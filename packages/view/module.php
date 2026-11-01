<?php

declare(strict_types=1);

use Marko\View\Discovery\DiscoveredViewProviders;
use Marko\View\Discovery\ViewProviderCacheContributor;
use Marko\View\ModuleTemplateResolver;
use Marko\View\TemplateFunctionRegistry;
use Marko\View\TemplateResolverInterface;
use Marko\View\ViewDataMerger;

return [
    'bindings' => [
        TemplateResolverInterface::class => ModuleTemplateResolver::class,
    ],
    'singletons' => [
        // Shared: provider classes are discovered (or read from the cache) once per process.
        DiscoveredViewProviders::class,
        // Shared: the function list is built once and reused by the engine adapter.
        TemplateFunctionRegistry::class,
        // Shared: provider instances are resolved once; their data() runs on every render.
        ViewDataMerger::class,
    ],
    'discovery' => [
        ViewProviderCacheContributor::class,
    ],
];
