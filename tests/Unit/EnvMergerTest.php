<?php

use Gin\EnvMerger\Enums\MergeStrategy;
use Gin\EnvMerger\Parser\EnvParser;
use Gin\EnvMerger\Services\EnvMerger;

beforeEach(function () {
    $this->merger = new EnvMerger();
    $this->tempDir = sys_get_temp_dir() . '/env_merger_test_' . uniqid();
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

it('merges incoming strategy by default', function () {
    $baseContent = "# Main Settings\nAPP_NAME=LocalApp\nAPP_DEBUG=true\n";
    $targetContent = "APP_DEBUG=false\nNEW_KEY=hello_world\n";

    file_put_contents($this->baseFile, $baseContent);
    file_put_contents($this->targetFile, $targetContent);

    $result = $this->merger->merge(
        basePath: $this->baseFile,
        targetPath: $this->targetFile,
        strategy: MergeStrategy::INCOMING,
        createBackup: false
    );

    expect($result->hasChanges())->toBeTrue();
    expect($result->updatedKeys)->toHaveKey('APP_DEBUG');
    expect($result->updatedKeys['APP_DEBUG']['old'])->toBe('true');
    expect($result->updatedKeys['APP_DEBUG']['new'])->toBe('false');
    expect($result->addedKeys)->toHaveKey('NEW_KEY');

    $mergedContent = file_get_contents($this->baseFile);
    expect($mergedContent)->toContain("# Main Settings");
    expect($mergedContent)->toContain("APP_NAME=LocalApp");
    expect($mergedContent)->toContain("APP_DEBUG=false");
    expect($mergedContent)->toContain("NEW_KEY=hello_world");
});

it('preserves base values when using keep-base strategy', function () {
    $baseContent = "APP_ENV=local\n";
    $targetContent = "APP_ENV=production\nADDED_VAR=xyz\n";

    file_put_contents($this->baseFile, $baseContent);
    file_put_contents($this->targetFile, $targetContent);

    $result = $this->merger->merge(
        basePath: $this->baseFile,
        targetPath: $this->targetFile,
        strategy: MergeStrategy::KEEP_BASE,
        createBackup: false
    );

    expect($result->unchangedKeys)->toHaveKey('APP_ENV');
    expect($result->addedKeys)->toHaveKey('ADDED_VAR');

    $mergedContent = file_get_contents($this->baseFile);
    expect($mergedContent)->toContain("APP_ENV=local");
    expect($mergedContent)->toContain("ADDED_VAR=xyz");
});

it('throws exception in strict fail strategy when conflict exists', function () {
    file_put_contents($this->baseFile, "APP_ENV=local\n");
    file_put_contents($this->targetFile, "APP_ENV=production\n");

    expect(fn () => $this->merger->merge(
        basePath: $this->baseFile,
        targetPath: $this->targetFile,
        strategy: MergeStrategy::FAIL,
        createBackup: false
    ))->toThrow(RuntimeException::class, 'Merge failed in strict mode');
});

it('respects dry-run without modifying files', function () {
    $original = "APP_NAME=Old\n";
    file_put_contents($this->baseFile, $original);
    file_put_contents($this->targetFile, "APP_NAME=New\n");

    $result = $this->merger->merge(
        basePath: $this->baseFile,
        targetPath: $this->targetFile,
        dryRun: true,
        createBackup: false
    );

    expect($result->isDryRun)->toBeTrue();
    expect(file_get_contents($this->baseFile))->toBe($original);
});

it('supports custom interactive resolver', function () {
    file_put_contents($this->baseFile, "APP_ENV=local\nDB_PASS=localpass\n");
    file_put_contents($this->targetFile, "APP_ENV=prod\nDB_PASS=prodpass\n");

    $customResolver = function (array $conflicts, array $added, bool $showSecrets): array {
        return [
            'APP_ENV' => 'staging_custom',
            'DB_PASS' => 'custom_password_123',
        ];
    };

    $this->merger->merge(
        basePath: $this->baseFile,
        targetPath: $this->targetFile,
        interactive: true,
        createBackup: false,
        customResolver: $customResolver
    );

    $mergedContent = file_get_contents($this->baseFile);
    expect($mergedContent)->toContain("APP_ENV=staging_custom");
    expect($mergedContent)->toContain("DB_PASS=custom_password_123");
});

it('creates backup file when enabled', function () {
    file_put_contents($this->baseFile, "APP_NAME=Original\n");
    file_put_contents($this->targetFile, "APP_NAME=Modified\n");

    $result = $this->merger->merge(
        basePath: $this->baseFile,
        targetPath: $this->targetFile,
        createBackup: true
    );

    expect($result->backupPath)->not->toBeNull();
    expect(file_exists($result->backupPath))->toBeTrue();
    expect(file_get_contents($result->backupPath))->toBe("APP_NAME=Original\n");
});
