<?php

declare(strict_types=1);

namespace Gin\EnvMerger\DTO;

class EnvDocument
{
    /**
     * @var array<int, EnvLine>
     */
    private array $lines = [];

    /**
     * Map of variable KEY => line index
     * @var array<string, int>
     */
    private array $keyIndex = [];

    /**
     * @param array<int, EnvLine> $lines
     */
    public function __construct(array $lines = [])
    {
        foreach ($lines as $line) {
            $this->addLine($line);
        }
    }

    public function addLine(EnvLine $line): self
    {
        $index = count($this->lines);
        $this->lines[] = $line;

        if ($line->isVariable() && $line->key !== null) {
            $this->keyIndex[$line->key] = $index;
        }

        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->keyIndex[$key]);
    }

    public function get(string $key): ?string
    {
        if (! $this->has($key)) {
            return null;
        }

        return $this->lines[$this->keyIndex[$key]]->value;
    }

    public function getLine(string $key): ?EnvLine
    {
        if (! $this->has($key)) {
            return null;
        }

        return $this->lines[$this->keyIndex[$key]];
    }

    public function set(string $key, string $value, ?string $quote = null): self
    {
        if ($this->has($key)) {
            $index = $this->keyIndex[$key];
            $currentLine = $this->lines[$index];
            $this->lines[$index] = $currentLine->withValue($value, $quote);
        } else {
            $this->append($key, $value, $quote);
        }

        return $this;
    }

    public function append(string $key, string $value, ?string $quote = null, ?string $inlineComment = null): self
    {
        $line = EnvLine::createVariable(
            key: $key,
            value: $value,
            quote: $quote,
            inlineComment: $inlineComment
        );

        $this->addLine($line);

        return $this;
    }

    public function appendComment(string $comment): self
    {
        $commentStr = str_starts_with(trim($comment), '#') ? $comment : '# ' . $comment;
        $this->addLine(EnvLine::createComment($commentStr));

        return $this;
    }

    public function appendEmptyLine(): self
    {
        $this->addLine(EnvLine::createEmpty());

        return $this;
    }

    /**
     * @return array<int, EnvLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * @return array<string, string>
     */
    public function variables(): array
    {
        $vars = [];
        foreach ($this->lines as $line) {
            if ($line->isVariable() && $line->key !== null) {
                $vars[$line->key] = $line->value ?? '';
            }
        }

        return $vars;
    }

    /**
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->keyIndex);
    }

    public function count(): int
    {
        return count($this->keyIndex);
    }
}
