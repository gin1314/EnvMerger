<?php

use Gin\EnvMerger\Commands\MergeCommand;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/env_merger_cli_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);

    $this->baseFile = $this->tempDir . '/.env';
    $this->targetFile = $this->tempDir . '/.env.production';
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        $files = scandir($this->tempDir) ?: [];
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                @unlink("{$this->tempDir}/{$file}");
            }
        }
        @rmdir($this->tempDir);
    }
});

it('executes merge command successfully via CLI', function () {
    file_put_contents($this->baseFile, "APP_ENV=local\nDB_PASSWORD=secret123\n");
    file_put_contents($this->targetFile, "APP_ENV=production\nDB_PASSWORD=prodsecret456\nNEW_VAR=hello\n");

    $command = new MergeCommand();
    $tester = new CommandTester($command);

    $exitCode = $tester->execute([
        'base' => $this->baseFile,
        'target' => $this->targetFile,
        '--no-backup' => true,
    ]);

    expect($exitCode)->toBe(0);

    $output = $tester->getDisplay();
    expect($output)->toContain('Merging');
    expect($output)->toContain('Updated Variables');
    expect($output)->toContain('New Variables Added');
    expect($output)->toContain('Successfully updated');

    // Secrets masked by default in table
    expect($output)->toContain('••••••');
    expect($output)->not->toContain('prodsecret456');

    // File was updated
    $content = file_get_contents($this->baseFile);
    expect($content)->toContain('APP_ENV=production');
    expect($content)->toContain('DB_PASSWORD=prodsecret456');
    expect($content)->toContain('NEW_VAR=hello');
});

it('unmasks secrets when --show-secrets flag is provided', function () {
    file_put_contents($this->baseFile, "DB_PASSWORD=secret123\n");
    file_put_contents($this->targetFile, "DB_PASSWORD=prodsecret456\n");

    $command = new MergeCommand();
    $tester = new CommandTester($command);

    $exitCode = $tester->execute([
        'base' => $this->baseFile,
        'target' => $this->targetFile,
        '--no-backup' => true,
        '--show-secrets' => true,
    ]);

    expect($exitCode)->toBe(0);
    $output = $tester->getDisplay();
    expect($output)->toContain('prodsecret456');
});

it('respects --dry-run option in CLI', function () {
    $baseContent = "APP_NAME=OldName\n";
    file_put_contents($this->baseFile, $baseContent);
    file_put_contents($this->targetFile, "APP_NAME=NewName\n");

    $command = new MergeCommand();
    $tester = new CommandTester($command);

    $exitCode = $tester->execute([
        'base' => $this->baseFile,
        'target' => $this->targetFile,
        '--dry-run' => true,
        '--no-backup' => true,
    ]);

    expect($exitCode)->toBe(0);
    $output = $tester->getDisplay();
    expect($output)->toContain('[DRY RUN]');
    expect(file_get_contents($this->baseFile))->toBe($baseContent);
});

it('writes to custom output file when --output is passed', function () {
    file_put_contents($this->baseFile, "APP_NAME=BaseApp\n");
    file_put_contents($this->targetFile, "APP_NAME=TargetApp\n");
    $customOutput = $this->tempDir . '/.env.merged';

    $command = new MergeCommand();
    $tester = new CommandTester($command);

    $exitCode = $tester->execute([
        'base' => $this->baseFile,
        'target' => $this->targetFile,
        '--output' => $customOutput,
        '--no-backup' => true,
    ]);

    expect($exitCode)->toBe(0);
    expect(file_exists($customOutput))->toBeTrue();
    expect(file_get_contents($customOutput))->toContain('APP_NAME=TargetApp');
    expect(file_get_contents($this->baseFile))->toContain('APP_NAME=BaseApp');
});

it('returns failure when base file does not exist', function () {
    $command = new MergeCommand();
    $tester = new CommandTester($command);

    $exitCode = $tester->execute([
        'base' => $this->tempDir . '/non_existent_file',
        'target' => $this->targetFile,
    ]);

    expect($exitCode)->toBe(1);
    expect($tester->getDisplay())->toContain('Base file not found');
});
