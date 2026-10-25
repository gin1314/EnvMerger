<?php

declare(strict_types=1);

namespace Gin\EnvMerger\DTO;

class MergeResult
{
    /**
     * @param array<string, string> $addedKeys
     * @param array<string, array{old: string, new: string}> $updatedKeys
     * @param array<string, string> $unchangedKeys
     */
    public function __construct(
        public readonly string $basePath,
        public readonly string $targetPath,
        public readonly string $outputPath,
        public readonly array $addedKeys = [],
        public readonly array $updatedKeys = [],
        public readonly array $unchangedKeys = [],
        public readonly ?string $backupPath = null,
        public readonly bool $isDryRun = false,
    ) {
    }

    public function totalChanges(): int
    {
        return count($this->addedKeys) + count($this->updatedKeys);
    }

    public function hasChanges(): bool
    {
        return $this->totalChanges() > 0;
    }
}
