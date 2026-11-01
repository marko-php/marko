<?php

declare(strict_types=1);

use Marko\Core\Sleep\SleeperInterface;
use Marko\Core\Sleep\UsleepSleeper;

it('implements the core sleeper interface', function (): void {
    expect(new UsleepSleeper())->toBeInstanceOf(SleeperInterface::class);
});

it('blocks for at least the requested milliseconds', function (): void {
    $started = hrtime(true);

    new UsleepSleeper()->sleep(20);

    expect((hrtime(true) - $started) / 1_000_000)->toBeGreaterThanOrEqual(20.0);
});

it('returns at once for zero or negative milliseconds', function (): void {
    $sleeper = new UsleepSleeper();
    $started = hrtime(true);

    $sleeper->sleep(0);
    $sleeper->sleep(-1_000);

    expect((hrtime(true) - $started) / 1_000_000)->toBeLessThan(500.0);
});
