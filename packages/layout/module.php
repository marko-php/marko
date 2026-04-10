<?php

declare(strict_types=1);

use Marko\Layout\ComponentCollectorInterface;
use Marko\Layout\DiscoveringComponentCollector;
use Marko\Layout\HandleResolver;
use Marko\Layout\LayoutProcessor;
use Marko\Layout\LayoutProcessorInterface;
use Marko\Layout\LayoutResolver;

return [
    'bindings' => [
        ComponentCollectorInterface::class => DiscoveringComponentCollector::class,
        LayoutProcessorInterface::class => LayoutProcessor::class,
    ],
    'singletons' => [
        HandleResolver::class => HandleResolver::class,
        LayoutResolver::class => LayoutResolver::class,
    ],
];
