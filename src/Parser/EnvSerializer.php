<?php

declare(strict_types=1);

namespace Gin\EnvMerger\Parser;

use Gin\EnvMerger\DTO\EnvDocument;

class EnvSerializer
{
    public function serialize(EnvDocument $document): string
    {
        $lines = $document->lines();
        if (empty($lines)) {
            return '';
        }

        $output = [];
        foreach ($lines as $line) {
            $output[] = $line->toLineString();
        }

        return implode("\n", $output) . "\n";
    }

    public function writeToFile(EnvDocument $document, string $filePath): void
    {
        $dir = dirname($filePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = $this->serialize($document);
        $result = file_put_contents($filePath, $content);

        if ($result === false) {
            throw new \RuntimeException("Failed to write environment file to {$filePath}");
        }
    }
}
