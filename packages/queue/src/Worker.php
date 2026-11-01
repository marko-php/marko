<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Queue\Exceptions\JobTimedOutException;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\Exceptions\SerializationException;
use Throwable;

class Worker implements WorkerInterface
{
    /** Exit status of a worker process that ended because a job exceeded its timeout. */
    public const int EXIT_TIMED_OUT = 1;

    private bool $running = false;

    private bool $timedOut = false;

    public function __construct(
        private readonly QueueInterface $queue,
        private readonly FailedJobHandlerInterface $failedJobHandler,
        private readonly QueueConfig $config,
        private readonly JobRunnerInterface $jobRunner,
        private readonly RetryPolicy $retryPolicy,
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
     * Run one attempt of the job, then delete it, or release or fail it if it threw or asked to be released.
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

        if ($error !== null) {
            $this->handleFailedJob($job, $error, $queue);

            return;
        }

        if ($job->releaseDelay !== null) {
            $this->handleReleasedJob($job, $queue);

            return;
        }

        $this->queue->delete($job->id);
    }

    /**
     * Fail the running job with a JobTimedOutException, then end the worker process.
     *
     * The job is released for another attempt, or failed on its last attempt, like a job that
     * threw. This runs inside the pcntl alarm handler, so a FailableJobInterface job's failed()
     * runs in signal context too. The process then exits with EXIT_TIMED_OUT so its supervisor
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

        // A timed-out attempt ended in an exception, so it counts toward maxExceptions like one that threw
        $job->incrementExceptions();
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
     * Release a job that threw for another attempt, or fail it for good once RetryPolicy says a limit
     * is reached (retry deadline, maxExceptions, maxAttempts) or its backoff is invalid.
     *
     * The job's own exception is stored, with the reason it was not retried appended. A job whose
     * backoff is invalid is failed rather than stopping the worker: one bad job class must not stop
     * every worker. Fix the backoff, then queue:retry the job.
     *
     * @throws QueueException|SerializationException
     */
    private function handleFailedJob(
        JobInterface $job,
        Throwable $e,
        string $queue,
    ): void {
        $decision = $this->retryPolicy->decide($job, $e);

        if ($decision->failure !== null) {
            $this->failJob(
                $job,
                $e,
                $queue,
                "\n\nJob could not be retried: " . $decision->failure->getMessage() . ' '
                    . $decision->failure->getContext(),
            );

            return;
        }

        $this->queue->release($job, (int) $decision->delay);
    }

    /**
     * Release a job that asked for it with release() inside handle(), after the delay it asked for.
     *
     * A deliberate release is not an exception, so it does not count toward maxExceptions. A job past
     * its retry deadline, or out of attempts without a deadline, is failed for good instead.
     *
     * @throws QueueException|SerializationException
     */
    private function handleReleasedJob(
        JobInterface $job,
        string $queue,
    ): void {
        $decision = $this->retryPolicy->decide($job);

        if ($decision->failure !== null) {
            $this->failJob($job, $decision->failure, $queue);

            return;
        }

        $this->queue->release($job, (int) $decision->delay);
    }

    /**
     * Hand the job to the failed-job handler (store it, call failed(), dispatch JobFailedEvent),
     * then delete it from the queue.
     *
     * @throws SerializationException
     */
    private function failJob(
        JobInterface $job,
        Throwable $e,
        string $queue,
        string $note = '',
    ): void {
        $this->failedJobHandler->handle($job, $queue, $e, $note);
        $this->queue->delete($job->id);
    }
}
