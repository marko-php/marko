<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Queue\Command\RetryCommand;
use Marko\Queue\FailedJob;
use Marko\Queue\Job;
use Marko\Queue\Tests\Command\Helpers;

/**
 * A simple test job for retry testing.
 */
class TestRetryJob extends Job
{
    public function __construct(
        public string $data = 'test',
    ) {}

    public function handle(): void
    {
        // Do nothing
    }
}

/**
 * Helper to create a FailedJob with a serialized TestRetryJob.
 */
function createFailedJob(
    string $id,
    string $queue = 'default',
): FailedJob {
    $job = new TestRetryJob('test-data');

    return new FailedJob(
        id: $id,
        queue: $queue,
        payload: $job->serialize(),
        exception: 'Test exception',
        failedAt: new DateTimeImmutable('2024-01-01 12:00:00'),
    );
}

it('registers as queue:retry command via #[Command] attribute', function (): void {
    $reflection = new ReflectionClass(RetryCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('queue:retry');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(RetryCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('retries specific job by ID', function (): void {
    $failedJob = createFailedJob('a1b2c3d4-e5f6-7890-abcd-ef1234567890');
    $repository = Helpers::createStubFailedJobRepository([$failedJob]);
    $queue = Helpers::createStubQueue();

    $command = new RetryCommand($repository, $queue);

    ['output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'queue:retry', 'a1b2c3d4-e5f6-7890-abcd-ef1234567890']);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0)
        ->and($queue->pushedJobs)->toHaveCount(1)
        ->and($queue->pushedJobs[0]['queue'])->toBe('default')
        ->and($repository->deletedIds)->toBe(['a1b2c3d4-e5f6-7890-abcd-ef1234567890']);
});

it('supports all flag', function (): void {
    $failedJobs = [
        createFailedJob('job-1'),
        createFailedJob('job-2', 'emails'),
    ];
    $repository = Helpers::createStubFailedJobRepository($failedJobs);
    $queue = Helpers::createStubQueue();

    $command = new RetryCommand($repository, $queue);

    ['output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'queue:retry', '--all']);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0)
        ->and($queue->pushedJobs)->toHaveCount(2)
        ->and($queue->pushedJobs[0]['queue'])->toBe('default')
        ->and($queue->pushedJobs[1]['queue'])->toBe('emails')
        ->and($repository->deletedIds)->toHaveCount(2);
});

it('shows success message for single job', function (): void {
    $failedJob = createFailedJob('a1b2c3d4-e5f6-7890-abcd-ef1234567890');
    $repository = Helpers::createStubFailedJobRepository([$failedJob]);
    $queue = Helpers::createStubQueue();

    $command = new RetryCommand($repository, $queue);

    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'queue:retry', 'a1b2c3d4-e5f6-7890-abcd-ef1234567890']);

    $command->execute($input, $output);

    $result = Helpers::getOutputContent($stream);

    expect($result)->toContain('a1b2c3d4-e5f6-7890-abcd-ef1234567890')
        ->and($result)->toContain('pushed back to queue');
});

it('shows success message for all jobs', function (): void {
    $failedJobs = [
        createFailedJob('job-1'),
        createFailedJob('job-2'),
    ];
    $repository = Helpers::createStubFailedJobRepository($failedJobs);
    $queue = Helpers::createStubQueue();

    $command = new RetryCommand($repository, $queue);

    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'queue:retry', '--all']);

    $command->execute($input, $output);

    $result = Helpers::getOutputContent($stream);

    expect($result)->toContain('2 jobs pushed back to queue');
});

it('handles invalid ID', function (): void {
    $repository = Helpers::createStubFailedJobRepository();
    $queue = Helpers::createStubQueue();

    $command = new RetryCommand($repository, $queue);

    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'queue:retry', 'non-existent-job-id']);

    $exitCode = $command->execute($input, $output);

    $result = Helpers::getOutputContent($stream);

    expect($exitCode)->toBe(1)
        ->and($result)->toContain('not found');
});

it('requires job ID or --all flag', function (): void {
    $repository = Helpers::createStubFailedJobRepository();
    $queue = Helpers::createStubQueue();

    $command = new RetryCommand($repository, $queue);

    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'queue:retry']);

    $exitCode = $command->execute($input, $output);

    $result = Helpers::getOutputContent($stream);

    expect($exitCode)->toBe(1)
        ->and($result)->toContain('Please provide a job ID or use --all flag');
});
