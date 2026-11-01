<?php

declare(strict_types=1);

use Marko\Queue\Exceptions\JobAttemptsExhaustedException;
use Marko\Queue\Exceptions\JobExceptionsExhaustedException;
use Marko\Queue\Exceptions\JobRetryDeadlinePassedException;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\Job;
use Marko\Queue\QueueConfig;
use Marko\Queue\RetryDecision;
use Marko\Queue\RetryPolicy;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

class RetryPolicyTestJob extends Job
{
    /**
     * @param int|list<int>|null $backoff
     */
    public function __construct(
        ?int $maxAttempts = null,
        ?int $maxExceptions = null,
        array|int|null $backoff = null,
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->maxExceptions = $maxExceptions;
        $this->backoff = $backoff;
    }

    public function handle(): void {}
}

/**
 * @param array<string, mixed> $values
 */
function createRetryPolicy(
    FakeClock $clock = new FakeClock('2026-10-31 12:00:00'),
    array $values = [],
): RetryPolicy {
    return new RetryPolicy(
        new QueueConfig(new FakeConfigRepository(array_merge(['queue.max_attempts' => 3], $values))),
        $clock,
    );
}

/**
 * Run the given number of attempts on the job, each ending in an exception when $failing is true.
 */
function attemptRetryPolicyJob(
    Job $job,
    int $attempts,
    bool $failing = false,
): Job {
    for ($i = 0; $i < $attempts; $i++) {
        $job->incrementAttempts();

        if ($failing) {
            $job->incrementExceptions();
        }
    }

    return $job;
}

describe('RetryPolicy', function (): void {
    it('releases a job with attempts left after its backoff', function (): void {
        $job = attemptRetryPolicyJob(new RetryPolicyTestJob(backoff: 15), 1, failing: true);

        $decision = createRetryPolicy()->decide($job, new RuntimeException('boom'));

        expect($decision)->toBeInstanceOf(RetryDecision::class)
            ->and($decision->shouldRelease())->toBeTrue()
            ->and($decision->delay)->toBe(15)
            ->and($decision->failure)->toBeNull();
    });

    it('releases a self-released job after the delay it asked for, not its backoff', function (): void {
        $job = attemptRetryPolicyJob(new RetryPolicyTestJob(backoff: 15), 1);
        $job->release(42);

        $decision = createRetryPolicy()->decide($job);

        expect($decision->shouldRelease())->toBeTrue()
            ->and($decision->delay)->toBe(42);
    });

    it('fails a job once its attempts reach maxAttempts', function (): void {
        $job = attemptRetryPolicyJob(new RetryPolicyTestJob(maxAttempts: 2), 2, failing: true);

        $decision = createRetryPolicy()->decide($job, new RuntimeException('boom'));

        expect($decision->shouldRelease())->toBeFalse()
            ->and($decision->delay)->toBeNull()
            ->and($decision->failure)->toBeInstanceOf(JobAttemptsExhaustedException::class)
            ->and($decision->failure?->getMessage())->toContain('2 of its 2 attempts');
    });

    it('uses the queue.max_attempts config when the job has no maxAttempts', function (): void {
        $policy = createRetryPolicy(values: ['queue.max_attempts' => 4]);

        expect($policy->maxAttemptsFor(new RetryPolicyTestJob()))->toBe(4)
            ->and($policy->maxAttemptsFor(new RetryPolicyTestJob(maxAttempts: 7)))->toBe(7)
            ->and($policy->decide(attemptRetryPolicyJob(new RetryPolicyTestJob(), 3))->shouldRelease())->toBeTrue()
            ->and($policy->decide(attemptRetryPolicyJob(new RetryPolicyTestJob(), 4))->shouldRelease())->toBeFalse();
    });

    it('ignores the attempt limit while a retry deadline is set and still ahead', function (): void {
        $clock = new FakeClock('2026-10-31 12:00:00');
        $job = attemptRetryPolicyJob(new RetryPolicyTestJob(maxAttempts: 3), 500);
        $job->setRetryDeadline(new DateTimeImmutable('2026-10-31 18:00:00'));
        $job->release(30);

        $decision = createRetryPolicy($clock)->decide($job);

        expect($decision->shouldRelease())->toBeTrue()
            ->and($decision->delay)->toBe(30)
            ->and(createRetryPolicy($clock)->limitReached($job))->toBeNull();
    });

    it('fails a job whose retry deadline has passed', function (): void {
        $clock = new FakeClock('2026-10-31 12:00:00');
        $job = attemptRetryPolicyJob(new RetryPolicyTestJob(), 1);
        $job->setRetryDeadline(new DateTimeImmutable('2026-10-31 18:00:00'));
        $job->release(30);
        $policy = createRetryPolicy($clock);

        expect($policy->decide($job)->shouldRelease())->toBeTrue();

        $clock->setNow('2026-10-31 18:00:01');
        $decision = $policy->decide($job);

        expect($decision->shouldRelease())->toBeFalse()
            ->and($decision->failure)->toBeInstanceOf(JobRetryDeadlinePassedException::class)
            ->and($decision->failure?->getMessage())->toContain('2026-10-31T18:00:00');
    });

    it('fails a job once its exceptions reach maxExceptions', function (): void {
        $job = attemptRetryPolicyJob(new RetryPolicyTestJob(maxExceptions: 2), 1, failing: true);
        $policy = createRetryPolicy();

        expect($policy->decide($job, new RuntimeException('boom'))->shouldRelease())->toBeTrue();

        attemptRetryPolicyJob($job, 1, failing: true);
        $decision = $policy->decide($job, new RuntimeException('boom'));

        expect($decision->shouldRelease())->toBeFalse()
            ->and($decision->failure)->toBeInstanceOf(JobExceptionsExhaustedException::class)
            ->and($decision->failure?->getMessage())->toContain('2 of its 2 allowed exceptions');
    });

    it('fails the first exception of a job with maxExceptions 1 and a retry deadline', function (): void {
        $job = attemptRetryPolicyJob(new RetryPolicyTestJob(maxExceptions: 1), 200);
        $job->setRetryDeadline(new DateTimeImmutable('2026-10-31 18:00:00'));
        attemptRetryPolicyJob($job, 1, failing: true);

        $decision = createRetryPolicy()->decide($job, new RuntimeException('boom'));

        expect($decision->failure)->toBeInstanceOf(JobExceptionsExhaustedException::class);
    });

    it('checks the deadline first, then maxExceptions, then maxAttempts', function (): void {
        $clock = new FakeClock('2026-10-31 12:00:00');
        $policy = createRetryPolicy($clock);

        $everyLimit = attemptRetryPolicyJob(
            new RetryPolicyTestJob(maxAttempts: 1, maxExceptions: 1),
            1,
            failing: true,
        );
        $everyLimit->setRetryDeadline(new DateTimeImmutable('2026-10-31 11:00:00'));

        $exceptionsAndAttempts = attemptRetryPolicyJob(
            new RetryPolicyTestJob(maxAttempts: 1, maxExceptions: 1),
            1,
            failing: true,
        );

        expect($policy->limitReached($everyLimit))->toBeInstanceOf(JobRetryDeadlinePassedException::class)
            ->and($policy->limitReached($exceptionsAndAttempts))->toBeInstanceOf(
                JobExceptionsExhaustedException::class,
            );
    });

    it('fails a job whose backoff is invalid instead of releasing it', function (): void {
        $job = attemptRetryPolicyJob(new RetryPolicyTestJob(backoff: -5), 1, failing: true);

        $decision = createRetryPolicy()->decide($job, new RuntimeException('boom'));

        expect($decision->shouldRelease())->toBeFalse()
            ->and($decision->failure)->toBeInstanceOf(QueueException::class)
            ->and($decision->failure?->getMessage())->toContain('Invalid queue backoff');
    });

    it(
        'computes the backoff from the job, then the queue.backoff config, then the exponential curve',
        function (): void {
            $policy = createRetryPolicy(values: ['queue.backoff' => [5, 10]]);

            expect($policy->backoffFor(attemptRetryPolicyJob(new RetryPolicyTestJob(backoff: 7), 1)))->toBe(7)
                ->and($policy->backoffFor(attemptRetryPolicyJob(new RetryPolicyTestJob(backoff: [1, 2, 3]), 2)))->toBe(
                    2,
                )
                ->and($policy->backoffFor(attemptRetryPolicyJob(new RetryPolicyTestJob(), 3)))->toBe(10)
                ->and(createRetryPolicy()->backoffFor(attemptRetryPolicyJob(new RetryPolicyTestJob(), 2)))->toBe(40);
        },
    );
});

describe('RetryDecision', function (): void {
    it('rejects a negative release delay', function (): void {
        RetryDecision::release(-1);
    })->throws(QueueException::class);
});
