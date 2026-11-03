<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\PubSub\PublisherInterface;
use Marko\PubSub\Redis\Driver\RedisPublisher;
use Marko\PubSub\Redis\Driver\RedisSubscriber;
use Marko\PubSub\Redis\RedisPubSubConnection;
use Marko\PubSub\SubscriberInterface;

return [
    'bindings' => [
        PublisherInterface::class => RedisPublisher::class,
        SubscriberInterface::class => RedisSubscriber::class,
        RedisPubSubConnection::class => static function (ContainerInterface $container): RedisPubSubConnection {
            $config = $container->get(ConfigRepositoryInterface::class);
            $password = $config->get(key: 'pubsub-redis.password');

            return new RedisPubSubConnection(
                host: $config->getString(key: 'pubsub-redis.host'),
                port: $config->getInt(key: 'pubsub-redis.port'),
                password: $password === null || $password === '' ? null : (string) $password,
                database: $config->getInt(key: 'pubsub-redis.database'),
                prefix: $config->getString(key: 'pubsub.prefix'),
                scheme: $config->getString(key: 'pubsub-redis.scheme'),
            );
        },
    ],
    'singletons' => [
        RedisPubSubConnection::class,
        // One subscriber per process: all of its subscriptions share one Redis connection
        SubscriberInterface::class,
    ],
];
