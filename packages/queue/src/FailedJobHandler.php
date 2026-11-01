<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Core\Event\EventDispatcherInterface;
use Marko\Queue\Event\JobFailedEvent;
use Marko\Queue\Exceptions\SerializationException;
use Psr\Clock\ClockInterface;
use Throwable;

class FailedJobHandler implements FailedJobHandlerInterface
{
    public function __construct(
        private readonly FailedJobRepositoryInterface $failedJobRepository,
        private readonly JobEnvelope $jobEnvelope,
        private readonly JobRunnerInterface $jobRunner,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * A failed() hook that throws is logged with the job's class and id and does not stop the
     * event or the caller: one broken cleanup must not take down a long-running worker.
     *
     * @throws SerializationException
     */
    public function handle(
        JobInterface $job,
        string $queue,
        Throwable $exception,
        string $note = '',
    ): void {
        $this->store($job, $queue, $exception, $note);

        if ($job instanceof FailableJobInterface) {
            $this->callFailedHook($job, $exception);
        }

        $this->eventDispatcher->dispatch(new JobFailedEvent($job, $queue, $exception));
    }

    /**
     * Store the job in the failed-job repository.
     *
     * A job whose payload cannot be serialized (for example one holding a closure) is still
     * recorded, with a placeholder payload of its class and the serialization error, so the
     * caller can delete it and keep running. queue:retry refuses that row.
     *
     * @throws SerializationException
     */
    private function store(
        JobInterface $job,
        string $queue,
        Throwable $e,
        string $note,
    ): void {
        $exception = $e->getMessage() . "\n" . $this->traceWithoutArguments($e) . $note;

        try {
            $serialized = $job->serialize();
        } catch (Throwable $serializationError) {
            $serialized = serialize([
                'class' => $job::class,
                'serialization_error' => $serializationError->getMessage(),
            ]);
            $exception .= "\n\nJob payload could not be serialized, so this failed job cannot be retried. "
                . 'Job class ' . $job::class . ': ' . $serializationError->getMessage();
        }

        $this->failedJobRepository->store(new FailedJob(
            id: $job->id,
            queue: $queue,
            payload: $this->jobEnvelope->wrap($serialized),
            exception: $exception,
            failedAt: $this->clock->now(),
        ));
    }

    /**
     * Call failed() with the container a container-aware job holds during handle().
     */
    private function callFailedHook(
        FailableJobInterface&JobInterface $job,
        Throwable $exception,
    ): void {
        try {
            $this->jobRunner->withContainer($job, static fn () => $job->failed($exception));
        } catch (Throwable $hookError) {
            error_log(sprintf(
                '[marko/queue] failed() of job %s (id %s) threw %s: %s',
                $job::class,
                $job->id ?? 'none',
                $hookError::class,
                $hookError->getMessage(),
            ));
        }
    }

    /**
     * The exception's stack trace in getTraceAsString() format, without call arguments.
     *
     * getTraceAsString() includes arguments unless zend.exception_ignore_args is on, and they
     * can hold passwords or tokens that must not be stored in failed_jobs.
     */
    private function traceWithoutArguments(
        Throwable $e,
    ): string {
        $lines = [];

        foreach ($e->getTrace() as $index => $frame) {
            $location = isset($frame['file'])
                ? $frame['file'] . '(' . ($frame['line'] ?? 0) . ')'
                : '[internal function]';
            $lines[] = sprintf(
                '#%d %s: %s%s%s()',
                $index,
                $location,
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'],
            );
        }

        $lines[] = '#' . count($lines) . ' {main}';

        return implode("\n", $lines);
    }
}
