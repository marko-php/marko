<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\PageCache\Boot\IdentityBridgeValidator;

// Marko-specific configuration for this module.
// Name and version come from composer.json.

return [
    'boot' => function (
        IdentityBridgeValidator $validator,
        ModuleRepositoryInterface $modules,
    ): void {
        $validator->validate($modules->all());
    },
];
