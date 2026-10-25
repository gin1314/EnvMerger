<?php

declare(strict_types=1);

namespace Gin\EnvMerger\Services;

use Gin\EnvMerger\DTO\Conflict;
use Gin\EnvMerger\DTO\EnvDocument;

class ConflictDetector
{
    /**
     * @return array{
     *     conflicts: array<string, Conflict>,
     *     added: array<string, string>,
     *     identical: array<string, string>
     * }
     */
    public function detect(EnvDocument $base, EnvDocument $incoming): array
    {
        $conflicts = [];
        $added = [];
        $identical = [];

        foreach ($incoming->variables() as $key => $incomingValue) {
            if (! $base->has($key)) {
                $added[$key] = $incomingValue;
                continue;
            }

            $baseValue = $base->get($key) ?? '';

            if ($baseValue === $incomingValue) {
                $identical[$key] = $incomingValue;
            } else {
                $baseLine = $base->getLine($key);
                $incomingLine = $incoming->getLine($key);

                $conflicts[$key] = new Conflict(
                    key: $key,
                    baseValue: $baseValue,
                    incomingValue: $incomingValue,
                    baseQuote: $baseLine?->quote,
                    incomingQuote: $incomingLine?->quote
                );
            }
        }

        return [
            'conflicts' => $conflicts,
            'added' => $added,
            'identical' => $identical,
        ];
    }
}
