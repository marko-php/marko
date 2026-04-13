<?php

declare(strict_types=1);

use Marko\Inertia\React\Contracts\InertiaReactPublisherInterface;
use Marko\Inertia\React\InertiaReactPublisher;
use Marko\Inertia\React\InertiaReactViteConfigUpdater;

return [
    'sequence' => [
        'after' => [
            'marko/inertia',
            'marko/vite',
        ],
    ],
    'bindings' => [
        InertiaReactPublisherInterface::class => InertiaReactPublisher::class,
    ],
    'singletons' => [
        InertiaReactPublisher::class,
        InertiaReactViteConfigUpdater::class,
    ],
];
