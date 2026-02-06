<?php

declare(strict_types=1);

use Marko\AdminPanel\Config\AdminPanelConfig;
use Marko\AdminPanel\Config\AdminPanelConfigInterface;

return [
    'bindings' => [
        AdminPanelConfigInterface::class => AdminPanelConfig::class,
    ],
];
