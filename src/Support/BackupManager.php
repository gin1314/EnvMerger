<?php

declare(strict_types=1);

namespace Gin\EnvMerger\Support;

use RuntimeException;

class BackupManager
{
    public function createBackup(string $filePath): ?string
    {
        if (! file_exists($filePath)) {
            return null;
        }

        $timestamp = date('Ymd_His');
        $backupPath = "{$filePath}.backup.{$timestamp}";

        $copied = copy($filePath, $backupPath);
        if (! $copied) {
            throw new RuntimeException("Failed to create backup file at: {$backupPath}");
        }

        return $backupPath;
    }
}
