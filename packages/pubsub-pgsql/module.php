<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\PubSub\PublisherInterface;
use Marko\PubSub\SubscriberInterface;
use Marko\PubSub\PgSql\Driver\PgSqlPublisher;
use Marko\PubSub\PgSql\Driver\PgSqlSubscriber;
use Marko\PubSub\PgSql\PgSqlPubSubConnection;

return [
    'bindings' => [
        PublisherInterface::class => PgSqlPublisher::class,
        SubscriberInterface::class => PgSqlSubscriber::class,
        PgSqlPubSubConnection::class => static function (ContainerInterface $container): PgSqlPubSubConnection {
            $config = $container->get(ConfigRepositoryInterface::class);

            return new PgSqlPubSubConnection(
                host: $config->getString(key: 'pubsub-pgsql.host'),
                port: $config->getInt(key: 'pubsub-pgsql.port'),
                user: $config->getString(key: 'pubsub-pgsql.user'),
                password: $config->getString(key: 'pubsub-pgsql.password'),
                database: $config->getString(key: 'pubsub-pgsql.database'),
            );
        },
    ],
];
