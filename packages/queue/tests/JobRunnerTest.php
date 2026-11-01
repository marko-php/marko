<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobRunner;
use Marko\Queue\JobRunnerInterface;
use Marko\Testing\Fake\FakeConfigRepository;

function createJobRunnerTestEnvelope(): JobEnvelope
{
    return new JobEnvelope(new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'job-runner-key'])));
}

class JobRunnerPlainJob extends Job
{
    public int $handled = 0;

    public ?int $attemptsWhenHandled = null;

    public ?int $releaseDelayWhenHandled = null;

    public function __construct(
        public readonly bool $fails = false,
        public readonly ?int $releaseWith = null,
    ) {}

    public function handle(): void
    {
        $this->handled++;
        $this->attemptsWhenHandled = $this->attempts;
        $this->releaseDelayWhenHandled = $this->releaseDelay;

        if ($this->releaseWith !== null) {
            $this->release($this->releaseWith);
        }

        if ($this->fails) {
            throw new RuntimeException('plain job failed');
        }
    }
}

class JobRunnerContainerAwareJob extends Job implements ContainerAwareJobInterface
{
    public ?ContainerInterface $container = null;

    public ?JobEnvelope $jobEnvelope = null;

    public bool $hadBothWhenHandled = false;

    public int $releaseCount = 0;

    public function __construct(
        public readonly bool $fails = false,
    ) {}

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function setJobEnvelope(JobEnvelope $jobEnvelope): void
    {
        $this->jobEnvelope = $jobEnvelope;
    }

    public function releaseContainer(): void
    {
        $this->container = null;
        $this->jobEnvelope = null;
        $this->releaseCount++;
    }

    public function handle(): void
    {
        $this->hadBothWhenHandled = $this->container !== null && $this->jobEnvelope !== null;

        if ($this->fails) {
            throw new RuntimeException('container-aware job failed');
        }
    }
}

describe('JobRunner', function (): void {
    it('implements JobRunnerInterface', function (): void {
        expect(new JobRunner(new Container(), createJobRunnerTestEnvelope()))
            ->toBeInstanceOf(JobRunnerInterface::class);
    });

    it('increments attempts before handling the job', function (): void {
        $job = new JobRunnerPlainJob();

        new JobRunner(new Container(), createJobRunnerTestEnvelope())->run($job);

        expect($job->handled)->toBe(1)
            ->and($job->attemptsWhenHandled)->toBe(1)
            ->and($job->attempts)->toBe(1)
            ->and($job->exceptions)->toBe(0);
    });

    it('counts an exception and rethrows it when handle() throws', function (): void {
        $job = new JobRunnerPlainJob(fails: true);
        $runner = new JobRunner(new Container(), createJobRunnerTestEnvelope());

        expect(fn () => $runner->run($job))->toThrow(RuntimeException::class, 'plain job failed')
            ->and($job->exceptions)->toBe(1)
            ->and($job->attempts)->toBe(1);
    });

    it('does not count a release requested from inside handle() as an exception', function (): void {
        $job = new JobRunnerPlainJob(releaseWith: 30);

        new JobRunner(new Container(), createJobRunnerTestEnvelope())->run($job);

        expect($job->releaseDelay)->toBe(30)
            ->and($job->exceptions)->toBe(0);
    });

    it('clears a release request left over from an earlier attempt before handling the job', function (): void {
        $job = new JobRunnerPlainJob();
        $job->release(30);

        new JobRunner(new Container(), createJobRunnerTestEnvelope())->run($job);

        expect($job->releaseDelayWhenHandled)->toBeNull()
            ->and($job->releaseDelay)->toBeNull();
    });

    it(
        'gives a container-aware job the container and envelope, then releases them when handle() returns',
        function (): void {
            $container = new Container();
            $job = new JobRunnerContainerAwareJob();

            new JobRunner($container, createJobRunnerTestEnvelope())->run($job);

            expect($job->hadBothWhenHandled)->toBeTrue()
                ->and($job->releaseCount)->toBe(1)
                ->and($job->container)->toBeNull()
                ->and($job->jobEnvelope)->toBeNull();
        },
    );

    it('releases the container when a container-aware job throws, and counts the exception', function (): void {
        $job = new JobRunnerContainerAwareJob(fails: true);
        $runner = new JobRunner(new Container(), createJobRunnerTestEnvelope());

        expect(fn () => $runner->run($job))->toThrow(RuntimeException::class, 'container-aware job failed')
            ->and($job->releaseCount)->toBe(1)
            ->and($job->container)->toBeNull()
            ->and($job->exceptions)->toBe(1);
    });

    it('runs a callback with the container set up and returns its result', function (): void {
        $container = new Container();
        $job = new JobRunnerContainerAwareJob();
        $runner = new JobRunner($container, createJobRunnerTestEnvelope());

        $result = $runner->withContainer($job, fn (): ?ContainerInterface => $job->container);

        expect($result)->toBe($container)
            ->and($job->releaseCount)->toBe(1)
            ->and($job->container)->toBeNull();
    });

    it('releases the container when the callback throws', function (): void {
        $job = new JobRunnerContainerAwareJob();
        $runner = new JobRunner(new Container(), createJobRunnerTestEnvelope());

        expect(fn () => $runner->withContainer($job, fn () => throw new LogicException('callback failed')))
            ->toThrow(LogicException::class, 'callback failed')
            ->and($job->releaseCount)->toBe(1);
    });

    it('runs the callback as-is for a job that is not container-aware', function (): void {
        $job = new JobRunnerPlainJob();
        $runner = new JobRunner(new Container(), createJobRunnerTestEnvelope());

        expect($runner->withContainer($job, fn (): string => 'ran'))->toBe('ran');
    });
});
