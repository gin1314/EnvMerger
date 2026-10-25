<?php

declare(strict_types=1);

namespace Gin\EnvMerger\Tests;

use Gin\EnvMerger\Parser\EnvParser;
use Gin\EnvMerger\Parser\EnvSerializer;
use Gin\EnvMerger\Services\ConflictDetector;
use Gin\EnvMerger\Services\EnvMerger;
use Gin\EnvMerger\Services\SecretMasker;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public EnvParser $parser;
    public EnvSerializer $serializer;
    public SecretMasker $masker;
    public ConflictDetector $detector;
    public EnvMerger $merger;
    public string $tempDir;
    public string $baseFile;
    public string $targetFile;
}
