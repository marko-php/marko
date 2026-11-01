<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Scheduler\Command\RunScheduleCommand;
use Marko\Scheduler\Mutex\FileTaskMutex;
use Marko\Scheduler\Schedule;
use Marko\Scheduler\Tests\Fixtures\SchedulerFactory;
use Marko\Testing\Fake\FakeClock;

/**
 * Helper to build the command with a real runner. None of these tasks use
 * withoutOverlapping(), so the mutex directory is never created.
 */
function createRunScheduleCommand(
    Schedule $schedule,
    FakeClock $clock = new FakeClock('2026-10-05 12:00:00'),
): RunScheduleCommand {
    return new RunScheduleCommand(
        SchedulerFactory::runner($schedule, new FileTaskMutex(sys_get_temp_dir() . '/marko-scheduler-unused', $clock)),
        $clock,
    );
}

/**
 * Helper to create output stream for capturing command output.
 *
 * @return array{stream: resource, output: Output}
 */
function createOutputStream(): array
{
    $stream = fopen('php://memory', 'r+');

    return [
        'stream' => $stream,
        'output' => new Output($stream),
    ];
}

/**
 * Helper to get output content from stream.
 *
 * @param resource $stream
 */
function getOutputContent(
    mixed $stream,
): string {
    rewind($stream);

    return stream_get_contents($stream);
}

/**
 * Helper to execute RunScheduleCommand and capture output.
 *
 * @return array{output: string, exitCode: int}
 */
function executeScheduleCommand(
    RunScheduleCommand $command,
): array {
    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'schedule:run']);

    $exitCode = $command->execute($input, $output);
    $result = getOutputContent($stream);

    return ['output' => $result, 'exitCode' => $exitCode];
}

it('creates RunScheduleCommand with schedule dependency', function (): void {
    $reflection = new ReflectionClass(RunScheduleCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();

    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('schedule:run');
});

it('executes due tasks', function (): void {
    $executed = false;
    $schedule = SchedulerFactory::schedule();
    $schedule->call(function () use (&$executed): void {
        $executed = true;
    })->everyMinute()->description('Test task');

    $command = createRunScheduleCommand($schedule);
    ['output' => $output, 'exitCode' => $exitCode] = executeScheduleCommand($command);

    expect($executed)->toBeTrue()
        ->and($output)->toContain('Executed: Test task')
        ->and($output)->toContain('Executed 1 scheduled tasks.')
        ->and($exitCode)->toBe(0);
});

it('skips tasks that are not due at the injected clock time', function (): void {
    $executed = false;
    $schedule = SchedulerFactory::schedule();
    $schedule->call(function () use (&$executed): void {
        $executed = true;
    })->cron('0 0 1 1 *')->description('New Year task');

    $command = createRunScheduleCommand($schedule, new FakeClock('2026-10-05 12:00:00'));
    ['output' => $output] = executeScheduleCommand($command);

    expect($executed)->toBeFalse()
        ->and($output)->toContain('No scheduled tasks are due.');
});

it('runs the tasks due at the injected clock time', function (): void {
    $executed = false;
    $schedule = SchedulerFactory::schedule();
    $schedule->call(function () use (&$executed): void {
        $executed = true;
    })->cron('0 0 1 1 *')->description('New Year task');

    $command = createRunScheduleCommand($schedule, new FakeClock('2027-01-01 00:00:00'));
    ['output' => $output] = executeScheduleCommand($command);

    expect($executed)->toBeTrue()
        ->and($output)->toContain('Executed: New Year task');
});

it('reports executed task count', function (): void {
    $schedule = SchedulerFactory::schedule();
    $schedule->call(fn (): null => null)->everyMinute()->description('Task A');
    $schedule->call(fn (): null => null)->everyMinute()->description('Task B');

    $command = createRunScheduleCommand($schedule);
    ['output' => $output] = executeScheduleCommand($command);

    expect($output)->toContain('Executed: Task A')
        ->and($output)->toContain('Executed: Task B')
        ->and($output)->toContain('Executed 2 scheduled tasks.');
});

it('exits with 1 when a task fails', function (): void {
    $ranAfterFailure = false;
    $schedule = SchedulerFactory::schedule();
    $schedule->call(function (): never {
        throw new RuntimeException('Something went wrong');
    })->everyMinute()->description('Failing task');
    $schedule->call(function () use (&$ranAfterFailure): void {
        $ranAfterFailure = true;
    })->everyMinute()->description('Healthy task');

    $command = createRunScheduleCommand($schedule);
    ['output' => $output, 'exitCode' => $exitCode] = executeScheduleCommand($command);

    expect($output)->toContain('Failed: Failing task - Something went wrong')
        ->and($output)->toContain('Executed: Healthy task')
        ->and($ranAfterFailure)->toBeTrue()
        ->and($exitCode)->toBe(1);
});

it('exits with 0 when every due task succeeds', function (): void {
    $schedule = SchedulerFactory::schedule();
    $schedule->call(fn (): null => null)->everyMinute()->description('Healthy task');

    $command = createRunScheduleCommand($schedule);
    ['exitCode' => $exitCode] = executeScheduleCommand($command);

    expect($exitCode)->toBe(0);
});
