<?php

declare(strict_types=1);

use Marko\PubSub\PublisherInterface;
use Marko\PubSub\SubscriberInterface;
use Marko\PubSub\PgSql\Driver\PgSqlPublisher;
use Marko\PubSub\PgSql\Driver\PgSqlSubscriber;

return [
    'bindings' => [
        PublisherInterface::class => PgSqlPublisher::class,
        SubscriberInterface::class => PgSqlSubscriber::class,
    ],
];
