<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Scheduler\Command\ScheduleWorkCommand;
use Marko\Scheduler\Mutex\FileTaskMutex;
use Marko\Scheduler\Tests\Fixtures\SchedulerFactory;
use Marko\Testing\Fake\FakeClock;

beforeEach(function (): void {
    $this->schedule = SchedulerFactory::schedule();
    $this->clock = new FakeClock('2026-10-05 12:00:30');
    $this->sleeps = [];
    $this->onSleep = null;
    $this->command = new ScheduleWorkCommand(
        SchedulerFactory::runner(
            $this->schedule,
            new FileTaskMutex(sys_get_temp_dir() . '/marko-scheduler-unused', $this->clock),
        ),
        $this->clock,
        function (int $seconds): void {
            $this->sleeps[] = $seconds;
            $this->clock->travel("+$seconds seconds");

            if ($this->onSleep !== null) {
                ($this->onSleep)();
            }
        },
    );
    $this->stream = fopen('php://memory', 'r+');
    $this->work = function (): int {
        return $this->command->execute(new Input(['marko', 'schedule:work']), new Output($this->stream));
    };
    $this->outputContent = function (): string {
        rewind($this->stream);

        return stream_get_contents($this->stream);
    };
});

it('registers the schedule:work command', function (): void {
    $reflection = new ReflectionClass(ScheduleWorkCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue()
        ->and($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('schedule:work');
});

it('sleeps until the next minute boundary before running', function (): void {
    $executed = false;
    $this->schedule->call(function () use (&$executed): void {
        $executed = true;
    })->everyMinute();
    $this->clock->setNow('2026-10-05 12:00:45');
    $this->onSleep = fn () => $this->command->stop();

    $exitCode = ($this->work)();

    expect($this->sleeps)->toBe([15])
        ->and($executed)->toBeFalse()
        ->and($exitCode)->toBe(0);
});

it('runs due tasks once per minute boundary', function (): void {
    $runTimes = [];
    $this->schedule->call(function () use (&$runTimes): void {
        $runTimes[] = $this->clock->now()->format('H:i:s');

        if (count($runTimes) === 3) {
            $this->command->stop();
        }
    })->everyMinute()->description('Tick');

    ($this->work)();

    expect($runTimes)->toBe(['12:01:00', '12:02:00', '12:03:00'])
        ->and($this->sleeps)->toBe([30, 60, 60]);
});

it('only runs tasks that are due at each minute boundary', function (): void {
    $ran = [];
    $this->schedule->call(function () use (&$ran): void {
        $ran[] = 'every';

        if (count($ran) >= 4) {
            $this->command->stop();
        }
    })->everyMinute()->description('Every minute');
    $this->schedule->call(function () use (&$ran): void {
        $ran[] = 'at-two';
    })->cron('2 12 * * *')->description('At 12:02');

    ($this->work)();

    expect($ran)->toBe(['every', 'every', 'at-two', 'every']);
});

it('waits for the following boundary when a run overruns the minute', function (): void {
    $runs = 0;
    $this->schedule->call(function () use (&$runs): void {
        $runs++;
        $this->clock->travel('+90 seconds');

        if ($runs === 2) {
            $this->command->stop();
        }
    })->everyMinute()->description('Slow task');

    ($this->work)();

    expect($this->sleeps)->toBe([30, 30]);
});

it('keeps working after a task fails', function (): void {
    $attempts = 0;
    $this->schedule->call(function () use (&$attempts): never {
        $attempts++;

        if ($attempts === 2) {
            $this->command->stop();
        }

        throw new RuntimeException('Boom');
    })->everyMinute()->description('Failing task');

    $exitCode = ($this->work)();

    expect($attempts)->toBe(2)
        ->and(substr_count(($this->outputContent)(), 'Failed: Failing task - Boom'))->toBe(2)
        ->and($exitCode)->toBe(0);
});

it('stops when stop is called', function (): void {
    $this->onSleep = fn () => $this->command->stop();

    $exitCode = ($this->work)();

    expect($exitCode)->toBe(0)
        ->and(($this->outputContent)())->toContain('Scheduler stopped.');
});
