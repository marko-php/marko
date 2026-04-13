<?php

declare(strict_types=1);

// Pest configuration for monorepo - runs tests from all packages

require_once dirname(__DIR__) . '/packages/testing/src/Pest/Expectations.php';
require_once __DIR__ . '/Support/PackageInventory.php';

foreach (markoPackageDirectories() as $package) {
    $packagePestPath = dirname(__DIR__) . "/packages/{$package}/tests/Pest.php";

    if (is_file($packagePestPath)) {
        require_once $packagePestPath;
    }
}
