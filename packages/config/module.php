<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;

return [
    'enabled' => true,
    'bindings' => [
        ConfigRepositoryInterface::class => ConfigRepository::class,
    ],
];
