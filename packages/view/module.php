<?php

declare(strict_types=1);

use Marko\View\ModuleTemplateResolver;
use Marko\View\TemplateResolverInterface;

return [
    'enabled' => true,
    'bindings' => [
        TemplateResolverInterface::class => ModuleTemplateResolver::class,
    ],
];
