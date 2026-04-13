<?php

declare(strict_types=1);

use Marko\Inertia\Svelte\Contracts\InertiaSveltePublisherInterface;
use Marko\Inertia\Svelte\InertiaSveltePublisher;
use Marko\Inertia\Svelte\InertiaSvelteViteConfigUpdater;

return [
    'sequence' => [
        'after' => [
            'marko/inertia',
            'marko/vite',
        ],
    ],
    'bindings' => [
        InertiaSveltePublisherInterface::class => InertiaSveltePublisher::class,
    ],
    'singletons' => [
        InertiaSveltePublisher::class,
        InertiaSvelteViteConfigUpdater::class,
    ],
];
