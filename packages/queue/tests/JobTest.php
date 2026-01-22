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
});
