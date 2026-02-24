<?php

declare(strict_types=1);

use Marko\Notification\Database\Repository\DatabaseNotificationRepository;
use Marko\Notification\Database\Repository\NotificationRepositoryInterface;

return [
    'enabled' => true,
    'bindings' => [
        NotificationRepositoryInterface::class => DatabaseNotificationRepository::class,
    ],
];
