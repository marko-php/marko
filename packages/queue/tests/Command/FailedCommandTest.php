<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Queue\Command\FailedCommand;
use Marko\Queue\FailedJob;
use Marko\Queue\Tests\Command\Helpers;

/**
 * Execute FailedCommand and return output.
 *
 * @return array{output: string, exitCode: int}
 */
function executeFailedCommand(
    FailedCommand $command,
): array {
    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'queue:failed']);

    $exitCode = $command->execute($input, $output);
    $result = Helpers::getOutputContent($stream);

    return ['output' => $result, 'exitCode' => $exitCode];
}

it('registers as queue:failed command via #[Command] attribute', function (): void {
    $reflection = new ReflectionClass(FailedCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('queue:failed');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(FailedCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('lists failed jobs', function (): void {
    $failedJobs = [
        new FailedJob(
            id: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            queue: 'default',
            payload: serialize(['class' => 'App\\Jobs\\SendEmail', 'data' => []]),
            exception: 'Connection timed out',
            failedAt: new DateTimeImmutable('2026-01-21 09:45:00'),
        ),
    ];

    $repository = Helpers::createStubFailedJobRepository($failedJobs);
    $command = new FailedCommand($repository);
    ['output' => $output] = executeFailedCommand($command);

    expect($output)->toContain('a1b2c3d4-e5f6-7890-abcd-ef1234567890')
        ->and($output)->toContain('default');
});

it('shows job details', function (): void {
    $failedJobs = [
        new FailedJob(
            id: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            queue: 'default',
            payload: serialize(['class' => 'App\\Jobs\\SendEmail', 'data' => []]),
            exception: 'Connection timed out',
            failedAt: new DateTimeImmutable('2026-01-21 09:45:00'),
        ),
    ];

    $repository = Helpers::createStubFailedJobRepository($failedJobs);
    $command = new FailedCommand($repository);
    ['output' => $output] = executeFailedCommand($command);

    expect($output)->toContain('App\\Jobs\\SendEmail')
        ->and($output)->toContain('2026-01-21 09:45:00');
});

it('shows total count', function (): void {
    $failedJobs = [
        new FailedJob(
            id: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
            queue: 'default',
            payload: serialize(['class' => 'App\\Jobs\\SendEmail', 'data' => []]),
            exception: 'Connection timed out',
            failedAt: new DateTimeImmutable('2026-01-21 09:45:00'),
        ),
    ];

    $repository = Helpers::createStubFailedJobRepository($failedJobs);
    $command = new FailedCommand($repository);
    ['output' => $output] = executeFailedCommand($command);

    expect($output)->toContain('Total: 1 failed job');
});

it('handles empty list', function (): void {
    $repository = Helpers::createStubFailedJobRepository();
    $command = new FailedCommand($repository);
    ['output' => $output] = executeFailedCommand($command);

    expect($output)->toContain('No failed jobs');
});
