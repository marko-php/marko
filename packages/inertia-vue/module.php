<?php

declare(strict_types=1);

use Marko\Inertia\Vue\Contracts\InertiaVuePublisherInterface;
use Marko\Inertia\Vue\InertiaVuePublisher;
use Marko\Inertia\Vue\InertiaVueViteConfigUpdater;

return [
    'sequence' => [
        'after' => [
            'marko/inertia',
            'marko/vite',
        ],
    ],
    'bindings' => [
        InertiaVuePublisherInterface::class => InertiaVuePublisher::class,
    ],
    'singletons' => [
        InertiaVuePublisher::class,
        InertiaVueViteConfigUpdater::class,
    ],
];
