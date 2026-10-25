<?php

declare(strict_types=1);

namespace Gin\EnvMerger\DTO;

class Conflict
{
    public function __construct(
        public readonly string $key,
        public readonly string $baseValue,
        public readonly string $incomingValue,
        public readonly ?string $baseQuote = null,
        public readonly ?string $incomingQuote = null,
    ) {
    }
}
