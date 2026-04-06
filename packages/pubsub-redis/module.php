<?php

declare(strict_types=1);

use Marko\PubSub\PublisherInterface;
use Marko\PubSub\Redis\Driver\RedisPublisher;
use Marko\PubSub\Redis\Driver\RedisSubscriber;
use Marko\PubSub\SubscriberInterface;

return [
    'bindings' => [
        PublisherInterface::class => RedisPublisher::class,
        SubscriberInterface::class => RedisSubscriber::class,
    ],
];
