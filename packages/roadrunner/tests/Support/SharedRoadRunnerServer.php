<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Support;

use Random\RandomException;
use RuntimeException;

/**
 * Lazily starts one real `rr serve` process the first time an end-to-end
 * test needs it, and reuses it for every other test in the same file — the
 * whole point of the suite is many sequential, even interleaved, requests
 * landing on the same single worker, so tests deliberately share one server
 * rather than each paying to start and stop their own.
 *
 * Never started at all when every test in the file is skipped (no binary),
 * so a plain `composer test` run pays nothing for this class existing.
 */
class SharedRoadRunnerServer
{
    private static ?RoadRunnerServerProcess $instance = null;

    private function __construct() {}

    /**
     * @throws RandomException|RuntimeException
     */
    public static function get(
        string $binary,
        string $basePath,
    ): RoadRunnerServerProcess {
        if (self::$instance === null) {
            self::$instance = new RoadRunnerServerProcess($binary, $basePath);
            self::$instance->start();
        }

        return self::$instance;
    }

    public static function stopIfStarted(): void
    {
        self::$instance?->stop();
        self::$instance = null;
    }
}
