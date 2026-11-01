<?php

declare(strict_types=1);

// This fixture is not a real Composer install: every marko/* package it needs
// is already autoloadable through the monorepo root's autoloader, so this file
// delegates to it. Module *discovery* still runs against this fixture's own
// vendor/marko/* stubs; only class autoloading is borrowed.
//
// vendor/ -> http-app/ -> fixtures/ -> tests/ -> testing/ -> packages/ -> root
require dirname(__DIR__, 6) . '/vendor/autoload.php';
