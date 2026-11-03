<?php

declare(strict_types=1);

namespace Marko\Redis\Tests;

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Redis\Exceptions\RedisConnectionException;
use Marko\Redis\RedisConnection;
use Marko\Redis\RedisConnectionFactory;
use Marko\Redis\RedisConnectionFactoryInterface;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * Build a FakeConfigRepository holding redis.connections both as the nested
 * array and as flat dot-notation keys, the way the real repository resolves it.
 *
 * @param array<string, array<string, mixed>> $connections
 */
function redisConfig(
    array $connections,
): FakeConfigRepository {
    $flat = ['redis.connections' => $connections];

    foreach ($connections as $name => $settings) {
        $flat["redis.connections.$name"] = $settings;

        foreach ($settings as $key => $value) {
            $flat["redis.connections.$name.$key"] = $value;
        }
    }

    return new FakeConfigRepository($flat);
}

/**
 * @return array<string, mixed>
 */
function redisConnectionSettings(
    string $host = 'redis.internal',
    ?string $password = 'secret',
    int $database = 3,
    string $scheme = 'tls',
): array {
    return [
        'host' => $host,
        'port' => 6380,
        'password' => $password,
        'database' => $database,
        'scheme' => $scheme,
    ];
}

describe('RedisConnectionFactory', function (): void {
    it('implements RedisConnectionFactoryInterface', function (): void {
        $factory = new RedisConnectionFactory(redisConfig(['default' => redisConnectionSettings()]));

        expect($factory)->toBeInstanceOf(RedisConnectionFactoryInterface::class);
    });

    it('builds a named connection from redis.connections config', function (): void {
        $factory = new RedisConnectionFactory(redisConfig(['default' => redisConnectionSettings()]));

        $connection = $factory->connection('default');

        expect($connection)->toBeInstanceOf(RedisConnection::class)
            ->and($connection->host)->toBe('redis.internal')
            ->and($connection->port)->toBe(6380)
            ->and($connection->password)->toBe('secret')
            ->and($connection->database)->toBe(3)
            ->and($connection->scheme)->toBe('tls');
    });

    it('returns the same instance for the same name', function (): void {
        $factory = new RedisConnectionFactory(redisConfig(['default' => redisConnectionSettings()]));

        expect($factory->connection('default'))->toBe($factory->connection('default'));
    });

    it('returns a separate instance per name with its own settings', function (): void {
        $factory = new RedisConnectionFactory(redisConfig([
            'default' => redisConnectionSettings(database: 0),
            'cache' => redisConnectionSettings(host: 'cache.internal', database: 2),
        ]));

        $default = $factory->connection('default');
        $cache = $factory->connection('cache');

        expect($cache)->not->toBe($default)
            ->and($cache->host)->toBe('cache.internal')
            ->and($cache->database)->toBe(2)
            ->and($default->database)->toBe(0);
    });

    it('treats a null or empty password as no password', function (): void {
        $factory = new RedisConnectionFactory(redisConfig([
            'none' => redisConnectionSettings(password: null),
            'empty' => redisConnectionSettings(password: ''),
        ]));

        expect($factory->connection('none')->password)->toBeNull()
            ->and($factory->connection('empty')->password)->toBeNull();
    });

    it('fails loudly when a connection has no password key', function (): void {
        $settings = redisConnectionSettings();
        unset($settings['password']);

        $factory = new RedisConnectionFactory(redisConfig(['default' => $settings]));

        expect(fn () => $factory->connection('default'))->toThrow(ConfigNotFoundException::class);
    });

    it('throws a loud error listing the configured names for an unknown connection', function (): void {
        $factory = new RedisConnectionFactory(redisConfig([
            'default' => redisConnectionSettings(),
            'cache' => redisConnectionSettings(),
        ]));

        try {
            $factory->connection('queue');
            $this->fail('Expected RedisConnectionException');
        } catch (RedisConnectionException $e) {
            expect($e->getMessage())->toContain("'queue'")
                ->and($e->getContext())->toContain('default, cache')
                ->and($e->getSuggestion())->toContain('config/redis.php');
        }
    });
});
