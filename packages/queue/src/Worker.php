<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Queue\Exceptions\JobTimedOutException;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\Exceptions\SerializationException;
use Psr\Clock\ClockInterface;
use Throwable;

class Worker implements WorkerInterface
{
    /** Exit status of a worker process that ended because a job exceeded its timeout. */
    public const int EXIT_TIMED_OUT = 1;

    private bool $running = false;

    private bool $timedOut = false;

    public function __construct(
        private readonly QueueInterface $queue,
        private readonly FailedJobRepositoryInterface $failedJobRepository,
        private readonly QueueConfig $config,
        private readonly JobEnvelope $jobEnvelope,
        private readonly JobRunnerInterface $jobRunner,
        private readonly ClockInterface $clock,
        private readonly BackoffValidator $backoffValidator = new BackoffValidator(),
        private readonly ProcessControlInterface $processControl = new PcntlProcessControl(),
    ) {}

    /**
     * @param list<string>|null $queues Queue names in priority order, or null for the default queue
     * @throws QueueException|SerializationException
     */
    public function work(
        ?array $queues = null,
        bool $once = false,
        int $sleep = 3,
        WorkerOptions $options = new WorkerOptions(),
    ): void {
        $queueNames = $this->resolveQueueNames($queues);
        $this->running = true;
        $jobsProcessed = 0;

        // SIGTERM/SIGINT only stop the loop, so the current job finishes before the worker exits
        $this->processControl->listenForTermination($this->stop(...));

        try {
            while ($this->running) {
                try {
                    [$job, $queue] = $this->popNextJob($queueNames);
                } catch (Throwable $e) {
                    // A pop failure (an unreachable broker, a driver error) must not kill a long-running
                    // worker: report it, wait, and poll again. A single --once run reports it loudly instead.
                    if ($once) {
                        throw $e;
                    }

                    $this->reportPopFailure($e);
                    $this->pause($sleep);

                    continue;
                }

                if ($job === null) {
                    if ($once) {
                        return;
                    }
                    $this->pause($sleep);

                    continue;
                }

                $this->process($job, $queue ?? $this->config->queue(), $options->timeout);
                $jobsProcessed++;

                if ($once || $this->timedOut || $this->limitReached($options, $jobsProcessed)) {
                    return;
                }
            }
        } finally {
            $this->processControl->stopListening();
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Run one attempt of the job, then delete it, or release or fail it if it threw.
     *
     * With a timeout, a timer fails the job once it runs too long and ends the worker process:
     * a job stuck in a blocking call cannot be stopped safely from inside the process.
     *
     * @throws QueueException|SerializationException
     */
    private function process(
        JobInterface $job,
        string $queue,
        int $timeout,
    ): void {
        $this->timedOut = false;
        $error = null;

        if ($timeout > 0) {
            $this->processControl->startTimer(
                $timeout,
                fn () => $this->handleTimeout($job, $queue, $timeout),
            );
        }

        try {
            $this->jobRunner->run($job);
        } catch (Throwable $e) {
            $error = $e;
        } finally {
            if ($timeout > 0) {
                $this->processControl->cancelTimer();
            }
        }

        // The timeout handler has already released or failed the job
        if ($this->timedOut) {
            return;
        }

        if ($error === null) {
            $this->queue->delete($job->id);

            return;
        }

        $this->handleFailedJob($job, $error, $queue);
    }

    /**
     * Fail the running job with a JobTimedOutException, then end the worker process.
     *
     * The job is released for another attempt, or recorded in failed_jobs on its last attempt,
     * like a job that threw. The process then exits with EXIT_TIMED_OUT so its supervisor
     * starts a fresh worker.
     */
    private function handleTimeout(
        JobInterface $job,
        string $queue,
        int $timeout,
    ): void {
        $this->timedOut = true;
        $this->running = false;

        if ($job instanceof ContainerAwareJobInterface) {
            $job->releaseContainer();
        }

        $exception = JobTimedOutException::exceeded($job::class, $timeout);

        try {
            $this->handleFailedJob($job, $exception, $queue);
        } catch (Throwable $e) {
            error_log(sprintf(
                '[marko/queue] Could not release or fail timed-out job %s: %s: %s',
                $job->id,
                $e::class,
                $e->getMessage(),
            ));
        }

        error_log(sprintf(
            '[marko/queue] %s The worker is exiting with status %d so its supervisor can restart it.',
            $exception->getMessage(),
            self::EXIT_TIMED_OUT,
        ));

        $this->processControl->terminate(self::EXIT_TIMED_OUT);
    }

    /**
     * Whether the worker has reached its max-jobs or memory limit and should exit.
     */
    private function limitReached(
        WorkerOptions $options,
        int $jobsProcessed,
    ): bool {
        if ($options->maxJobs > 0 && $jobsProcessed >= $options->maxJobs) {
            return true;
        }

        return $options->memory > 0
            && $this->processControl->memoryUsage() >= $options->memory * 1024 * 1024;
    }

    /**
     * Wait before polling again once every queue is empty.
     */
    protected function pause(
        int $seconds,
    ): void {
        sleep($seconds);
    }

    /**
     * Validate the priority list. Null becomes [null], which pops the driver's default queue.
     *
     * @param array<mixed>|null $queues
     * @return list<?string>
     * @throws QueueException
     */
    private function resolveQueueNames(
        ?array $queues,
    ): array {
        if ($queues === null) {
            return [null];
        }

        if ($queues === []) {
            throw QueueException::noQueuesGiven();
        }

        if (!array_is_list($queues)) {
            throw QueueException::invalidQueueName('the queue list must be a list, not a keyed array');
        }

        foreach ($queues as $queue) {
            if (!is_string($queue) || trim($queue) === '') {
                throw QueueException::invalidQueueName(
                    'every queue name must be a non-blank string; got ' . var_export($queue, true),
                );
            }
        }

        /** @var list<string> $queues */
        return $queues;
    }

    /**
     * Pop from each queue in priority order and return the first job found with its queue name.
     *
     * @param list<?string> $queueNames
     * @return array{0: ?JobInterface, 1: ?string}
     */
    private function popNextJob(
        array $queueNames,
    ): array {
        foreach ($queueNames as $queueName) {
            $job = $this->queue->pop($queueName);

            if ($job !== null) {
                return [$job, $queueName];
            }
        }

        return [null, null];
    }

    /**
     * Log a failed pop to PHP's error log (STDERR for a CLI worker) so it is visible without stopping the worker.
     */
    private function reportPopFailure(
        Throwable $e,
    ): void {
        error_log(sprintf(
            '[marko/queue] Worker could not pop a job, retrying after the sleep interval: %s: %s',
            $e::class,
            $e->getMessage(),
        ));
    }

    /**
     * Seconds to wait before retrying a job whose latest attempt failed.
     *
     * Uses the job's $backoff, then the `queue.backoff` config, then the exponential
     * curve 2^attempts * 10. A list backoff is indexed by attempt and its last value repeats.
     *
     * @throws QueueException When the job's $backoff or the queue.backoff config is invalid
     */
    public function backoffFor(
        JobInterface $job,
    ): int {
        $backoff = $job->backoff !== null
            ? $this->backoffValidator->validate($job->backoff, 'job ' . $job::class)
            : $this->config->backoff();

        if ($backoff === null) {
            return (int) pow(2, $job->attempts) * 10;
        }

        if (is_int($backoff)) {
            return $backoff;
        }

        return $backoff[min(max($job->attempts, 1), count($backoff)) - 1];
    }

    /**
     * Release a failed job for another attempt, or record it in failed_jobs on its last attempt.
     *
     * A job whose backoff is invalid cannot be released, so it is recorded in failed_jobs with
     * both its own error and the backoff error, and the worker keeps running: one bad job class
     * must not stop every worker. Fix the backoff, then queue:retry the job.
     *
     * @throws QueueException|SerializationException
     */
    private function handleFailedJob(
        JobInterface $job,
        Throwable $e,
        string $queue,
    ): void {
        $maxAttempts = $job->maxAttempts ?? $this->config->maxAttempts();

        if ($job->attempts >= $maxAttempts) {
            $this->storeFailedJob($job, $e, $queue);

            return;
        }

        try {
            $delay = $this->backoffFor($job);
        } catch (QueueException $backoffError) {
            $this->storeFailedJob(
                $job,
                $e,
                $queue,
                "\n\nJob could not be retried: " . $backoffError->getMessage() . ' ' . $backoffError->getContext(),
            );

            return;
        }

        $this->queue->release($job, $delay);
    }

    /**
     * Store the job in failed_jobs and delete it from the queue.
     *
     * A job whose payload cannot be serialized (for example one holding a closure) is still
     * recorded, with a placeholder payload of its class and the serialization error, and
     * deleted from the queue, so the worker keeps running. queue:retry refuses that row.
     *
     * @throws SerializationException
     */
    private function storeFailedJob(
        JobInterface $job,
        Throwable $e,
        string $queue,
        string $note = '',
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
        $this->queue->delete($job->id);
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
