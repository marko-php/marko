<?php

declare(strict_types=1);

use Marko\Cache\Config\CacheConfig;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Container\Container;
use Marko\Core\Path\ProjectPaths;
use Marko\Testing\Fake\FakeConfigRepository;

it('reads driver from config without fallback', function (): void {
    $config = new CacheConfig(new FakeConfigRepository([
        'cache.driver' => 'redis',
    ]));

    expect($config->driver())->toBe('redis');
});

it('reads path from config without fallback', function (): void {
    $config = new CacheConfig(new FakeConfigRepository([
        'cache.path' => '/var/cache',
    ]));

    expect($config->path())->toBe('/var/cache');
});

it('resolves a relative path against the project root, not the working directory', function (): void {
    $config = new CacheConfig(
        new FakeConfigRepository(['cache.path' => 'storage/cache']),
        new ProjectPaths('/srv/app'),
    );

    expect($config->path())->toBe('/srv/app/storage/cache');
});

it('gets the project root from the container', function (): void {
    $container = new Container();
    $container->instance(
        ConfigRepositoryInterface::class,
        new FakeConfigRepository(['cache.path' => 'storage/cache']),
    );
    $container->instance(ProjectPaths::class, new ProjectPaths('/srv/app'));

    expect($container->get(CacheConfig::class)->path())->toBe('/srv/app/storage/cache');
});

it('keeps an absolute path as configured', function (): void {
    $config = new CacheConfig(
        new FakeConfigRepository(['cache.path' => '/var/cache']),
        new ProjectPaths('/srv/app'),
    );

    expect($config->path())->toBe('/var/cache');
});

it('reads default_ttl from config without fallback', function (): void {
    $config = new CacheConfig(new FakeConfigRepository([
        'cache.default_ttl' => 7200,
    ]));

    expect($config->defaultTtl())->toBe(7200);
});

it('throws ConfigNotFoundException when driver is missing', function (): void {
    $config = new CacheConfig(new FakeConfigRepository([]));

    $config->driver();
})->throws(ConfigNotFoundException::class);

it('throws ConfigNotFoundException when path is missing', function (): void {
    $config = new CacheConfig(new FakeConfigRepository([]));

    $config->path();
})->throws(ConfigNotFoundException::class);

it('throws ConfigNotFoundException when default_ttl is missing', function (): void {
    $config = new CacheConfig(new FakeConfigRepository([]));

    $config->defaultTtl();
})->throws(ConfigNotFoundException::class);

it('config file contains all required keys with defaults', function (): void {
    $configFile = require dirname(__DIR__, 2) . '/config/cache.php';

    expect($configFile)->toHaveKey('driver')
        ->and($configFile)->toHaveKey('path')
        ->and($configFile)->toHaveKey('default_ttl');
});
