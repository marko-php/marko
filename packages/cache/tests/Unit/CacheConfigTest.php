<?php

declare(strict_types=1);

use Marko\Cache\Config\CacheConfig;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Testing\Fake\FakeConfigRepository;

it('reads driver from config without fallback', function () {
    $config = new CacheConfig(new FakeConfigRepository([
        'cache.driver' => 'redis',
    ]));

    expect($config->driver())->toBe('redis');
});

it('reads path from config without fallback', function () {
    $config = new CacheConfig(new FakeConfigRepository([
        'cache.path' => '/var/cache',
    ]));

    expect($config->path())->toBe('/var/cache');
});

it('reads default_ttl from config without fallback', function () {
    $config = new CacheConfig(new FakeConfigRepository([
        'cache.default_ttl' => 7200,
    ]));

    expect($config->defaultTtl())->toBe(7200);
});

it('throws ConfigNotFoundException when driver is missing', function () {
    $config = new CacheConfig(new FakeConfigRepository([]));

    $config->driver();
})->throws(ConfigNotFoundException::class);

it('throws ConfigNotFoundException when path is missing', function () {
    $config = new CacheConfig(new FakeConfigRepository([]));

    $config->path();
})->throws(ConfigNotFoundException::class);

it('throws ConfigNotFoundException when default_ttl is missing', function () {
    $config = new CacheConfig(new FakeConfigRepository([]));

    $config->defaultTtl();
})->throws(ConfigNotFoundException::class);

it('config file contains all required keys with defaults', function () {
    $configFile = require dirname(__DIR__, 2) . '/config/cache.php';

    expect($configFile)->toHaveKey('driver');
    expect($configFile)->toHaveKey('path');
    expect($configFile)->toHaveKey('default_ttl');
});
