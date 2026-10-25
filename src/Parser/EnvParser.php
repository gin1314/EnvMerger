<?php

declare(strict_types=1);

namespace Gin\EnvMerger\Parser;

use Gin\EnvMerger\DTO\EnvDocument;
use Gin\EnvMerger\DTO\EnvLine;
use RuntimeException;

class EnvParser
{
    public function parseFile(string $filePath): EnvDocument
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException("Environment file not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Failed to read environment file: {$filePath}");
        }

        return $this->parseString($content);
    }

    public function parseString(string $content): EnvDocument
    {
        $document = new EnvDocument();

        if ($content === '') {
            return $document;
        }

        $trimmed = rtrim($content, "\r\n");
        if ($trimmed === '') {
            return $document;
        }

        $rawLines = preg_split('/\r\n|\n|\r/', $trimmed);
        if ($rawLines === false) {
            return $document;
        }

        foreach ($rawLines as $rawLine) {
            $document->addLine($this->parseLine($rawLine));
        }

        return $document;
    }

    public function parseLine(string $rawLine): EnvLine
    {
        $trimmed = trim($rawLine);

        // Empty line
        if ($trimmed === '') {
            return EnvLine::createEmpty($rawLine);
        }

        // Comment line
        if (str_starts_with($trimmed, '#')) {
            return EnvLine::createComment($rawLine);
        }

        // Variable assignment: [leading spaces][export ][KEY][spaces]=[spaces][VALUE]
        if (preg_match('/^(\s*)(export\s+)?([A-Za-z0-9_.\-]+)(\s*)=(\s*)(.*)$/s', $rawLine, $matches)) {
            $leadingWhitespace = $matches[1];
            $exportPrefix = ! empty($matches[2]);
            $key = $matches[3];
            $spacingBefore = $matches[4];
            $spacingAfter = $matches[5];
            $remainder = $matches[6];

            [$value, $quote, $inlineComment] = $this->parseValueAndComment($remainder);

            return new EnvLine(
                type: \Gin\EnvMerger\Enums\LineType::VARIABLE,
                raw: $rawLine,
                key: $key,
                value: $value,
                quote: $quote,
                exportPrefix: $exportPrefix,
                inlineComment: $inlineComment,
                leadingWhitespace: $leadingWhitespace,
                spacingBeforeEquals: $spacingBefore,
                spacingAfterEquals: $spacingAfter
            );
        }

        // Unrecognized format preserved as comment/verbatim line
        return EnvLine::createComment($rawLine);
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string} [value, quote, inlineComment]
     */
    private function parseValueAndComment(string $remainder): array
    {
        $len = strlen($remainder);
        if ($len === 0) {
            return ['', null, null];
        }

        $firstChar = $remainder[0];

        // Double quoted string
        if ($firstChar === '"') {
            return $this->parseQuotedString($remainder, '"');
        }

        // Single quoted string
        if ($firstChar === "'") {
            return $this->parseQuotedString($remainder, "'");
        }

        // Unquoted value: check for inline comment preceded by whitespace
        if (preg_match('/^(.*?)(?:\s+(#.*))$/', $remainder, $commentMatches)) {
            $val = rtrim($commentMatches[1]);
            $comment = trim($commentMatches[2]);
            return [$val, null, $comment];
        }

        return [rtrim($remainder), null, null];
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string} [value, quote, inlineComment]
     */
    private function parseQuotedString(string $remainder, string $quoteChar): array
    {
        $len = strlen($remainder);
        $escaped = false;
        $closingIndex = -1;

        for ($i = 1; $i < $len; $i++) {
            $char = $remainder[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === $quoteChar) {
                $closingIndex = $i;
                break;
            }
        }

        if ($closingIndex === -1) {
            // Unclosed quote: treat rest of line as value
            $inner = substr($remainder, 1);
            return [$inner, $quoteChar, null];
        }

        $quotedContent = substr($remainder, 1, $closingIndex - 1);
        $afterQuote = substr($remainder, $closingIndex + 1);

        $value = $quoteChar === '"'
            ? str_replace(['\\\\', '\\"', '\n', '\r', '\t'], ['\\', '"', "\n", "\r", "\t"], $quotedContent)
            : str_replace(['\\\\', "\\'"], ['\\', "'"], $quotedContent);

        $inlineComment = null;
        if (str_contains($afterQuote, '#')) {
            $hashPos = strpos($afterQuote, '#');
            if ($hashPos !== false) {
                $inlineComment = trim(substr($afterQuote, $hashPos));
            }
        }

        return [$value, $quoteChar, $inlineComment];
    }
}
