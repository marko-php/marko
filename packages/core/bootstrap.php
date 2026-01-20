<?php

declare(strict_types=1);

use Marko\Core\Application;

/**
 * Marko Framework Bootstrap
 *
 * This is the single entry point for Marko applications.
 * It creates and boots the Application instance with the provided paths.
 *
 * Usage:
 *   $app = (require 'vendor/marko/core/bootstrap.php')(
 *       vendorPath: __DIR__ . '/vendor',
 *       modulesPath: __DIR__ . '/modules',
 *       appPath: __DIR__ . '/app',
 *   );
 */
return function (
    string $vendorPath,
    string $modulesPath,
    string $appPath,
): Application {
    $app = new Application(
        vendorPath: $vendorPath,
        modulesPath: $modulesPath,
        appPath: $appPath,
    );

    $app->boot();

    return $app;
};
