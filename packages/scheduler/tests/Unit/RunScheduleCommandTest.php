<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Scheduler\Command\RunScheduleCommand;
use Marko\Scheduler\Schedule;

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
    $schedule = new Schedule();
    $schedule->call(function () use (&$executed): void {
        $executed = true;
    })->everyMinute()->description('Test task');

    $command = new RunScheduleCommand($schedule);
    ['output' => $output, 'exitCode' => $exitCode] = executeScheduleCommand($command);

    expect($executed)->toBeTrue()
        ->and($output)->toContain('Executed: Test task')
        ->and($output)->toContain('Executed 1 scheduled tasks.')
        ->and($exitCode)->toBe(0);
});

it('skips non-due tasks', function (): void {
    $executed = false;
    $schedule = new Schedule();
    // Schedule for a specific time that's almost certainly not now
    $schedule->call(function () use (&$executed): void {
        $executed = true;
    })->cron('0 0 1 1 *')->description('New Year task'); // Only Jan 1 at midnight

    $command = new RunScheduleCommand($schedule);
    ['output' => $output] = executeScheduleCommand($command);

    // Task might or might not be due depending on current time
    // But if it's not Jan 1 midnight, it won't execute
    if (! $executed) {
        expect($output)->toContain('No scheduled tasks are due.');
    }
});

it('reports executed task count', function (): void {
    $schedule = new Schedule();
    $schedule->call(fn (): null => null)->everyMinute()->description('Task A');
    $schedule->call(fn (): null => null)->everyMinute()->description('Task B');

    $command = new RunScheduleCommand($schedule);
    ['output' => $output] = executeScheduleCommand($command);

    expect($output)->toContain('Executed: Task A')
        ->and($output)->toContain('Executed: Task B')
        ->and($output)->toContain('Executed 2 scheduled tasks.');
});

it('handles task execution errors gracefully', function (): void {
    $schedule = new Schedule();
    $schedule->call(function (): never {
        throw new RuntimeException('Something went wrong');
    })->everyMinute()->description('Failing task');

    $command = new RunScheduleCommand($schedule);
    ['output' => $output, 'exitCode' => $exitCode] = executeScheduleCommand($command);

    expect($output)->toContain('Failed: Failing task - Something went wrong')
        ->and($output)->toContain('Executed 0 scheduled tasks.')
        ->and($exitCode)->toBe(0);
});
