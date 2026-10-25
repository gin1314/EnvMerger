<?php

use Gin\EnvMerger\DTO\EnvDocument;
use Gin\EnvMerger\Enums\LineType;
use Gin\EnvMerger\Parser\EnvParser;
use Gin\EnvMerger\Parser\EnvSerializer;

beforeEach(function () {
    $this->parser = new EnvParser();
    $this->serializer = new EnvSerializer();
});

it('parses empty string into empty document', function () {
    $doc = $this->parser->parseString('');
    expect($doc->lines())->toBeEmpty();
    expect($doc->variables())->toBeEmpty();
});

it('preserves comments and empty lines', function () {
    $content = "# Application Configuration\n\nAPP_NAME=Laravel\n# End of config\n";
    $doc = $this->parser->parseString($content);

    expect($doc->lines())->toHaveCount(4);
    expect($doc->lines()[0]->type)->toBe(LineType::COMMENT);
    expect($doc->lines()[0]->raw)->toBe('# Application Configuration');
    expect($doc->lines()[1]->type)->toBe(LineType::EMPTY);
    expect($doc->lines()[2]->type)->toBe(LineType::VARIABLE);
    expect($doc->lines()[2]->key)->toBe('APP_NAME');
    expect($doc->lines()[2]->value)->toBe('Laravel');
    expect($doc->lines()[3]->type)->toBe(LineType::COMMENT);

    $serialized = $this->serializer->serialize($doc);
    expect($serialized)->toBe($content);
});

it('parses quoted values correctly', function () {
    $content = <<<ENV
APP_NAME="My Complex App"
SINGLE_QUOTED='Single Quoted Value'
HASH_INSIDE_DOUBLE="value with # hash inside"
HASH_INSIDE_SINGLE='value with # hash inside'
ESCAPED="escaped \"quote\" inside"
ENV;

    $doc = $this->parser->parseString($content);

    expect($doc->get('APP_NAME'))->toBe('My Complex App');
    expect($doc->get('SINGLE_QUOTED'))->toBe('Single Quoted Value');
    expect($doc->get('HASH_INSIDE_DOUBLE'))->toBe('value with # hash inside');
    expect($doc->get('HASH_INSIDE_SINGLE'))->toBe('value with # hash inside');
    expect($doc->get('ESCAPED'))->toBe('escaped "quote" inside');
});

it('extracts inline comments without polluting values', function () {
    $content = <<<ENV
DB_PORT=3306 # MySQL port
APP_ENV="production" # The active environment
ENV;

    $doc = $this->parser->parseString($content);

    expect($doc->get('DB_PORT'))->toBe('3306');
    expect($doc->getLine('DB_PORT')?->inlineComment)->toBe('# MySQL port');

    expect($doc->get('APP_ENV'))->toBe('production');
    expect($doc->getLine('APP_ENV')?->inlineComment)->toBe('# The active environment');
});

it('updates variable in-place while preserving quotes and inline comments', function () {
    $content = "DB_HOST=127.0.0.1 # local host\nAPP_NAME=\"Original Name\"\n";
    $doc = $this->parser->parseString($content);

    $doc->set('DB_HOST', 'db.internal.production');
    $doc->set('APP_NAME', 'New Updated Name');

    $serialized = $this->serializer->serialize($doc);

    expect($serialized)->toContain("DB_HOST=db.internal.production # local host");
    expect($serialized)->toContain('APP_NAME="New Updated Name"');
});

it('handles export prefix', function () {
    $content = "export API_TOKEN=xyz123\n";
    $doc = $this->parser->parseString($content);

    expect($doc->has('API_TOKEN'))->toBeTrue();
    expect($doc->get('API_TOKEN'))->toBe('xyz123');
    expect($doc->getLine('API_TOKEN')?->exportPrefix)->toBeTrue();

    $doc->set('API_TOKEN', 'new_token');
    $serialized = $this->serializer->serialize($doc);
    expect($serialized)->toContain('export API_TOKEN=new_token');
});
