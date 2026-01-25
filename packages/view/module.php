<?php

declare(strict_types=1);

use Marko\View\ModuleTemplateResolver;
use Marko\View\TemplateResolverInterface;

return [
    'bindings' => [
        TemplateResolverInterface::class => ModuleTemplateResolver::class,
    ],
];
