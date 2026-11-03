<?php

declare(strict_types=1);

namespace Marko\PubSub\Redis\Tests;

use Amp\Redis\RedisConfig;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Container\Container;
use Marko\PubSub\Redis\Driver\RedisSubscriber;
use Marko\PubSub\Redis\RedisPubSubConnection;
use Marko\PubSub\SubscriberInterface;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * @param list<string> $without Config keys to leave out
 */
function createPubSubRedisContainer(
    array $without = [],
): Container {
    $config = [
        'pubsub.prefix' => 'app:',
        'pubsub-redis.host' => 'redis.internal',
        'pubsub-redis.port' => 6380,
        'pubsub-redis.password' => 'secret',
        'pubsub-redis.database' => 4,
        'pubsub-redis.scheme' => 'tls',
    ];

    foreach ($without as $key) {
        unset($config[$key]);
    }

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository($config));

    $module = require dirname(__DIR__) . '/module.php';

    foreach ($module['bindings'] as $id => $implementation) {
        $container->bind($id, $implementation);
    }

    foreach ($module['singletons'] ?? [] as $id) {
        $container->singleton($id);
    }

    return $container;
}

describe('pubsub-redis module bindings', function (): void {
    it('resolves RedisPubSubConnection with values from pubsub-redis config', function (): void {
        $connection = createPubSubRedisContainer()->get(RedisPubSubConnection::class);

        expect($connection)->toBeInstanceOf(RedisPubSubConnection::class)
            ->and($connection->host)->toBe('redis.internal')
            ->and($connection->port)->toBe(6380)
            ->and($connection->password)->toBe('secret')
            ->and($connection->database)->toBe(4)
            ->and($connection->scheme)->toBe('tls');
    });

    it('ships tcp as the default pubsub-redis.scheme', function (): void {
        $config = require dirname(__DIR__) . '/config/pubsub-redis.php';

        expect($config['scheme'])->toBe('tcp');
    });

    it('fails loudly when pubsub-redis.password is missing from config', function (): void {
        $container = createPubSubRedisContainer(without: ['pubsub-redis.password']);

        expect(fn () => $container->get(RedisPubSubConnection::class))
            ->toThrow(ConfigNotFoundException::class);
    });

    it('takes the connection prefix from pubsub.prefix', function (): void {
        $connection = createPubSubRedisContainer()->get(RedisPubSubConnection::class);

        expect($connection->prefix)->toBe('app:');
    });

    it('resolves the same RedisPubSubConnection instance twice', function (): void {
        $container = createPubSubRedisContainer();

        expect($container->get(RedisPubSubConnection::class))
            ->toBe($container->get(RedisPubSubConnection::class));
    });

    it('resolves SubscriberInterface to RedisSubscriber', function (): void {
        expect(createPubSubRedisContainer()->get(SubscriberInterface::class))
            ->toBeInstanceOf(RedisSubscriber::class);
    });

    it('resolves the same SubscriberInterface instance twice', function (): void {
        $container = createPubSubRedisContainer();

        // One subscriber per process means one Redis connection for every subscription
        expect($container->get(SubscriberInterface::class))
            ->toBe($container->get(SubscriberInterface::class));
    });

    it('builds a redis config carrying host, port, password and database', function (): void {
        $connection = new class () extends RedisPubSubConnection
        {
            public function __construct()
            {
                parent::__construct(host: 'redis.internal', port: 6380, password: 'secret', database: 4);
            }

            public function exposedRedisConfig(): RedisConfig
            {
                return $this->redisConfig();
            }
        };

        $config = $connection->exposedRedisConfig();

        expect($config->getConnectUri())->toBe('tcp://redis.internal:6380')
            ->and($config->getPassword())->toBe('secret')
            ->and($config->getDatabase())->toBe(4);
    });
});
