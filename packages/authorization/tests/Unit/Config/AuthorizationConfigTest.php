<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Unit\Config;

use Marko\Authorization\Config\AuthorizationConfig;
use Marko\Testing\Fake\FakeConfigRepository;

it('creates AuthorizationConfig with default guard accessor', function (): void {
    $config = new AuthorizationConfig(
        config: new FakeConfigRepository([
            'authorization.default_guard' => 'web',
        ]),
    );

    expect($config->defaultGuard())->toBe('web');
});

it('returns configured default guard', function (): void {
    $config = new AuthorizationConfig(
        config: new FakeConfigRepository([
            'authorization.default_guard' => 'api',
        ]),
    );

    expect($config->defaultGuard())->toBe('api');
});
