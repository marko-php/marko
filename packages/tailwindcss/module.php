<?php

declare(strict_types=1);

use Marko\TailwindCss\ContentPathCollector;
use Marko\TailwindCss\Contracts\ContentPathProviderInterface;
use Marko\TailwindCss\Contracts\TailwindEntrypointProviderInterface;
use Marko\TailwindCss\Contracts\TailwindPublisherInterface;
use Marko\TailwindCss\DefaultContentPathProvider;
use Marko\TailwindCss\DefaultTailwindEntrypointProvider;
use Marko\TailwindCss\TailwindAssetRegistry;
use Marko\TailwindCss\TailwindPublisher;
use Marko\TailwindCss\TailwindViteConfigUpdater;

return [
    'sequence' => [
        'after' => [
            'marko/vite',
        ],
    ],
    'bindings' => [
        ContentPathProviderInterface::class => DefaultContentPathProvider::class,
        TailwindEntrypointProviderInterface::class => DefaultTailwindEntrypointProvider::class,
        TailwindPublisherInterface::class => TailwindPublisher::class,
    ],
    'singletons' => [
        DefaultContentPathProvider::class,
        ContentPathCollector::class,
        DefaultTailwindEntrypointProvider::class,
        TailwindPublisher::class,
        TailwindAssetRegistry::class,
        TailwindViteConfigUpdater::class,
    ],
];
