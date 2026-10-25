<?php

use Gin\EnvMerger\Parser\EnvParser;
use Gin\EnvMerger\Services\ConflictDetector;

beforeEach(function () {
    $this->parser = new EnvParser();
    $this->detector = new ConflictDetector();
});

it('detects added keys, identical keys, and conflicts', function () {
    $baseContent = <<<ENV
APP_NAME=OldApp
APP_ENV=local
DB_HOST=127.0.0.1
ENV;

    $incomingContent = <<<ENV
APP_NAME=OldApp
APP_ENV=production
DB_HOST=db.prod
NEW_FEATURE_ENABLED=true
ENV;

    $baseDoc = $this->parser->parseString($baseContent);
    $incomingDoc = $this->parser->parseString($incomingContent);

    $results = $this->detector->detect($baseDoc, $incomingDoc);

    expect($results['identical'])->toHaveKey('APP_NAME');
    expect($results['identical']['APP_NAME'])->toBe('OldApp');

    expect($results['added'])->toHaveKey('NEW_FEATURE_ENABLED');
    expect($results['added']['NEW_FEATURE_ENABLED'])->toBe('true');

    expect($results['conflicts'])->toHaveKeys(['APP_ENV', 'DB_HOST']);
    expect($results['conflicts']['APP_ENV']->baseValue)->toBe('local');
    expect($results['conflicts']['APP_ENV']->incomingValue)->toBe('production');
});
