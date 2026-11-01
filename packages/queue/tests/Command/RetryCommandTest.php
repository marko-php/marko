<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\Command\RetryCommand;
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\Exceptions\SerializationException;
use Marko\Queue\FailableJobInterface;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobInterface;
use Marko\Queue\JobRunner;
use Marko\Queue\PushResult;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\RetryPolicy;
use Marko\Queue\Tests\Command\Helpers;
use Marko\Queue\Tests\Command\StubFailedJobRepository;
use Marko\Queue\Worker;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;

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

function createRetryCommandEnvelope(
    string $key = 'test-key-for-retry-command',
): JobEnvelope {
    return new JobEnvelope(new EncryptionConfig(new FakeConfigRepository(['encryption.key' => $key])));
}

/**
 * Helper to create a FailedJob with an HMAC-signed wrapped payload.
 */
function createFailedJob(
    string $id,
    string $queue = 'default',
    ?JobEnvelope $envelope = null,
): FailedJob {
    $envelope ??= createRetryCommandEnvelope();
    $job = new TestRetryJob('test-data');

    return new FailedJob(
        id: $id,
        queue: $queue,
        payload: $envelope->wrap($job->serialize()),
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

it('declares all as a flag on queue:retry', function (): void {
    $attribute = new ReflectionClass(RetryCommand::class)->getAttributes(Command::class)[0]->newInstance();

    expect($attribute->flags)->toBe(['all']);
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(RetryCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('retries specific job by ID', function (): void {
    $failedJob = createFailedJob('a1b2c3d4-e5f6-7890-abcd-ef1234567890');
    $repository = Helpers::createStubFailedJobRepository([$failedJob]);
    $queue = Helpers::createStubQueue();

    $command = new RetryCommand($repository, $queue, createRetryCommandEnvelope());

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

    $command = new RetryCommand($repository, $queue, createRetryCommandEnvelope());

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

    $command = new RetryCommand($repository, $queue, createRetryCommandEnvelope());

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

    $command = new RetryCommand($repository, $queue, createRetryCommandEnvelope());

    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'queue:retry', '--all']);

    $command->execute($input, $output);

    $result = Helpers::getOutputContent($stream);

    expect($result)->toContain('2 jobs pushed back to queue');
});

it('handles invalid ID', function (): void {
    $repository = Helpers::createStubFailedJobRepository();
    $queue = Helpers::createStubQueue();

    $command = new RetryCommand($repository, $queue, createRetryCommandEnvelope());

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

    $command = new RetryCommand($repository, $queue, createRetryCommandEnvelope());

    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'queue:retry']);

    $exitCode = $command->execute($input, $output);

    $result = Helpers::getOutputContent($stream);

    expect($exitCode)->toBe(1)
        ->and($result)->toContain('Please provide a job ID or use --all flag');
});

it('resets a retried job\'s attempts to zero before re-queuing', function (): void {
    $envelope = createRetryCommandEnvelope();

    // Create a job that has already been attempted 3 times (at maxAttempts)
    $job = new TestRetryJob('test-data');
    $job->incrementAttempts();
    $job->incrementAttempts();
    $job->incrementAttempts();

    $failedJob = new FailedJob(
        id: 'failed-job-001',
        queue: 'default',
        payload: $envelope->wrap($job->serialize()),
        exception: 'Test exception',
        failedAt: new DateTimeImmutable('2024-01-01 12:00:00'),
    );

    $repository = Helpers::createStubFailedJobRepository([$failedJob]);
    $queue = Helpers::createStubQueue();

    $command = new RetryCommand($repository, $queue, $envelope);

    ['output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'queue:retry', 'failed-job-001']);

    $command->execute($input, $output);

    /** @var TestRetryJob $pushedJob */
    $pushedJob = $queue->pushedJobs[0]['job'];

    expect($pushedJob->attempts)->toBe(0);
});

it(
    'resets a retried job\'s exception count, retry deadline and release request before re-queuing',
    function (string $mode): void {
        $envelope = createRetryCommandEnvelope();
        $job = new TestRetryJob('test-data');
        $job->incrementAttempts();
        $job->incrementExceptions();
        $job->setRetryDeadline(new DateTimeImmutable('2024-01-01 11:00:00'));
        $job->release(30);
        $failedJob = new FailedJob(
            id: 'failed-job-002',
            queue: 'default',
            payload: $envelope->wrap($job->serialize()),
            exception: 'Test exception',
            failedAt: new DateTimeImmutable('2024-01-01 12:00:00'),
        );
        $queue = Helpers::createStubQueue();
        $command = new RetryCommand(Helpers::createStubFailedJobRepository([$failedJob]), $queue, $envelope);
        ['output' => $output] = Helpers::createOutputStream();
        $argv = $mode === 'all' ? ['marko', 'queue:retry', '--all'] : ['marko', 'queue:retry', 'failed-job-002'];

        $command->execute(new Input($argv), $output);

        /** @var TestRetryJob $pushedJob */
        $pushedJob = $queue->pushedJobs[0]['job'];

        expect($pushedJob->attempts)->toBe(0)
            ->and($pushedJob->exceptions)->toBe(0)
            ->and($pushedJob->retryDeadline)->toBeNull()
            ->and($pushedJob->releaseDelay)->toBeNull();
    },
)->with(['single', 'all']);

class RetryFailableJob extends Job implements FailableJobInterface
{
    public int $failedCalls = 0;

    public function handle(): void {}

    public function failed(Throwable $exception): void
    {
        $this->failedCalls++;
    }
}

it('does not call failed() on a job it retries', function (string $mode): void {
    $envelope = createRetryCommandEnvelope();
    $failedJob = new FailedJob(
        id: 'failable-001',
        queue: 'default',
        payload: $envelope->wrap(new RetryFailableJob()->serialize()),
        exception: 'Test exception',
        failedAt: new DateTimeImmutable('2024-01-01 12:00:00'),
    );
    $queue = Helpers::createStubQueue();
    $command = new RetryCommand(Helpers::createStubFailedJobRepository([$failedJob]), $queue, $envelope);
    ['output' => $output] = Helpers::createOutputStream();
    $argv = $mode === 'all' ? ['marko', 'queue:retry', '--all'] : ['marko', 'queue:retry', 'failable-001'];

    $command->execute(new Input($argv), $output);

    /** @var RetryFailableJob $pushedJob */
    $pushedJob = $queue->pushedJobs[0]['job'];

    expect($pushedJob)->toBeInstanceOf(RetryFailableJob::class)
        ->and($pushedJob->failedCalls)->toBe(0);
})->with(['single', 'all']);

it('resets attempts when retrying all failed jobs', function (): void {
    $envelope = createRetryCommandEnvelope();

    $job1 = new TestRetryJob('data-1');
    $job1->incrementAttempts();
    $job1->incrementAttempts();
    $job1->incrementAttempts();

    $job2 = new TestRetryJob('data-2');
    $job2->incrementAttempts();

    $failedJobs = [
        new FailedJob(
            id: 'bulk-job-001',
            queue: 'default',
            payload: $envelope->wrap($job1->serialize()),
            exception: 'Exception 1',
            failedAt: new DateTimeImmutable('2024-01-01 12:00:00'),
        ),
        new FailedJob(
            id: 'bulk-job-002',
            queue: 'emails',
            payload: $envelope->wrap($job2->serialize()),
            exception: 'Exception 2',
            failedAt: new DateTimeImmutable('2024-01-01 12:00:00'),
        ),
    ];

    $repository = Helpers::createStubFailedJobRepository($failedJobs);
    $queue = Helpers::createStubQueue();

    $command = new RetryCommand($repository, $queue, $envelope);

    ['output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'queue:retry', '--all']);

    $command->execute($input, $output);

    /** @var TestRetryJob $pushedJob1 */
    $pushedJob1 = $queue->pushedJobs[0]['job'];

    /** @var TestRetryJob $pushedJob2 */
    $pushedJob2 = $queue->pushedJobs[1]['job'];

    expect($pushedJob1->attempts)->toBe(0)
        ->and($pushedJob2->attempts)->toBe(0);
});

it('re-pushes the retried job to its original queue', function (): void {
    $envelope = createRetryCommandEnvelope();
    $failedJob = new FailedJob(
        id: 'queue-test-job',
        queue: 'notifications',
        payload: $envelope->wrap((new TestRetryJob('data'))->serialize()),
        exception: 'Test exception',
        failedAt: new DateTimeImmutable('2024-01-01 12:00:00'),
    );

    $repository = Helpers::createStubFailedJobRepository([$failedJob]);
    $queue = Helpers::createStubQueue();

    $command = new RetryCommand($repository, $queue, $envelope);

    ['output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'queue:retry', 'queue-test-job']);

    $command->execute($input, $output);

    expect($queue->pushedJobs)->toHaveCount(1)
        ->and($queue->pushedJobs[0]['queue'])->toBe('notifications');
});

it('deletes the failed-job record after retrying', function (): void {
    $envelope = createRetryCommandEnvelope();
    $failedJob = new FailedJob(
        id: 'delete-test-job',
        queue: 'default',
        payload: $envelope->wrap((new TestRetryJob('data'))->serialize()),
        exception: 'Test exception',
        failedAt: new DateTimeImmutable('2024-01-01 12:00:00'),
    );

    $repository = Helpers::createStubFailedJobRepository([$failedJob]);
    $queue = Helpers::createStubQueue();

    $command = new RetryCommand($repository, $queue, $envelope);

    ['output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'queue:retry', 'delete-test-job']);

    $command->execute($input, $output);

    expect($repository->deletedIds)->toBe(['delete-test-job']);
});

it('rejects a tampered failed-job payload in the retry command', function (): void {
    $envelope = createRetryCommandEnvelope();

    $fakeHmac = str_repeat('d', 64);
    $tamperedPayload = $fakeHmac . '.O:8:"EvilJob":0:{}';

    $failedJob = new FailedJob(
        id: 'tampered-job-id',
        queue: 'default',
        payload: $tamperedPayload,
        exception: 'Test exception',
        failedAt: new DateTimeImmutable('2024-01-01 12:00:00'),
    );

    $repository = Helpers::createStubFailedJobRepository([$failedJob]);
    $queue = Helpers::createStubQueue();

    $command = new RetryCommand($repository, $queue, $envelope);

    ['output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'queue:retry', 'tampered-job-id']);

    expect(fn () => $command->execute($input, $output))
        ->toThrow(SerializationException::class);
});

/**
 * A failed-job row whose payload is the worker's placeholder for a job that could not be serialized.
 */
function createUnserializableFailedJob(
    string $id,
): FailedJob {
    return new FailedJob(
        id: $id,
        queue: 'default',
        payload: createRetryCommandEnvelope()->wrap(serialize([
            'class' => 'App\Jobs\ClosureJob',
            'serialization_error' => "Serialization of 'Closure' is not allowed",
        ])),
        exception: 'Closure job failed',
        failedAt: new DateTimeImmutable('2024-01-01 12:00:00'),
    );
}

it('refuses to retry a failed job whose payload could not be serialized', function (): void {
    $repository = Helpers::createStubFailedJobRepository([createUnserializableFailedJob('closure-job')]);
    $queue = Helpers::createStubQueue();
    $command = new RetryCommand($repository, $queue, createRetryCommandEnvelope());

    ['output' => $output, 'stream' => $stream] = Helpers::createOutputStream();
    $exitCode = $command->execute(new Input(['marko', 'queue:retry', 'closure-job']), $output);

    expect($exitCode)->toBe(1)
        ->and(Helpers::getOutputContent($stream))
        ->toContain('Job closure-job cannot be retried')
        ->toContain('App\Jobs\ClosureJob')
        ->toContain("Serialization of 'Closure' is not allowed")
        ->and($queue->pushedJobs)->toBe([])
        ->and($repository->deletedIds)->toBe([]);
});

it('skips failed jobs whose payload could not be serialized when retrying all', function (): void {
    $repository = Helpers::createStubFailedJobRepository([
        createFailedJob('job-1'),
        createUnserializableFailedJob('closure-job'),
        createFailedJob('job-2'),
    ]);
    $queue = Helpers::createStubQueue();
    $command = new RetryCommand($repository, $queue, createRetryCommandEnvelope());

    ['output' => $output, 'stream' => $stream] = Helpers::createOutputStream();
    $exitCode = $command->execute(new Input(['marko', 'queue:retry', '--all']), $output);

    expect($exitCode)->toBe(1)
        ->and(Helpers::getOutputContent($stream))
        ->toContain('Job closure-job cannot be retried')
        ->toContain('2 jobs pushed back to queue.')
        ->toContain('1 job skipped.')
        ->and($queue->pushedJobs)->toHaveCount(2)
        ->and($repository->deletedIds)->toBe(['job-1', 'job-2']);
});

it('refuses to retry a failed job whose payload is not a job', function (): void {
    $failedJob = new FailedJob(
        id: 'not-a-job',
        queue: 'default',
        payload: createRetryCommandEnvelope()->wrap(serialize('just a string')),
        exception: 'Test exception',
        failedAt: new DateTimeImmutable('2024-01-01 12:00:00'),
    );
    $repository = Helpers::createStubFailedJobRepository([$failedJob]);
    $queue = Helpers::createStubQueue();
    $command = new RetryCommand($repository, $queue, createRetryCommandEnvelope());

    ['output' => $output, 'stream' => $stream] = Helpers::createOutputStream();
    $exitCode = $command->execute(new Input(['marko', 'queue:retry', 'not-a-job']), $output);

    expect($exitCode)->toBe(1)
        ->and(Helpers::getOutputContent($stream))->toContain('Job not-a-job cannot be retried')
        ->and($queue->pushedJobs)->toBe([]);
});

/**
 * Service a retried container-aware job resolves from whichever container the worker gives it.
 */
class RetryRecordingService
{
    public int $calls = 0;

    public function run(): void
    {
        $this->calls++;
    }
}

/**
 * Container-aware job that resolves RetryRecordingService when it runs.
 */
class RetryContainerAwareJob extends Job implements ContainerAwareJobInterface
{
    public private(set) ?ContainerInterface $container = null;

    public private(set) int $attemptsWhenHandled = 0;

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function setJobEnvelope(JobEnvelope $jobEnvelope): void {}

    public function releaseContainer(): void
    {
        $this->container = null;
    }

    public function handle(): void
    {
        $this->attemptsWhenHandled = $this->attempts;
        $this->container->get(RetryRecordingService::class)->run();
    }
}

/**
 * In-memory queue: push() stores a job and pop() hands it back.
 */
class RetryRoundTripQueue implements QueueInterface
{
    /** @var list<JobInterface> */
    private array $jobs = [];

    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        $job->setId('round-trip-job');
        $this->jobs[] = $job;

        return PushResult::pushed('round-trip-job');
    }

    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return $this->push($job, $queue);
    }

    public function pop(
        ?string $queue = null,
    ): ?JobInterface {
        return array_shift($this->jobs);
    }

    public function size(
        ?string $queue = null,
    ): int {
        return count($this->jobs);
    }

    public function clear(
        ?string $queue = null,
    ): int {
        return 0;
    }

    public function delete(
        string $jobId,
    ): bool {
        return true;
    }

    public function release(
        JobInterface $job,
        int $delay = 0,
    ): bool {
        return true;
    }
}

/**
 * A container that cannot be serialized (anonymous class) and resolves nothing.
 */
function createFailingRetryContainer(): ContainerInterface
{
    return new class () implements ContainerInterface
    {
        public function get(string $id): never
        {
            throw new RuntimeException("Service unavailable: $id");
        }

        public function has(string $id): bool
        {
            return false;
        }

        public function singleton(string $id): void {}

        public function instance(
            string $id,
            object $instance,
        ): void {}

        public function call(Closure $callable): mixed
        {
            return null;
        }

        public function resolvedInstances(?string $interface = null): array
        {
            return [];
        }
    };
}

function createRetryWorker(
    QueueInterface $queue,
    StubFailedJobRepository $failedJobRepository,
    ContainerInterface $container,
): Worker {
    return new Worker(
        $queue,
        new FailedJobHandler(
            $failedJobRepository,
            createRetryCommandEnvelope(),
            new JobRunner($container, createRetryCommandEnvelope(), new FakeEventDispatcher()),
            new FakeEventDispatcher(),
            new FakeClock(),
        ),
        new QueueConfig(new FakeConfigRepository([
            'queue.queue' => 'default',
            'queue.max_attempts' => 1,
        ])),
        new JobRunner($container, createRetryCommandEnvelope(), new FakeEventDispatcher()),
        new RetryPolicy(new QueueConfig(new FakeConfigRepository([
            'queue.queue' => 'default',
            'queue.max_attempts' => 1,
        ])), new FakeClock()),
    );
}

/**
 * Fail a RetryContainerAwareJob for the last time under a container that resolves nothing.
 *
 * @return array{queue: RetryRoundTripQueue, failed: StubFailedJobRepository}
 */
function failContainerAwareJobForTheLastTime(): array
{
    $queue = new RetryRoundTripQueue();
    $failedRepository = Helpers::createStubFailedJobRepository();
    $queue->push(new RetryContainerAwareJob());

    createRetryWorker($queue, $failedRepository, createFailingRetryContainer())->work(once: true);

    return ['queue' => $queue, 'failed' => $failedRepository];
}

it('stores the failed container-aware job payload without the container', function (): void {
    ['failed' => $failedRepository] = failContainerAwareJobForTheLastTime();

    $stored = unserialize(createRetryCommandEnvelope()->verifyAndUnwrap($failedRepository->all()[0]->payload));

    expect($stored)->toBeInstanceOf(RetryContainerAwareJob::class)
        ->and($stored->container)->toBeNull();
});

it('retries a stored failed container-aware job and runs it with a fresh container', function (): void {
    ['queue' => $queue, 'failed' => $failedRepository] = failContainerAwareJobForTheLastTime();

    $service = new RetryRecordingService();
    $freshContainer = new Container();
    $freshContainer->instance(RetryRecordingService::class, $service);

    ['output' => $output] = Helpers::createOutputStream();
    $exitCode = new RetryCommand($failedRepository, $queue, createRetryCommandEnvelope())
        ->execute(new Input(['marko', 'queue:retry', 'round-trip-job']), $output);

    createRetryWorker($queue, $failedRepository, $freshContainer)->work(once: true);

    expect($exitCode)->toBe(0)
        ->and($service->calls)->toBe(1)
        ->and($failedRepository->count())->toBe(0);
});

it('resets the retried container-aware job attempts before it runs again', function (): void {
    ['queue' => $queue, 'failed' => $failedRepository] = failContainerAwareJobForTheLastTime();

    ['output' => $output] = Helpers::createOutputStream();
    new RetryCommand($failedRepository, $queue, createRetryCommandEnvelope())
        ->execute(new Input(['marko', 'queue:retry', 'round-trip-job']), $output);

    /** @var RetryContainerAwareJob $retried */
    $retried = $queue->pop();
    $queue->push($retried);

    $freshContainer = new Container();
    $freshContainer->instance(RetryRecordingService::class, new RetryRecordingService());
    createRetryWorker($queue, $failedRepository, $freshContainer)->work(once: true);

    expect($retried->attemptsWhenHandled)->toBe(1);
});
