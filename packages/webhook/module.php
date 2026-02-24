<?php

declare(strict_types=1);

use Marko\Webhook\Contracts\WebhookDispatcherInterface;
use Marko\Webhook\Sending\WebhookDispatcher;

return [
    'bindings' => [
        WebhookDispatcherInterface::class => WebhookDispatcher::class,
    ],
];
