<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$app = (require __DIR__ . '/../vendor/marko/core/bootstrap.php')(
    vendorPath: __DIR__ . '/../vendor',
    modulesPath: __DIR__ . '/../modules',
    appPath: __DIR__ . '/../app',
);

// Routing will handle requests once marko/routing is built.
// For now, just confirm the application booted successfully.
echo "Application booted.\n";
