<?php

declare(strict_types=1);

namespace Marko\Cache\Redis\Tests;

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Redis\Config\RedisCacheConfig;
use Marko\Cache\Redis\Driver\RedisCacheDriver;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Redis\Exceptions\RedisConnectionException;
use Marko\Redis\RedisConnectionFactory;
use Marko\Redis\RedisConnectionFactoryInterface;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Psr\Clock\ClockInterface;

/**
 * Wire the cache-redis module bindings, plus the marko/redis factory, into a container.
 *
 * @param array<string, mixed> $config
 */
function createCacheRedisContainer(
    array $config,
): Container {
    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository($config));
    $container->instance(ClockInterface::class, new FakeClock());

    $container->bind(RedisConnectionFactoryInterface::class, RedisConnectionFactory::class);

    $module = require dirname(__DIR__) . '/module.php';

    foreach ($module['bindings'] as $id => $implementation) {
        $container->bind($id, $implementation);
    }

    return $container;
}

/**
 * @return array<string, mixed>
 */
function cacheRedisConfig(
    string $connection = 'cache',
): array {
    $settings = [
        'host' => 'redis.internal',
        'port' => 6380,
        'password' => 'secret',
        'database' => 3,
        'scheme' => 'tls',
    ];
    $config = [
        'cache-redis.connection' => $connection,
        'cache-redis.prefix' => 'app:cache:',
        'cache.driver' => 'redis',
        'cache.path' => '/tmp/cache',
        'cache.default_ttl' => 3600,
        'encryption.key' => 'test-signing-key',
        'redis.connections' => ['default' => $settings, 'cache' => $settings],
    ];

    foreach (['default', 'cache'] as $name) {
        foreach ($settings as $key => $value) {
            $config["redis.connections.$name.$key"] = $value;
        }
    }

    return $config;
}

describe('cache-redis module bindings', function (): void {
    it('ships a cache-redis config file naming a redis connection and a key prefix', function (): void {
        $config = require dirname(__DIR__) . '/config/cache-redis.php';

        expect($config)->toBe([
            'connection' => 'default',
            'prefix' => $config['prefix'],
        ])->and($config['prefix'])->toBeString();
    });

    it('reads the connection name and prefix from cache-redis config', function (): void {
        $redisCacheConfig = new RedisCacheConfig(new FakeConfigRepository(cacheRedisConfig()));

        expect($redisCacheConfig->connection())->toBe('cache')
            ->and($redisCacheConfig->prefix())->toBe('app:cache:');
    });

    it('resolves CacheInterface to RedisCacheDriver on the configured marko/redis connection', function (): void {
        $container = createCacheRedisContainer(cacheRedisConfig());

        expect($container->get(CacheInterface::class))->toBeInstanceOf(RedisCacheDriver::class);
    });

    it('throws a loud error when cache-redis names a connection redis does not configure', function (): void {
        $container = createCacheRedisContainer(cacheRedisConfig(connection: 'missing'));

        expect(fn () => $container->get(CacheInterface::class))
            ->toThrow(RedisConnectionException::class, "Redis connection 'missing' is not configured");
    });
});
