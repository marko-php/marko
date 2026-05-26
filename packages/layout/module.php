<?php

declare(strict_types=1);

use Marko\Layout\ComponentCollectorInterface;
use Marko\Layout\DiscoveringComponentCollector;
use Marko\Layout\HandleResolver;
use Marko\Layout\LayoutProcessor;
use Marko\Layout\LayoutProcessorInterface;
use Marko\Layout\LayoutResolver;
use Marko\Layout\Middleware\LayoutMiddleware;

return [
    'sequence' => [
        // Layout middleware reads session state, so session must run first.
        // Soft ordering — only enforced when marko/session is also installed.
        'after' => ['marko/session'],
    ],
    'bindings' => [
        ComponentCollectorInterface::class => DiscoveringComponentCollector::class,
        LayoutProcessorInterface::class => LayoutProcessor::class,
    ],
    'singletons' => [
        HandleResolver::class => HandleResolver::class,
        LayoutResolver::class => LayoutResolver::class,
    ],
    'globalMiddleware' => [
        LayoutMiddleware::class,
    ],
];
