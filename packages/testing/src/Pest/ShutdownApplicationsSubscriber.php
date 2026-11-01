<?php

declare(strict_types=1);

namespace Marko\Testing\Pest;

use Marko\Testing\BootedApplications;
use PHPUnit\Event\Test\AfterTestMethodFinished;
use PHPUnit\Event\Test\AfterTestMethodFinishedSubscriber;
use Throwable;

/**
 * Shuts down the applications a test booted through marko/testing, right after the test's
 * tearDown (where Pest runs its afterEach hooks) and before PHPUnit compares the error and
 * exception handler stacks with the ones the test started with.
 */
class ShutdownApplicationsSubscriber implements AfterTestMethodFinishedSubscriber
{
    /**
     * @throws Throwable
     */
    public function notify(
        AfterTestMethodFinished $event,
    ): void {
        BootedApplications::shutdownAll();
    }
}
