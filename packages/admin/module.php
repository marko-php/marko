<?php

declare(strict_types=1);

use Marko\Admin\AdminSectionRegistry;
use Marko\Admin\Config\AdminConfig;
use Marko\Admin\Config\AdminConfigInterface;
use Marko\Admin\Contracts\AdminSectionRegistryInterface;

return [
    'bindings' => [
        AdminSectionRegistryInterface::class => AdminSectionRegistry::class,
        AdminConfigInterface::class => AdminConfig::class,
    ],
];
