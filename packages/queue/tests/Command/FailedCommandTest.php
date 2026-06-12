<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\Command\FailedCommand;
use Marko\Queue\FailedJob;
use Marko\Queue\JobEnvelope;
use Marko\Queue\Tests\Command\Helpers;
use Marko\Testing\Fake\FakeConfigRepository;

function createFailedCommandEnvelope(
    string $key = 'test-key-for-failed-command',
): JobEnvelope {
    return new JobEnvelope(new EncryptionConfig(new FakeConfigRepository(['encryption.key' => $key])));
}

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

/**
 * Create a FailedJob with an HMAC-signed payload containing an array with 'class' key.
 */
function createWrappedFailedJobWithClass(
    string $id,
    string $class,
    JobEnvelope $envelope,
): FailedJob {
    return new FailedJob(
        id: $id,
        queue: 'default',
        payload: $envelope->wrap(serialize(['class' => $class, 'data' => []])),
        exception: 'Connection timed out',
        failedAt: new DateTimeImmutable('2026-01-21 09:45:00'),
    );
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
    $envelope = createFailedCommandEnvelope();
    $failedJobs = [
        createWrappedFailedJobWithClass('a1b2c3d4-e5f6-7890-abcd-ef1234567890', 'App\\Jobs\\SendEmail', $envelope),
    ];

    $repository = Helpers::createStubFailedJobRepository($failedJobs);
    $command = new FailedCommand($repository, $envelope);
    ['output' => $output] = executeFailedCommand($command);

    expect($output)->toContain('a1b2c3d4-e5f6-7890-abcd-ef1234567890')
        ->and($output)->toContain('default');
});

it('shows job details', function (): void {
    $envelope = createFailedCommandEnvelope();
    $failedJobs = [
        createWrappedFailedJobWithClass('a1b2c3d4-e5f6-7890-abcd-ef1234567890', 'App\\Jobs\\SendEmail', $envelope),
    ];

    $repository = Helpers::createStubFailedJobRepository($failedJobs);
    $command = new FailedCommand($repository, $envelope);
    ['output' => $output] = executeFailedCommand($command);

    expect($output)->toContain('App\\Jobs\\SendEmail')
        ->and($output)->toContain('2026-01-21 09:45:00');
});

it('shows total count', function (): void {
    $envelope = createFailedCommandEnvelope();
    $failedJobs = [
        createWrappedFailedJobWithClass('a1b2c3d4-e5f6-7890-abcd-ef1234567890', 'App\\Jobs\\SendEmail', $envelope),
    ];

    $repository = Helpers::createStubFailedJobRepository($failedJobs);
    $command = new FailedCommand($repository, $envelope);
    ['output' => $output] = executeFailedCommand($command);

    expect($output)->toContain('Total: 1 failed job');
});

it('handles empty list', function (): void {
    $repository = Helpers::createStubFailedJobRepository();
    $command = new FailedCommand($repository, createFailedCommandEnvelope());
    ['output' => $output] = executeFailedCommand($command);

    expect($output)->toContain('No failed jobs');
});

it(
    'verifies the envelope before unserializing in the failed command and preserves the Unknown fallback',
    function (): void {
        $envelope = createFailedCommandEnvelope();

        // Use a serialized stdClass object (not an array) — this triggers the 'Unknown' fallback since
        // extractJobClass expects is_array($data) && isset($data['class']), but a serialized object is not an array.
        $wrappedObjectPayload = $envelope->wrap(serialize(new stdClass()));

        $failedJobs = [
            new FailedJob(
                id: 'obj-job-id',
                queue: 'default',
                payload: $wrappedObjectPayload,
                exception: 'Test',
                failedAt: new DateTimeImmutable('2026-01-21 09:45:00'),
            ),
        ];

        $repository = Helpers::createStubFailedJobRepository($failedJobs);
        $command = new FailedCommand($repository, $envelope);
        ['output' => $output] = executeFailedCommand($command);

        // The job class should be 'Unknown' since the payload is a serialized object, not array with 'class' key
        expect($output)->toContain('Unknown');
    },
);
