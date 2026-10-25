<?php

declare(strict_types=1);

namespace Gin\EnvMerger\DTO;

use Gin\EnvMerger\Enums\LineType;

class EnvLine
{
    public function __construct(
        public readonly LineType $type,
        public readonly string $raw,
        public readonly ?string $key = null,
        public readonly ?string $value = null,
        public readonly ?string $quote = null,
        public readonly bool $exportPrefix = false,
        public readonly ?string $inlineComment = null,
        public readonly string $leadingWhitespace = '',
        public readonly string $spacingBeforeEquals = '',
        public readonly string $spacingAfterEquals = '',
    ) {
    }

    public static function createVariable(
        string $key,
        string $value,
        ?string $quote = null,
        bool $exportPrefix = false,
        ?string $inlineComment = null,
        string $leadingWhitespace = '',
        string $spacingBeforeEquals = '',
        string $spacingAfterEquals = '',
    ): self {
        $quoteChar = $quote ?? self::determineQuote($value);
        $raw = self::buildRaw(
            $key,
            $value,
            $quoteChar,
            $exportPrefix,
            $inlineComment,
            $leadingWhitespace,
            $spacingBeforeEquals,
            $spacingAfterEquals
        );

        return new self(
            LineType::VARIABLE,
            $raw,
            $key,
            $value,
            $quoteChar,
            $exportPrefix,
            $inlineComment,
            $leadingWhitespace,
            $spacingBeforeEquals,
            $spacingAfterEquals
        );
    }

    public static function createComment(string $raw): self
    {
        return new self(LineType::COMMENT, $raw);
    }

    public static function createEmpty(string $raw = ''): self
    {
        return new self(LineType::EMPTY, $raw);
    }

    public function isVariable(): bool
    {
        return $this->type === LineType::VARIABLE;
    }

    public function isComment(): bool
    {
        return $this->type === LineType::COMMENT;
    }

    public function isEmpty(): bool
    {
        return $this->type === LineType::EMPTY;
    }

    public function withValue(string $newValue, ?string $preferredQuote = null): self
    {
        if (! $this->isVariable() || $this->key === null) {
            return $this;
        }

        $quote = $preferredQuote ?? $this->quote ?? self::determineQuote($newValue);

        // If existing quote was single quotes but new value contains single quotes, switch to double quotes
        if ($quote === "'" && str_contains($newValue, "'")) {
            $quote = '"';
        }

        $raw = self::buildRaw(
            $this->key,
            $newValue,
            $quote,
            $this->exportPrefix,
            $this->inlineComment,
            $this->leadingWhitespace,
            $this->spacingBeforeEquals,
            $this->spacingAfterEquals
        );

        return new self(
            LineType::VARIABLE,
            $raw,
            $this->key,
            $newValue,
            $quote,
            $this->exportPrefix,
            $this->inlineComment,
            $this->leadingWhitespace,
            $this->spacingBeforeEquals,
            $this->spacingAfterEquals
        );
    }

    public function toLineString(): string
    {
        return $this->raw;
    }

    public static function determineQuote(string $value): ?string
    {
        // Quote if empty, or contains spaces, tabs, newlines, hashes, quotes, or equals
        if ($value === '' || preg_match('/[\s#="\'$]/', $value)) {
            return '"';
        }

        return null;
    }

    private static function buildRaw(
        string $key,
        string $value,
        ?string $quote,
        bool $exportPrefix,
        ?string $inlineComment,
        string $leadingWhitespace,
        string $spacingBeforeEquals,
        string $spacingAfterEquals
    ): string {
        $prefix = $exportPrefix ? 'export ' : '';
        $formattedValue = self::formatValueForStorage($value, $quote);
        $comment = $inlineComment !== null ? ' ' . ltrim($inlineComment) : '';

        return "{$leadingWhitespace}{$prefix}{$key}{$spacingBeforeEquals}={$spacingAfterEquals}{$formattedValue}{$comment}";
    }

    private static function formatValueForStorage(string $value, ?string $quote): string
    {
        if ($quote === '"') {
            // Escape double quotes and newlines
            $escaped = str_replace(['\\', '"', "\n", "\r", "\t"], ['\\\\', '\\"', '\n', '\r', '\t'], $value);
            return "\"{$escaped}\"";
        }

        if ($quote === "'") {
            // In single quotes, only backslash and single quotes need escaping
            $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
            return "'{$escaped}'";
        }

        return $value;
    }
}
