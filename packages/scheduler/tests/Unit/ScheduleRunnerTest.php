<?php

declare(strict_types=1);

use Marko\Core\Command\Output;
use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\Mutex\FileTaskMutex;
use Marko\Scheduler\Mutex\TaskMutexInterface;
use Marko\Scheduler\ScheduleRunResult;
use Marko\Scheduler\Tests\Fixtures\SchedulerFactory;
use Marko\Testing\Fake\FakeClock;

beforeEach(function (): void {
    $this->mutexDirectory = sys_get_temp_dir() . '/marko-scheduler-runner-' . bin2hex(random_bytes(6));
    $this->schedule = SchedulerFactory::schedule();
    $this->clock = new FakeClock('2026-10-05 12:00:00');
    $this->mutex = new FileTaskMutex($this->mutexDirectory, $this->clock);
    $this->runner = SchedulerFactory::runner($this->schedule, $this->mutex);
    $this->stream = fopen('php://memory', 'r+');
    $this->output = new Output($this->stream);
    $this->now = new DateTimeImmutable('2026-10-05 12:00:00');
    $this->outputContent = function (): string {
        rewind($this->stream);

        return stream_get_contents($this->stream);
    };
});

afterEach(function (): void {
    if (is_dir($this->mutexDirectory)) {
        exec('rm -rf ' . escapeshellarg($this->mutexDirectory));
    }
});

it('runs every due task and reports the result counts', function (): void {
    $ran = [];
    $this->schedule->call(function () use (&$ran): void {
        $ran[] = 'A';
    })->everyMinute()->description('Task A');
    $this->schedule->call(function () use (&$ran): void {
        $ran[] = 'B';
    })->hourly()->description('Task B');
    $this->schedule->call(function () use (&$ran): void {
        $ran[] = 'C';
    })->cron('30 * * * *')->description('Not due');

    $result = $this->runner->run($this->now, $this->output);

    expect($ran)->toBe(['A', 'B'])
        ->and($result->executed)->toBe(2)
        ->and($result->failed)->toBe(0)
        ->and($result->skipped)->toBe(0)
        ->and($result->hasFailures())->toBeFalse()
        ->and(($this->outputContent)())->toContain('Executed: Task A')
        ->and(($this->outputContent)())->toContain('Executed 2 scheduled tasks.');
});

it('reports when no tasks are due', function (): void {
    $this->schedule->call(fn (): null => null)->cron('30 * * * *');

    $result = $this->runner->run($this->now, $this->output);

    expect($result->executed)->toBe(0)
        ->and(($this->outputContent)())->toContain('No scheduled tasks are due.');
});

it('keeps running remaining due tasks after one fails', function (): void {
    $ranAfterFailure = false;
    $this->schedule->call(function (): never {
        throw new RuntimeException('Boom');
    })->everyMinute()->description('Failing task');
    $this->schedule->call(function () use (&$ranAfterFailure): void {
        $ranAfterFailure = true;
    })->everyMinute()->description('Healthy task');

    $result = $this->runner->run($this->now, $this->output);

    expect($ranAfterFailure)->toBeTrue()
        ->and($result->executed)->toBe(1)
        ->and($result->failed)->toBe(1)
        ->and($result->hasFailures())->toBeTrue()
        ->and(($this->outputContent)())->toContain('Failed: Failing task - Boom')
        ->and(($this->outputContent)())->toContain('1 scheduled task failed.');
});

it('skips an overlapping task whose mutex is held', function (): void {
    $executed = false;
    $task = $this->schedule->call(function () use (&$executed): void {
        $executed = true;
    })->everyMinute()->description('Long import')->withoutOverlapping();
    $otherProcess = new FileTaskMutex($this->mutexDirectory, $this->clock);
    $otherProcess->acquire($task->mutexName(), 3600);

    $result = $this->runner->run($this->now, $this->output);

    expect($executed)->toBeFalse()
        ->and($result->skipped)->toBe(1)
        ->and($result->hasFailures())->toBeFalse()
        ->and(($this->outputContent)())->toContain('Skipped (still running): Long import');
});

it('holds the mutex while an overlapping task runs and releases it afterwards', function (): void {
    $heldDuringRun = null;
    $task = $this->schedule->call(function () use (&$heldDuringRun, &$task): void {
        $heldDuringRun = new FileTaskMutex($this->mutexDirectory, $this->clock)->exists($task->mutexName());
    })->everyMinute()->description('Long import')->withoutOverlapping();

    $this->runner->run($this->now, $this->output);

    expect($heldDuringRun)->toBeTrue()
        ->and($this->mutex->exists($task->mutexName()))->toBeFalse();
});

it('acquires the mutex with the configured expiry in seconds', function (): void {
    $this->schedule->call(fn (): null => null)
        ->everyMinute()
        ->description('Short expiry')
        ->withoutOverlapping(5);
    $recordingMutex = new class () implements TaskMutexInterface
    {
        /** @var array<int> */
        public array $acquired = [];

        public int $released = 0;

        public function acquire(
            string $name,
            int $expiresAfterSeconds,
        ): bool {
            $this->acquired[] = $expiresAfterSeconds;

            return true;
        }

        public function release(
            string $name,
        ): void {
            $this->released++;
        }

        public function exists(
            string $name,
        ): bool {
            return false;
        }
    };

    SchedulerFactory::runner($this->schedule, $recordingMutex)->run($this->now, $this->output);

    expect($recordingMutex->acquired)->toBe([300])
        ->and($recordingMutex->released)->toBe(1);
});

it('releases the mutex when the task throws', function (): void {
    $task = $this->schedule->call(function (): never {
        throw new RuntimeException('Boom');
    })->everyMinute()->description('Failing import')->withoutOverlapping();

    $result = $this->runner->run($this->now, $this->output);

    expect($result->failed)->toBe(1)
        ->and($this->mutex->exists($task->mutexName()))->toBeFalse()
        ->and(new FileTaskMutex($this->mutexDirectory, $this->clock)->acquire($task->mutexName(), 60))->toBeTrue();
});

it('throws before running anything when an overlapping task has no description', function (): void {
    $executed = false;
    $this->schedule->call(function () use (&$executed): void {
        $executed = true;
    })->everyMinute()->description('Innocent task');
    $this->schedule->call(fn (): null => null)->cron('30 * * * *')->withoutOverlapping();

    try {
        $this->runner->run($this->now, $this->output);
        $this->fail('Expected SchedulerException');
    } catch (SchedulerException $e) {
        expect($e->getMessage())->toContain('withoutOverlapping()');
    }

    expect($executed)->toBeFalse();
});

it('skips a task whose when() condition is false and counts it separately', function (): void {
    $executed = false;
    $this->schedule->call(function () use (&$executed): void {
        $executed = true;
    })->everyMinute()->description('Poll engine')->when(fn (): bool => false);

    $result = $this->runner->run($this->now, $this->output);

    expect($executed)->toBeFalse()
        ->and($result->executed)->toBe(0)
        ->and($result->skipped)->toBe(0)
        ->and($result->skippedByCondition)->toBe(1)
        ->and($result->hasFailures())->toBeFalse()
        ->and(($this->outputContent)())->toContain('Skipped (condition): Poll engine');
});

it('skips a task whose skip() condition is true and counts it separately', function (): void {
    $executed = false;
    $this->schedule->call(function () use (&$executed): void {
        $executed = true;
    })->everyMinute()->description('Prune')->skip(fn (): bool => true);

    $result = $this->runner->run($this->now, $this->output);

    expect($executed)->toBeFalse()
        ->and($result->skippedByCondition)->toBe(1)
        ->and(($this->outputContent)())->toContain('Skipped (condition): Prune');
});

it('runs a task whose conditions allow it', function (): void {
    $executed = false;
    $this->schedule->call(function () use (&$executed): void {
        $executed = true;
    })->everyMinute()->description('Poll engine')->when(fn (): bool => true)->skip(fn (): bool => false);

    $result = $this->runner->run($this->now, $this->output);

    expect($executed)->toBeTrue()
        ->and($result->executed)->toBe(1)
        ->and($result->skippedByCondition)->toBe(0);
});

it('does not evaluate conditions of a task that is not due', function (): void {
    $evaluated = false;
    $this->schedule->call(fn (): null => null)->cron('30 * * * *')->when(function () use (&$evaluated): bool {
        $evaluated = true;

        return true;
    });

    $this->runner->run($this->now, $this->output);

    expect($evaluated)->toBeFalse();
});

it('checks conditions before acquiring the overlap mutex', function (): void {
    $this->schedule->call(fn (): null => null)
        ->everyMinute()
        ->description('Conditional import')
        ->withoutOverlapping()
        ->when(fn (): bool => false);
    $recordingMutex = new class () implements TaskMutexInterface
    {
        public int $acquired = 0;

        public function acquire(
            string $name,
            int $expiresAfterSeconds,
        ): bool {
            $this->acquired++;

            return true;
        }

        public function release(
            string $name,
        ): void {}

        public function exists(
            string $name,
        ): bool {
            return false;
        }
    };

    $result = SchedulerFactory::runner($this->schedule, $recordingMutex)->run($this->now, $this->output);

    expect($recordingMutex->acquired)->toBe(0)
        ->and($result->skippedByCondition)->toBe(1);
});

it('counts a throwing condition as a failure and keeps running the next task', function (): void {
    $ranAfterFailure = false;
    $broken = $this->schedule->call(fn (): null => null)
        ->everyMinute()
        ->description('Broken condition')
        ->withoutOverlapping()
        ->when(function (): never {
            throw new RuntimeException('Config missing');
        });
    $this->schedule->call(function () use (&$ranAfterFailure): void {
        $ranAfterFailure = true;
    })->everyMinute()->description('Healthy task');

    $result = $this->runner->run($this->now, $this->output);

    expect($ranAfterFailure)->toBeTrue()
        ->and($result->failed)->toBe(1)
        ->and($result->executed)->toBe(1)
        ->and($result->skippedByCondition)->toBe(0)
        ->and($result->hasFailures())->toBeTrue()
        ->and(($this->outputContent)())->toContain('Failed: Broken condition - Config missing')
        ->and($this->mutex->exists($broken->mutexName()))->toBeFalse();
});

it('counts a condition returning a non-bool as a failure', function (): void {
    $this->schedule->call(fn (): null => null)
        ->everyMinute()
        ->description('Sloppy condition')
        ->when(fn (): int => 1);

    $result = $this->runner->run($this->now, $this->output);

    expect($result->failed)->toBe(1)
        ->and(($this->outputContent)())->toContain('Failed: Sloppy condition - A when() condition');
});

it('defaults skippedByCondition to zero for existing callers', function (): void {
    $result = new ScheduleRunResult(executed: 1, failed: 0, skipped: 2);

    expect($result->skippedByCondition)->toBe(0);
});
