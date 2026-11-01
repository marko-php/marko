<?php

declare(strict_types=1);

use Marko\Scheduler\Mutex\FileTaskMutex;
use Marko\Scheduler\Schedule;
use Marko\Scheduler\ScheduledTask;
use Psr\Clock\ClockInterface;

/*
 * The scheduler through the real `schedule:run` command, with the tasks the
 * fixture module registers in its boot callback (#164).
 */

pest()->group('integration-services');

beforeEach(fn () => setUpIntegrationTest($this, migrate: false));

afterEach(fn () => tearDownIntegrationTest($this));

it('runs the task registered in the fixture boot callback with schedule:run', function (): void {
    $result = runIntegrationCommand($this->app, 'schedule:run');

    expect($result['exitCode'])->toBe(0, $result['output'])
        ->and($result['output'])->toContain('Executed: integration heartbeat')
        ->and(file_get_contents($this->project . '/storage/scheduled-task.txt'))->toBe('scheduled task ran');
})->issue(164);

it('skips a scheduled task whose previous run is still in progress', function (): void {
    $tasks = array_values(array_filter(
        $this->app->container->get(Schedule::class)->tasks(),
        fn (ScheduledTask $task): bool => $task->getDescription() === 'integration exclusive',
    ));

    // A second mutex on the same directory stands in for the earlier run,
    // which would be another process holding the lock file.
    $previousRun = new FileTaskMutex(
        directory: $this->project . '/storage/framework',
        clock: $this->app->container->get(ClockInterface::class),
    );

    expect($tasks)->toHaveCount(1)
        ->and($previousRun->acquire($tasks[0], 3600))->toBeTrue();

    try {
        $result = runIntegrationCommand($this->app, 'schedule:run');
    } finally {
        $previousRun->release($tasks[0]);
    }

    expect($result['exitCode'])->toBe(0, $result['output'])
        ->and($result['output'])->toContain('Skipped (still running): integration exclusive')
        ->and($result['output'])->toContain('Executed: integration heartbeat')
        ->and(file_exists($this->project . '/storage/exclusive-task.txt'))->toBeFalse();
})->issue(164);

it('runs a runInBackground() command to completion in a separate process', function (): void {
    // A real install has Composer's vendor/bin/marko; the fixture links it to the CLI package.
    mkdir($this->project . '/vendor/bin', 0777, true);
    symlink($this->project . '/vendor/marko/cli/bin/marko', $this->project . '/vendor/bin/marko');
    touch($this->project . '/storage/run-background-task');

    $result = runIntegrationCommand($this->app, 'schedule:run');

    expect($result['exitCode'])->toBe(0, $result['output'])
        ->and($result['output'])->toContain(
            'Launched: integration:record-background-run --note=ran in the background',
        );

    $record = $this->project . '/storage/background-command.txt';
    $deadline = microtime(true) + 30;

    while (!is_file($record) && microtime(true) < $deadline) {
        usleep(50_000);
        clearstatcache(true, $record);
    }

    expect(is_file($record))->toBeTrue('The background command did not finish within 30 seconds.');

    $written = json_decode((string) file_get_contents($record), true);
    $tasks = array_values(array_filter(
        $this->app->container->get(Schedule::class)->tasks(),
        fn (ScheduledTask $task): bool => $task->runsInBackground(),
    ));
    $mutex = new FileTaskMutex(
        directory: $this->project . '/storage/framework',
        clock: $this->app->container->get(ClockInterface::class),
    );

    // The child took the mutex (its file exists) and released it when the command ended.
    $deadline = microtime(true) + 10;

    while ($mutex->exists($tasks[0]) && microtime(true) < $deadline) {
        usleep(50_000);
    }

    expect($written['note'])->toBe('ran in the background')
        ->and($written['pid'])->not->toBe(getmypid())
        ->and(is_file($this->project . '/storage/framework/' . $tasks[0]->mutexName()))->toBeTrue()
        ->and($mutex->exists($tasks[0]))->toBeFalse();
})->issue(565);
