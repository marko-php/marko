<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Mail\Contracts\MailerInterface;
use Marko\Notification\Channel\DatabaseChannel;
use Marko\Notification\Channel\MailChannel;
use Marko\Notification\Config\NotificationConfig;
use Marko\Notification\NotificationManager;
use Marko\Notification\NotificationSender;

return [
    'enabled' => true,
    'bindings' => [
        NotificationConfig::class => NotificationConfig::class,
        NotificationManager::class => NotificationManager::class,
        NotificationSender::class => NotificationSender::class,
    ],
    'boot' => function (ContainerInterface $container) {
        $manager = $container->get(NotificationManager::class);

        // Register mail channel if mailer is available
        if ($container->has(MailerInterface::class)) {
            $manager->register('mail', $container->get(MailChannel::class));
        }

        // Register database channel if connection is available
        if ($container->has(ConnectionInterface::class)) {
            $manager->register('database', $container->get(DatabaseChannel::class));
        }
    },
];
