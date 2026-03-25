<?php

declare(strict_types=1);

// Pest configuration for monorepo - runs tests from all packages

require dirname(__DIR__) . '/packages/testing/src/Pest/Expectations.php';
require dirname(__DIR__) . '/packages/blog/tests/Pest.php';
