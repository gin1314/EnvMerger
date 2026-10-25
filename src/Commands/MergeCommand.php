<?php

declare(strict_types=1);

namespace Gin\EnvMerger\Commands;

use Gin\EnvMerger\Enums\MergeStrategy;
use Gin\EnvMerger\Services\EnvMerger;
use Gin\EnvMerger\Services\SecretMasker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Laravel\Prompts\select;

class MergeCommand extends Command
{
    protected static $defaultName = 'env:merge';
    protected static $defaultDescription = 'Merge an existing .env file with another environment file';

    public function __construct(
        private readonly ?EnvMerger $merger = null,
        private readonly ?SecretMasker $masker = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('env:merge')
            ->setDescription('Merge an existing .env file with another environment file (e.g. .env.production)')
            ->addArgument('base', InputArgument::OPTIONAL, 'Path to the base environment file', '.env')
            ->addArgument('target', InputArgument::OPTIONAL, 'Path to the incoming environment file to merge')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (default: in-place update)')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Run interactive conflict resolution wizard')
            ->addOption('strategy', 's', InputOption::VALUE_REQUIRED, 'Non-interactive conflict strategy (incoming, keep-base, fail)', 'incoming')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without writing to disk')
            ->addOption('backup', null, InputOption::VALUE_NEGATABLE, 'Create a backup of the destination file before modifying', true)
            ->addOption('show-secrets', null, InputOption::VALUE_NONE, 'Show unmasked secret values in diffs and outputs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $merger = $this->merger ?? new EnvMerger();
        $masker = $this->masker ?? new SecretMasker();

        /** @var string $base */
        $base = (string) $input->getArgument('base');
        /** @var ?string $target */
        $target = $input->getArgument('target');

        // Resolve target if not provided
        if ($target === null || $target === '') {
            $discovered = $this->discoverTargetFiles($base);

            if (empty($discovered)) {
                $io->error("No target environment file provided, and no candidate .env.* files found.");
                return Command::FAILURE;
            }

            if (count($discovered) === 1) {
                $target = $discovered[0];
                $io->note("Using auto-detected target: {$target}");
            } else {
                if ($input->isInteractive()) {
                    $target = select(
                        label: "Which environment file would you like to merge into {$base}?",
                        options: array_combine($discovered, $discovered),
                        default: in_array('.env.production', $discovered, true) ? '.env.production' : $discovered[0]
                    );
                } else {
                    $target = in_array('.env.production', $discovered, true) ? '.env.production' : $discovered[0];
                    $io->note("Defaulting to target: {$target}");
                }
            }
        }

        if (! file_exists($base)) {
            $io->error("Base file not found: {$base}");
            return Command::FAILURE;
        }

        if (! file_exists($target)) {
            $io->error("Target file not found: {$target}");
            return Command::FAILURE;
        }

        /** @var ?string $outputPath */
        $outputPath = $input->getOption('output');
        $interactive = (bool) $input->getOption('interactive');
        $strategyString = (string) $input->getOption('strategy');
        $dryRun = (bool) $input->getOption('dry-run');
        $backup = (bool) $input->getOption('backup');
        $showSecrets = (bool) $input->getOption('show-secrets');

        try {
            $strategy = MergeStrategy::fromString($strategyString);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::INVALID;
        }

        $io->title("Merging {$target} into {$base}");

        try {
            $result = $merger->merge(
                basePath: $base,
                targetPath: $target,
                outputPath: $outputPath,
                strategy: $strategy,
                interactive: $interactive,
                dryRun: $dryRun,
                createBackup: $backup,
                showSecrets: $showSecrets
            );
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        // Display results
        if ($result->hasChanges()) {
            if (! empty($result->updatedKeys)) {
                $io->section("Updated Variables (" . count($result->updatedKeys) . ")");
                $rows = [];
                foreach ($result->updatedKeys as $key => $change) {
                    $oldVal = $masker->mask($key, $change['old'], $showSecrets);
                    $newVal = $masker->mask($key, $change['new'], $showSecrets);
                    $rows[] = [$key, $oldVal, $newVal];
                }
                $io->table(['Key', 'Previous Value', 'New Value'], $rows);
            }

            if (! empty($result->addedKeys)) {
                $io->section("New Variables Added (" . count($result->addedKeys) . ")");
                $rows = [];
                foreach ($result->addedKeys as $key => $val) {
                    $maskedVal = $masker->mask($key, $val, $showSecrets);
                    $rows[] = [$key, $maskedVal];
                }
                $io->table(['Key', 'Value'], $rows);
            }
        } else {
            $io->info("No differences found. Both files already have identical variable values.");
        }

        if ($dryRun) {
            $io->warning("[DRY RUN] No files were modified. Preview only.");
            return Command::SUCCESS;
        }

        if ($result->backupPath !== null) {
            $io->comment("Safety backup created: {$result->backupPath}");
        }

        $destination = $result->outputPath;
        $totalChanges = $result->totalChanges();

        $io->success("Successfully updated {$destination} with {$totalChanges} change(s).");

        return Command::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function discoverTargetFiles(string $basePath): array
    {
        $dir = dirname($basePath);
        if (! is_dir($dir)) {
            return [];
        }

        $candidates = glob("{$dir}/.env.*") ?: [];
        $results = [];

        foreach ($candidates as $candidate) {
            $filename = basename($candidate);
            // Skip backup files or base itself
            if (str_contains($filename, '.backup') || $candidate === $basePath) {
                continue;
            }
            $results[] = $candidate;
        }

        return $results;
    }
}
