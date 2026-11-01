<?php

declare(strict_types=1);

use Marko\Core\Event\Event;
use Marko\Queue\Event\JobFailedEvent;
use Marko\Queue\Event\JobProcessedEvent;
use Marko\Queue\Event\JobProcessingEvent;
use Marko\Queue\Exceptions\JobAttemptsExhaustedException;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\Job;

class LifecycleEventJob extends Job
{
    public function handle(): void {}
}

describe('job lifecycle events', function (): void {
    it(
        'exposes the job, its class and its id on JobProcessingEvent and JobProcessedEvent',
        function (string $eventClass): void {
            $job = new LifecycleEventJob();
            $job->setId('job-1');

            $event = new $eventClass($job);

            expect($event)->toBeInstanceOf(Event::class)
                ->and($event->job)->toBe($job)
                ->and($event->jobClass)->toBe(LifecycleEventJob::class)
                ->and($event->jobId)->toBe('job-1');
        },
    )->with([JobProcessingEvent::class, JobProcessedEvent::class]);

    it('exposes the job, its class and id, the queue and the exception on JobFailedEvent', function (): void {
        $job = new LifecycleEventJob();
        $job->setId('job-2');
        $exception = new RuntimeException('boom');

        $event = new JobFailedEvent($job, 'emails', $exception);

        expect($event)->toBeInstanceOf(Event::class)
            ->and($event->job)->toBe($job)
            ->and($event->jobClass)->toBe(LifecycleEventJob::class)
            ->and($event->jobId)->toBe('job-2')
            ->and($event->queue)->toBe('emails')
            ->and($event->exception)->toBe($exception);
    });
});

describe('JobAttemptsExhaustedException', function (): void {
    it('describes a job whose attempts were used up by crashed reservations', function (): void {
        $exception = JobAttemptsExhaustedException::afterCrashedReservations('job-3', 3, 3);

        expect($exception)->toBeInstanceOf(QueueException::class)
            ->and($exception->getMessage())->toBe(
                'Job job-3 exceeded max attempts after worker crash or timeout: '
                . '3 of 3 attempts were reserved without being completed or released.',
            )
            ->and($exception->getSuggestion())->toContain('queue:retry');
    });
});
