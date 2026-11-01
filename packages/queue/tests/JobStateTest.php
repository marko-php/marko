<?php

declare(strict_types=1);

use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\Job;
use Marko\Queue\JobInterface;

class JobStateTestJob extends Job
{
    public function handle(): void {}
}

class JobStateCustomLimitsJob extends Job
{
    public protected(set) ?int $timeout = 120;

    public protected(set) ?int $maxExceptions = 1;

    public function handle(): void {}
}

describe('JobInterface per-job state', function (): void {
    it('declares the per-job state properties and methods', function (): void {
        $reflection = new ReflectionClass(JobInterface::class);

        foreach (['timeout', 'maxExceptions', 'exceptions', 'retryDeadline', 'releaseDelay'] as $property) {
            expect($reflection->hasProperty($property))->toBeTrue();
        }

        foreach (['incrementExceptions', 'setRetryDeadline', 'release', 'clearRelease'] as $method) {
            expect($reflection->hasMethod($method))->toBeTrue();
        }
    });
});

describe('Job per-job state', function (): void {
    it(
        'defaults timeout, maxExceptions, retryDeadline and releaseDelay to null and exceptions to 0',
        function (): void {
            $job = new JobStateTestJob();

            expect($job->timeout)->toBeNull()
                ->and($job->maxExceptions)->toBeNull()
                ->and($job->exceptions)->toBe(0)
                ->and($job->retryDeadline)->toBeNull()
                ->and($job->releaseDelay)->toBeNull();
        },
    );

    it('lets a job subclass declare its own timeout and maxExceptions', function (): void {
        $job = new JobStateCustomLimitsJob();

        expect($job->timeout)->toBe(120)
            ->and($job->maxExceptions)->toBe(1);
    });

    it('counts exceptions', function (): void {
        $job = new JobStateTestJob();
        $job->incrementExceptions();
        $job->incrementExceptions();

        expect($job->exceptions)->toBe(2);
    });

    it('stores the retry deadline it is given', function (): void {
        $job = new JobStateTestJob();
        $deadline = new DateTimeImmutable('2026-11-01T06:00:00Z');
        $job->setRetryDeadline($deadline);

        expect($job->retryDeadline)->toBe($deadline);
    });

    it('sets releaseDelay without throwing when release() is called', function (): void {
        $job = new JobStateTestJob();
        $job->release(30);

        expect($job->releaseDelay)->toBe(30);
    });

    it('defaults the release delay to 0 seconds', function (): void {
        $job = new JobStateTestJob();
        $job->release();

        expect($job->releaseDelay)->toBe(0);
    });

    it('clears a release request', function (): void {
        $job = new JobStateTestJob();
        $job->release(10);
        $job->clearRelease();

        expect($job->releaseDelay)->toBeNull();
    });

    it('rejects a negative release delay loudly', function (): void {
        expect(fn () => new JobStateTestJob()->release(-1))
            ->toThrow(QueueException::class, 'Invalid release delay');
    });

    it('keeps exceptions, retry deadline and release delay through serialization', function (): void {
        $job = new JobStateTestJob();
        $job->incrementExceptions();
        $job->setRetryDeadline(new DateTimeImmutable('2026-11-01T06:00:00Z'));
        $job->release(15);

        $restored = JobStateTestJob::unserialize($job->serialize());

        expect($restored->exceptions)->toBe(1)
            ->and($restored->retryDeadline?->format(DATE_ATOM))->toBe('2026-11-01T06:00:00+00:00')
            ->and($restored->releaseDelay)->toBe(15);
    });
});
