<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\Env\EnvLoader;

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
    // Load environment variables if marko/env is installed
    if (class_exists(EnvLoader::class)) {
        (new EnvLoader())->load(dirname($vendorPath));
    }

    $app = new Application(
        vendorPath: $vendorPath,
        modulesPath: $modulesPath,
        appPath: $appPath,
    );

    $app->boot();

    return $app;
};
