<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\Mutex\FileTaskMutex;
use Marko\Scheduler\Tests\Fixtures\RecordingBackgroundCommandLauncher;
use Marko\Scheduler\Tests\Fixtures\SchedulerFactory;
use Marko\Testing\Fake\FakeClock;

beforeEach(function (): void {
    $this->mutexDirectory = sys_get_temp_dir() . '/marko-scheduler-commands-' . bin2hex(random_bytes(6));
    $this->clock = new FakeClock('2026-10-05 12:00:00');
    $this->mutex = new FileTaskMutex($this->mutexDirectory, $this->clock);
    $this->now = new DateTimeImmutable('2026-10-05 12:00:00');
    $this->stream = fopen('php://memory', 'r+');
    $this->output = new Output($this->stream);
    $this->outputContent = function (): string {
        rewind($this->stream);

        return stream_get_contents($this->stream);
    };
    $this->ran = [];
    [$registry, $commandRunner] = SchedulerFactory::commands([
        'app:prune' => function (Input $input, Output $output): int {
            $this->ran[] = ['app:prune', $input->getOption('days'), $input->getArguments()];
            $output->writeLine('Pruned 3 rows.');

            return 0;
        },
        'app:broken' => function (): int {
            $this->ran[] = ['app:broken'];

            return 2;
        },
        'app:throws' => function (): never {
            throw new RuntimeException('Engine unreachable');
        },
    ]);
    $this->schedule = SchedulerFactory::schedule($registry);
    $this->launcher = new RecordingBackgroundCommandLauncher();
    $this->runner = SchedulerFactory::runner($this->schedule, $this->mutex, $commandRunner, $this->launcher);
});

afterEach(function (): void {
    if (is_dir($this->mutexDirectory)) {
        exec('rm -rf ' . escapeshellarg($this->mutexDirectory));
    }
});

it('runs a command task through the CommandRunner with its arguments and output', function (): void {
    $this->schedule->command('app:prune', ['--days=30', 'users'])->everyMinute();

    $result = $this->runner->run($this->now, $this->output);

    expect($this->ran)->toBe([['app:prune', '30', ['users']]])
        ->and($result->executed)->toBe(1)
        ->and($result->failed)->toBe(0)
        ->and(($this->outputContent)())->toContain("Pruned 3 rows.\nExecuted: app:prune --days=30 users");
});

it('counts a non-zero exit code as a failure and still runs the next task', function (): void {
    $this->schedule->command('app:broken')->everyMinute();
    $this->schedule->command('app:prune')->everyMinute();

    $result = $this->runner->run($this->now, $this->output);

    expect($this->ran)->toBe([['app:broken'], ['app:prune', null, []]])
        ->and($result->failed)->toBe(1)
        ->and($result->executed)->toBe(1)
        ->and($result->hasFailures())->toBeTrue()
        ->and(($this->outputContent)())->toContain("Failed: app:broken - Command 'app:broken' exited with code 2.");
});

it('counts a command that throws as a failure', function (): void {
    $this->schedule->command('app:throws')->everyMinute();

    $result = $this->runner->run($this->now, $this->output);

    expect($result->failed)->toBe(1)
        ->and(($this->outputContent)())->toContain('Failed: app:throws - Engine unreachable');
});

it('releases the overlap mutex of a foreground command task when it finishes', function (): void {
    $task = $this->schedule->command('app:prune')->everyMinute()->withoutOverlapping();

    $this->runner->run($this->now, $this->output);

    expect($this->ran)->toHaveCount(1)
        ->and($this->mutex->exists($task))->toBeFalse();
});

it('hands a background task to the launcher without waiting for it', function (): void {
    $this->schedule->command('app:prune', ['--days=30'])->everyMinute()->runInBackground();

    $result = $this->runner->run($this->now, $this->output);

    expect($this->launcher->launches)->toBe([
        ['command' => 'app:prune', 'arguments' => ['--days=30'], 'mutexName' => null],
    ])
        ->and($this->ran)->toBe([])
        ->and($result->launched)->toBe(1)
        ->and($result->executed)->toBe(0)
        ->and(($this->outputContent)())->toContain('Launched: app:prune --days=30')
        ->and(($this->outputContent)())->toContain('Launched 1 background task.')
        ->and(($this->outputContent)())->not->toContain('Executed: app:prune');
});

it('passes the overlap mutex name to the child instead of taking the mutex itself', function (): void {
    $task = $this->schedule->command('app:prune')->everyMinute()->withoutOverlapping()->runInBackground();

    $this->runner->run($this->now, $this->output);

    expect($this->launcher->launches[0]['mutexName'])->toBe($task->mutexName())
        ->and($this->mutex->exists($task))->toBeFalse();
});

it('launches a background task while an earlier run holds its mutex, so the child decides to skip', function (): void {
    $task = $this->schedule->command('app:prune')->everyMinute()->withoutOverlapping()->runInBackground();
    $earlierRun = new FileTaskMutex($this->mutexDirectory, $this->clock);
    $earlierRun->acquire($task, 3600);

    try {
        $this->runner->run($this->now, $this->output);
    } finally {
        $earlierRun->release($task);
    }

    expect($this->launcher->launches)->toHaveCount(1);
});

it('checks the conditions of a background task before launching it', function (): void {
    $this->schedule->command('app:prune')->everyMinute()->runInBackground()->when(fn (): bool => false);

    $result = $this->runner->run($this->now, $this->output);

    expect($this->launcher->launches)->toBe([])
        ->and($result->skippedByCondition)->toBe(1)
        ->and(($this->outputContent)())->toContain('Skipped (condition): app:prune');
});

it('counts a launch failure as a failure and still runs the next task', function (): void {
    [$registry, $commandRunner] = SchedulerFactory::commands([
        'app:prune' => fn (): int => 0,
        'app:report' => fn (): int => 0,
    ]);
    $schedule = SchedulerFactory::schedule($registry);
    $launcher = new RecordingBackgroundCommandLauncher(
        failFor: ['app:prune'],
        failWith: SchedulerException::backgroundLaunchFailed('app:prune', 'proc_open() failed'),
    );
    $schedule->command('app:prune')->everyMinute()->runInBackground();
    $schedule->command('app:report')->everyMinute()->runInBackground();

    $result = SchedulerFactory::runner($schedule, $this->mutex, $commandRunner, $launcher)->run(
        $this->now,
        $this->output,
    );

    expect($result->failed)->toBe(1)
        ->and($result->launched)->toBe(1)
        ->and($launcher->launches[0]['command'])->toBe('app:report')
        ->and(($this->outputContent)())
        ->toContain(
            "Failed: app:prune - Could not start scheduled command 'app:prune' in the background: proc_open() failed.",
        );
});

it('runs closure tasks unchanged alongside command tasks', function (): void {
    $closureRan = false;
    $this->schedule->call(function () use (&$closureRan): void {
        $closureRan = true;
    })->everyMinute()->description('Closure task');
    $this->schedule->command('app:prune')->everyMinute()->runInBackground();

    $result = $this->runner->run($this->now, $this->output);

    expect($closureRan)->toBeTrue()
        ->and($result->executed)->toBe(1)
        ->and($result->launched)->toBe(1);
});
