<?php

declare(strict_types=1);

use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\ErrorsSimple\SimpleErrorHandler;

// Marko-specific configuration for this module.
// Name and version come from composer.json.

return [
    'enabled' => true,
    'bindings' => [
        ErrorHandlerInterface::class => SimpleErrorHandler::class,
    ],
    'boot' => function ($container) {
        // Get the error handler and register it
        $handler = $container->get(ErrorHandlerInterface::class);
        $handler->register();
    },
];
