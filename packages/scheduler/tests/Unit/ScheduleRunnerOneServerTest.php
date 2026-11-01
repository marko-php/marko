<?php

declare(strict_types=1);

use Marko\Cache\Lock\LockScope;
use Marko\Core\Command\Output;
use Marko\Core\Container\Container;
use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\Mutex\DistributedLockResolver;
use Marko\Scheduler\Mutex\FileTaskMutex;
use Marko\Scheduler\Mutex\LockTaskServerClaim;
use Marko\Scheduler\Schedule;
use Marko\Scheduler\ScheduleRunner;
use Marko\Scheduler\Tests\Fixtures\RecordingBackgroundCommandLauncher;
use Marko\Scheduler\Tests\Fixtures\SchedulerFactory;
use Marko\Scheduler\Tests\Fixtures\SharedLockStore;
use Marko\Testing\Fake\FakeClock;

beforeEach(function (): void {
    $this->mutexDirectory = sys_get_temp_dir() . '/marko-scheduler-one-server-' . bin2hex(random_bytes(6));
    $this->clock = new FakeClock('2026-10-05 12:00:00');
    $this->store = new SharedLockStore($this->clock);
    $this->stream = fopen('php://memory', 'r+');
    $this->output = new Output($this->stream);
    $this->outputContent = function (): string {
        rewind($this->stream);

        return stream_get_contents($this->stream);
    };
    // One "server": its own schedule, mutex and runner, sharing only the lock store.
    $this->server = function (Schedule $schedule, ?SharedLockStore $store = null): ScheduleRunner {
        return SchedulerFactory::runner(
            $schedule,
            new FileTaskMutex($this->mutexDirectory . '/' . bin2hex(random_bytes(4)), $this->clock),
            taskServerClaim: new LockTaskServerClaim(
                SchedulerFactory::lockResolver($store ?? $this->store, $this->clock),
            ),
        );
    };
});

afterEach(function (): void {
    if (is_dir($this->mutexDirectory)) {
        exec('rm -rf ' . escapeshellarg($this->mutexDirectory));
    }
});

it('runs an onOneServer task on one of two servers racing for the same minute', function (): void {
    $runs = 0;
    $schedules = [SchedulerFactory::schedule(), SchedulerFactory::schedule()];

    foreach ($schedules as $schedule) {
        $schedule->call(function () use (&$runs): void {
            $runs++;
        })->everyMinute()->description('Send digest')->onOneServer();
    }

    $now = new DateTimeImmutable('2026-10-05 12:00:00');
    $first = ($this->server)($schedules[0])->run($now, $this->output);
    $second = ($this->server)($schedules[1])->run($now->modify('+20 seconds'), $this->output);

    expect($runs)->toBe(1)
        ->and($first->executed)->toBe(1)
        ->and($first->skippedByServer)->toBe(0)
        ->and($second->executed)->toBe(0)
        ->and($second->skippedByServer)->toBe(1)
        ->and(($this->outputContent)())->toContain('Skipped (another server): Send digest');
});

it('lets the next due minute be claimed again', function (): void {
    $runs = 0;
    $schedule = SchedulerFactory::schedule();
    $schedule->call(function () use (&$runs): void {
        $runs++;
    })->everyMinute()->description('Send digest')->onOneServer();
    $runner = ($this->server)($schedule);

    $runner->run(new DateTimeImmutable('2026-10-05 12:00:00'), $this->output);
    $repeat = $runner->run(new DateTimeImmutable('2026-10-05 12:00:30'), $this->output);
    $next = $runner->run(new DateTimeImmutable('2026-10-05 12:01:00'), $this->output);

    expect($runs)->toBe(2)
        ->and($repeat->skippedByServer)->toBe(1)
        ->and($next->executed)->toBe(1);
});

it('keeps the claim after the run so a slower server cannot take the same minute', function (): void {
    $schedule = SchedulerFactory::schedule();
    $schedule->call(fn (): null => null)->everyMinute()->description('Send digest')->onOneServer();

    ($this->server)($schedule)->run(new DateTimeImmutable('2026-10-05 12:00:00'), $this->output);

    expect($this->store->heldNames())->toHaveCount(1)
        ->and($this->store->heldNames()[0])->toEndWith(':202610051200');
});

it('keys the claim on the due minute in UTC', function (): void {
    $schedule = SchedulerFactory::schedule();
    $schedule->call(fn (): null => null)->everyMinute()->description('Send digest')->onOneServer();

    ($this->server)($schedule)->run(
        new DateTimeImmutable('2026-10-05 08:00:45', new DateTimeZone('America/New_York')),
        $this->output,
    );

    expect($this->store->heldNames()[0])->toEndWith(':202610051200');
});

it('does not claim a task whose conditions skip it', function (): void {
    $schedule = SchedulerFactory::schedule();
    $schedule->call(fn (): null => null)->everyMinute()->description('Send digest')
        ->when(fn (): bool => false)->onOneServer();

    $result = ($this->server)($schedule)->run(new DateTimeImmutable('2026-10-05 12:00:00'), $this->output);

    expect($result->skippedByCondition)->toBe(1)
        ->and($result->skippedByServer)->toBe(0)
        ->and($this->store->heldNames())->toBe([]);
});

it('does not launch a background task another server claimed', function (): void {
    [$registry, $commandRunner] = SchedulerFactory::commands(['app:prune' => fn (): int => 0]);
    $launchers = [new RecordingBackgroundCommandLauncher(), new RecordingBackgroundCommandLauncher()];
    $results = [];

    foreach ($launchers as $launcher) {
        $schedule = SchedulerFactory::schedule($registry);
        $schedule->command('app:prune')->everyMinute()->runInBackground()->onOneServer();
        $results[] = SchedulerFactory::runner(
            $schedule,
            new FileTaskMutex($this->mutexDirectory, $this->clock),
            $commandRunner,
            $launcher,
            new LockTaskServerClaim(SchedulerFactory::lockResolver($this->store, $this->clock)),
        )->run(new DateTimeImmutable('2026-10-05 12:00:00'), $this->output);
    }

    expect($launchers[0]->launches)->toHaveCount(1)
        ->and($launchers[1]->launches)->toBe([])
        ->and($results[0]->launched)->toBe(1)
        ->and($results[1]->skippedByServer)->toBe(1);
});

it('throws before any task runs when an onOneServer task has no description', function (): void {
    $ran = false;
    $schedule = SchedulerFactory::schedule();
    $schedule->call(function () use (&$ran): void {
        $ran = true;
    })->everyMinute()->description('Runs first');
    $schedule->call(fn (): null => null)->cron('30 * * * *')->onOneServer();

    expect(fn () => ($this->server)($schedule)->run(new DateTimeImmutable('2026-10-05 12:00:00'), $this->output))
        ->toThrow(SchedulerException::class, "with expression '30 * * * *' uses onOneServer() but has no description")
        ->and($ran)->toBeFalse();
});

it('throws up front when no lock factory can be resolved', function (): void {
    $ran = false;
    $schedule = SchedulerFactory::schedule();
    $schedule->call(function () use (&$ran): void {
        $ran = true;
    })->everyMinute()->description('Runs first');
    $schedule->call(fn (): null => null)->cron('30 * * * *')->description('Nightly report')->onOneServer();
    $runner = SchedulerFactory::runner(
        $schedule,
        new FileTaskMutex($this->mutexDirectory, $this->clock),
        taskServerClaim: new LockTaskServerClaim(new DistributedLockResolver(new Container())),
    );

    expect(fn () => $runner->run(new DateTimeImmutable('2026-10-05 12:00:00'), $this->output))
        ->toThrow(
            SchedulerException::class,
            "Scheduled task 'Nightly report' uses onOneServer(), but no cache lock store can be resolved.",
        )
        ->and($ran)->toBeFalse();
});

it('throws up front when the lock store only excludes one host or process', function (LockScope $scope): void {
    $ran = false;
    $schedule = SchedulerFactory::schedule();
    $schedule->call(function () use (&$ran): void {
        $ran = true;
    })->everyMinute()->description('Nightly report')->onOneServer();
    $runner = ($this->server)($schedule, new SharedLockStore($this->clock, $scope));

    expect(fn () => $runner->run(new DateTimeImmutable('2026-10-05 12:00:00'), $this->output))
        ->toThrow(SchedulerException::class, "only excludes callers within one $scope->value")
        ->and($ran)->toBeFalse();
})->with([
    'host' => [LockScope::Host],
    'process' => [LockScope::Process],
]);

it('does not touch the lock store when no task uses onOneServer', function (): void {
    $schedule = SchedulerFactory::schedule();
    $schedule->call(fn (): null => null)->everyMinute()->description('Plain task');
    $runner = SchedulerFactory::runner(
        $schedule,
        new FileTaskMutex($this->mutexDirectory, $this->clock),
        taskServerClaim: new LockTaskServerClaim(new DistributedLockResolver(new Container())),
    );

    $result = $runner->run(new DateTimeImmutable('2026-10-05 12:00:00'), $this->output);

    expect($result->executed)->toBe(1);
});
