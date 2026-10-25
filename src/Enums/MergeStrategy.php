<?php

declare(strict_types=1);

namespace Gin\EnvMerger\Enums;

use InvalidArgumentException;

enum MergeStrategy: string
{
    case INCOMING = 'incoming';
    case KEEP_BASE = 'keep-base';
    case FAIL = 'fail';

    public static function fromString(string $value): self
    {
        return match (strtolower(trim($value))) {
            'incoming', 'override', 'force' => self::INCOMING,
            'keep-base', 'keep_base', 'base', 'keep' => self::KEEP_BASE,
            'fail', 'strict', 'error' => self::FAIL,
            default => throw new InvalidArgumentException(
                "Invalid merge strategy: '{$value}'. Supported strategies: incoming, keep-base, fail."
            ),
        };
    }
}
