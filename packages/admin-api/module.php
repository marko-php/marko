<?php

declare(strict_types=1);

use Marko\AdminApi\Config\AdminApiConfig;
use Marko\AdminApi\Config\AdminApiConfigInterface;

return [
    'bindings' => [
        AdminApiConfigInterface::class => AdminApiConfig::class,
    ],
];
