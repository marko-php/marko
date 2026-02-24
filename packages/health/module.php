<?php

declare(strict_types=1);

use Marko\Health\Registry\HealthCheckRegistry;

return [
    'bindings' => [
        HealthCheckRegistry::class => HealthCheckRegistry::class,
    ],
];
