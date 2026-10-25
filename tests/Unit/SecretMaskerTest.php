<?php

use Gin\EnvMerger\Services\SecretMasker;

beforeEach(function () {
    $this->masker = new SecretMasker();
});

it('identifies sensitive keys accurately', function () {
    expect($this->masker->isSecret('DB_PASSWORD'))->toBeTrue();
    expect($this->masker->isSecret('APP_KEY'))->toBeTrue();
    expect($this->masker->isSecret('STRIPE_SECRET'))->toBeTrue();
    expect($this->masker->isSecret('GITHUB_TOKEN'))->toBeTrue();
    expect($this->masker->isSecret('AWS_ACCESS_KEY_ID'))->toBeTrue();
    expect($this->masker->isSecret('JWT_AUTH_PRIVATE_KEY'))->toBeTrue();

    expect($this->masker->isSecret('APP_NAME'))->toBeFalse();
    expect($this->masker->isSecret('DB_PORT'))->toBeFalse();
    expect($this->masker->isSecret('CACHE_DRIVER'))->toBeFalse();
    expect($this->masker->isSecret('QUEUE_CONNECTION'))->toBeFalse();
});

it('masks sensitive values unless showSecrets is enabled', function () {
    $masked = $this->masker->mask('DB_PASSWORD', 'super_secret_123', false);
    expect($masked)->toContain('••••••');
    expect($masked)->not->toContain('super_secret');

    $revealed = $this->masker->mask('DB_PASSWORD', 'super_secret_123', true);
    expect($revealed)->toBe('super_secret_123');
});

it('leaves non-secret values intact', function () {
    expect($this->masker->mask('APP_NAME', 'My Website'))->toBe('My Website');
    expect($this->masker->mask('DB_PORT', '3306'))->toBe('3306');
});
