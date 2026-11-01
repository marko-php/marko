<?php

declare(strict_types=1);

namespace Marko\Testing\Pest;

use Pest\Contracts\Plugins\Bootable;
use PHPUnit\Event\Facade;

/**
 * Shuts down, after every Pest test, the applications the test booted through marko/testing.
 *
 * Pest has no global afterEach hook (pest()->afterEach() applies only to the paths passed to
 * in()), so this registers a PHPUnit event subscriber instead. It covers plain it() tests that
 * call TestClient::boot() with nothing in Pest.php, as well as tests extending
 * Marko\Testing\TestCase.
 *
 * Declared in composer.json under extra.pest.plugins, like ExpectationsPlugin. Pest boots it in
 * the main process and in every --parallel worker, before PHPUnit's event facade is sealed.
 */
class ShutdownApplicationsPlugin implements Bootable
{
    public function boot(): void
    {
        Facade::instance()->registerSubscriber(new ShutdownApplicationsSubscriber());
    }
}
