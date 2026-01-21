<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseConfig;

// Marko-specific configuration for this module.
// Name and version come from composer.json.

return [
    'enabled' => true,
    'bindings' => [
        DatabaseConfig::class => DatabaseConfig::class,
    ],
];
