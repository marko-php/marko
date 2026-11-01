<?php

declare(strict_types=1);

namespace Marko\Testing;

use Marko\Core\Application;
use Throwable;

/**
 * The applications marko/testing booted during the current test.
 *
 * Booting runs every module's boot callback, and some of them change process-wide state: the
 * errors modules push a global error handler and exception handler. marko/testing owns the boot
 * (TestClient::boot(), TestDatabase::boot()), so it owns the teardown too: each booted application
 * is tracked here, and shutdownAll() calls Application::shutdown() on every one after the test,
 * which leaves PHP's handler stacks exactly as the test found them.
 *
 * shutdownAll() runs automatically: from TestCase::tearDown(), and for every Pest test (plain it()
 * tests included) through ShutdownApplicationsPlugin. Applications you boot yourself and wrap with
 * TestClient::forApplication() are yours to shut down.
 */
class BootedApplications
{
    /** @var array<int, Application> Keyed by object id, in boot order */
    private static array $applications = [];

    public static function track(
        Application $application,
    ): void {
        self::$applications[spl_object_id($application)] = $application;
    }

    /**
     * Shut down every tracked application, the most recently booted first (handlers come off
     * PHP's stacks in the reverse order they went on), and forget them.
     *
     * Every application is shut down even when one throws; the first failure is rethrown after.
     *
     * @throws Throwable
     */
    public static function shutdownAll(): void
    {
        $applications = array_reverse(self::$applications);
        self::$applications = [];
        $failure = null;

        foreach ($applications as $application) {
            try {
                $application->shutdown();
            } catch (Throwable $throwable) {
                $failure ??= $throwable;
            }
        }

        if ($failure !== null) {
            throw $failure;
        }
    }
}
