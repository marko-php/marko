<?php

declare(strict_types=1);

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\PageCache\Config\PageCacheConfig;
use Marko\Testing\Fake\FakeConfigRepository;

it('returns the configured driver name', function (): void {
    $config = new PageCacheConfig(new FakeConfigRepository([
        'page-cache.driver' => 'redis',
    ]));

    expect($config->driver())->toBe('redis');
});

it('returns the configured storage path', function (): void {
    $config = new PageCacheConfig(new FakeConfigRepository([
        'page-cache.path' => '/tmp/page-cache',
    ]));

    expect($config->path())->toBe('/tmp/page-cache');
});

it('returns the configured default ttl as int', function (): void {
    $config = new PageCacheConfig(new FakeConfigRepository([
        'page-cache.default_ttl' => 7200,
    ]));

    expect($config->defaultTtl())->toBe(7200);
});

it('returns the configured cacheable status codes as array of ints', function (): void {
    $config = new PageCacheConfig(new FakeConfigRepository([
        'page-cache.cacheable_status_codes' => ['200', '301'],
    ]));

    expect($config->cacheableStatusCodes())->toBe([200, 301]);
});

it('returns the configured cacheable methods as array of strings', function (): void {
    $config = new PageCacheConfig(new FakeConfigRepository([
        'page-cache.cacheable_methods' => ['get', 'head'],
    ]));

    expect($config->cacheableMethods())->toBe(['GET', 'HEAD']);
});

it('propagates ConfigNotFoundException when a key is missing', function (): void {
    $config = new PageCacheConfig(new FakeConfigRepository([]));

    $config->driver();
})->throws(ConfigNotFoundException::class);
