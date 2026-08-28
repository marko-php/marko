<?php

declare(strict_types=1);

// This fixture is not a real Composer install: every marko/* package it
// needs is already autoloadable through the monorepo root's own autoloader
// (a Composer path repository wiring every packages/* directory as
// vendor/marko/*), so this file delegates to that autoloader instead of
// duplicating a real vendor/ install. Module *discovery* still runs against
// this fixture's own vendor/marko/* stubs below — only class autoloading is
// borrowed, matching ModuleAutoloader's documented behaviour that vendor
// modules rely on Composer (not the framework) to autoload their classes.
//
// vendor/ -> app/ -> Fixtures/ -> tests/ -> roadrunner/ -> packages/ -> root
require dirname(__DIR__, 6) . '/vendor/autoload.php';
