<?php

declare(strict_types=1);

namespace Gin\EnvMerger\Services;

use Gin\EnvMerger\DTO\MergeResult;
use Gin\EnvMerger\Enums\MergeStrategy;
use Gin\EnvMerger\Parser\EnvParser;
use Gin\EnvMerger\Parser\EnvSerializer;
use Gin\EnvMerger\Support\BackupManager;
use RuntimeException;

class EnvMerger
{
    public function __construct(
        private readonly EnvParser $parser = new EnvParser(),
        private readonly EnvSerializer $serializer = new EnvSerializer(),
        private readonly ConflictDetector $conflictDetector = new ConflictDetector(),
        private readonly InteractiveResolver $interactiveResolver = new InteractiveResolver(),
        private readonly BackupManager $backupManager = new BackupManager()
    ) {
    }

    /**
     * @param ?callable(array<string, \Gin\EnvMerger\DTO\Conflict>, array<string, string>, bool): array<string, string> $customResolver
     */
    public function merge(
        string $basePath,
        string $targetPath,
        ?string $outputPath = null,
        MergeStrategy $strategy = MergeStrategy::INCOMING,
        bool $interactive = false,
        bool $dryRun = false,
        bool $createBackup = true,
        bool $showSecrets = false,
        ?callable $customResolver = null,
        bool $appendSourceComment = true
    ): MergeResult {
        if (! file_exists($basePath)) {
            throw new RuntimeException("Base environment file not found: {$basePath}");
        }

        if (! file_exists($targetPath)) {
            throw new RuntimeException("Target environment file not found: {$targetPath}");
        }

        $baseDoc = $this->parser->parseFile($basePath);
        $targetDoc = $this->parser->parseFile($targetPath);

        $detection = $this->conflictDetector->detect($baseDoc, $targetDoc);
        $conflicts = $detection['conflicts'];
        $added = $detection['added'];
        $identical = $detection['identical'];

        // Determine resolution for conflicts
        $resolved = [];
        if (! empty($conflicts)) {
            if ($interactive) {
                $resolver = $customResolver ?? fn ($c, $a, $s) => $this->interactiveResolver->resolve($c, $a, $s);
                $resolved = $resolver($conflicts, $added, $showSecrets);
            } elseif ($strategy === MergeStrategy::FAIL) {
                $conflictCount = count($conflicts);
                $conflictKeys = implode(', ', array_keys($conflicts));
                throw new RuntimeException(
                    "Merge failed in strict mode: {$conflictCount} conflict(s) detected [{$conflictKeys}]."
                );
            } elseif ($strategy === MergeStrategy::KEEP_BASE) {
                foreach ($conflicts as $key => $conflict) {
                    $resolved[$key] = $conflict->baseValue;
                }
            } else { // INCOMING
                foreach ($conflicts as $key => $conflict) {
                    $resolved[$key] = $conflict->incomingValue;
                }
            }
        }

        $updatedKeys = [];
        $unchangedKeys = $identical;

        // Apply conflict resolutions
        foreach ($conflicts as $key => $conflict) {
            $newValue = $resolved[$key] ?? $conflict->incomingValue;

            if ($newValue !== $conflict->baseValue) {
                $incomingLine = $targetDoc->getLine($key);
                $baseDoc->set($key, $newValue, $incomingLine?->quote ?? $conflict->incomingQuote);
                $updatedKeys[$key] = [
                    'old' => $conflict->baseValue,
                    'new' => $newValue,
                ];
            } else {
                $unchangedKeys[$key] = $conflict->baseValue;
            }
        }

        // Apply newly added keys
        $addedKeys = [];
        if (! empty($added)) {
            if ($appendSourceComment) {
                $baseDoc->appendEmptyLine();
                $baseDoc->appendComment("--- Merged from " . basename($targetPath) . " ---");
            }

            foreach ($added as $key => $value) {
                $incomingLine = $targetDoc->getLine($key);
                $baseDoc->append(
                    key: $key,
                    value: $value,
                    quote: $incomingLine?->quote,
                    inlineComment: $incomingLine?->inlineComment
                );
                $addedKeys[$key] = $value;
            }
        }

        $destination = $outputPath ?? $basePath;
        $backupPath = null;

        if (! $dryRun) {
            if ($createBackup && file_exists($destination)) {
                $backupPath = $this->backupManager->createBackup($destination);
            }

            $this->serializer->writeToFile($baseDoc, $destination);
        }

        return new MergeResult(
            basePath: $basePath,
            targetPath: $targetPath,
            outputPath: $destination,
            addedKeys: $addedKeys,
            updatedKeys: $updatedKeys,
            unchangedKeys: $unchangedKeys,
            backupPath: $backupPath,
            isDryRun: $dryRun
        );
    }
}
