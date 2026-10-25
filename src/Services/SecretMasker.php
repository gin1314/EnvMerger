<?php

declare(strict_types=1);

namespace Gin\EnvMerger\Services;

class SecretMasker
{
    /**
     * Keywords that suggest a key contains sensitive data
     * @var array<string>
     */
    private const SENSITIVE_KEYWORDS = [
        'SECRET',
        'PASSWORD',
        'PASSWD',
        'PASS',
        'TOKEN',
        'KEY',
        'AUTH',
        'PRIVATE',
        'CREDENTIAL',
        'APIKEY',
        'ACCESS_KEY',
        'CERTIFICATE',
        'SALT',
        'HASH',
        'SIGNATURE',
    ];

    public function isSecret(string $key): bool
    {
        $normalized = strtoupper($key);

        foreach (self::SENSITIVE_KEYWORDS as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    public function mask(string $key, ?string $value, bool $showSecrets = false): string
    {
        if ($value === null || $value === '') {
            return '<empty>';
        }

        if ($showSecrets) {
            return $value;
        }

        if ($this->isSecret($key)) {
            $len = min(max(mb_strlen($value), 6), 10);
            return str_repeat('•', $len);
        }

        return $value;
    }
}
