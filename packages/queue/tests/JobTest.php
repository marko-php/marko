<?php

declare(strict_types=1);

use Marko\Queue\Job;
use Marko\Queue\JobInterface;

class TestJob extends Job
{
    public function __construct(
        public string $message = 'test',
    ) {}

    public function handle(): void
    {
        // Test implementation
    }
}

class CustomMaxAttemptsJob extends Job
{
    protected int $maxAttempts = 10;

    public function handle(): void
    {
        // Job with custom max attempts for serialization testing
    }
}

describe('Job', function (): void {
    it('implements JobInterface', function (): void {
        $reflection = new ReflectionClass(Job::class);

        expect($reflection->isAbstract())->toBeTrue()
            ->and($reflection->implementsInterface(JobInterface::class))->toBeTrue();
    });

    it('serialize and unserialize work correctly', function (): void {
        $job = new TestJob('hello world');
        $job->setId('job-123');
        $job->incrementAttempts();

        $serialized = $job->serialize();

        expect($serialized)->toBeString();

        $unserialized = TestJob::unserialize($serialized);

        expect($unserialized)->toBeInstanceOf(TestJob::class)
            ->and($unserialized->message)->toBe('hello world')
            ->and($unserialized->getId())->toBe('job-123')
            ->and($unserialized->getAttempts())->toBe(1);
    });

    it('tracks attempts correctly', function (): void {
        $job = new TestJob();

        expect($job->getAttempts())->toBe(0);

        $job->incrementAttempts();

        expect($job->getAttempts())->toBe(1);

        $job->incrementAttempts();
        $job->incrementAttempts();

        expect($job->getAttempts())->toBe(3);
    });

    it('has default max attempts of 3', function (): void {
        $job = new TestJob();

        expect($job->getMaxAttempts())->toBe(3);
    });

    it('returns null id by default', function (): void {
        $job = new TestJob();

        expect($job->getId())->toBeNull();
    });

    it('allows setting and getting id', function (): void {
        $job = new TestJob();
        $job->setId('my-job-id');

        expect($job->getId())->toBe('my-job-id');
    });

    it('Job handles custom maxAttempts', function (): void {
        $customJob = new class () extends Job
        {
            protected int $maxAttempts = 5;

            public function handle(): void
            {
                // Custom job with different max attempts
            }
        };

        expect($customJob->getMaxAttempts())->toBe(5);

        // Test another custom value
        $singleAttemptJob = new class () extends Job
        {
            protected int $maxAttempts = 1;

            public function handle(): void {}
        };

        expect($singleAttemptJob->getMaxAttempts())->toBe(1);

        // Test that custom maxAttempts persists through serialization using named class
        $customAttemptsJob = new CustomMaxAttemptsJob();
        $customAttemptsJob->setId('custom-job-1');
        $serialized = $customAttemptsJob->serialize();
        $unserialized = CustomMaxAttemptsJob::unserialize($serialized);

        expect($unserialized->getMaxAttempts())->toBe(10);
    });
});
