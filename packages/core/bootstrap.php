<?php

declare(strict_types=1);

use Marko\Core\Application;

/**
 * Marko Framework Bootstrap
 *
 * Legacy entry point for Marko applications.
 * For new projects, use Application::boot($basePath) instead.
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

    $app->initialize();

    return $app;
};
