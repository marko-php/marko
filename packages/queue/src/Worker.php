<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Log\Contracts\LoggerInterface;
use Marko\Queue\Exceptions\FailedJobNotStoredException;
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
        private readonly FailedJobHandlerInterface $failedJobHandler,
        private readonly QueueConfig $config,
        private readonly JobRunnerInterface $jobRunner,
        private readonly RetryPolicy $retryPolicy,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly ProcessControlInterface $processControl = new PcntlProcessControl(),
    ) {}

    /**
     * A failed job the failed-job repository cannot store ends the run: the worker logs the
     * FailedJobNotStoredException at critical level and throws it, so queue:work exits with status 1 instead
     * of pulling more jobs while the failed-job store is broken. The job is left in its queue and is failed
     * again once its reservation expires.
     *
     * @param list<string>|null $queues Queue names in priority order, or null for the default queue
     * @throws FailedJobNotStoredException|QueueException|SerializationException
     */
    public function work(
        ?array $queues = null,
        bool $once = false,
        int $sleep = 3,
        WorkerOptions $options = new WorkerOptions(),
    ): WorkerStopReason {
        $queueNames = $this->resolveQueueNames($queues);
        $this->running = true;
        $startedAt = $this->clock->now();
        $conditions = $options->buildStopConditions($this->clock);
        $jobsProcessed = 0;

        // SIGTERM/SIGINT only stop the loop, so the current job finishes before the worker exits
        $this->processControl->listenForTermination($this->stop(...));

        try {
            while (true) {
                try {
                    [$job, $queue] = $this->popNextJob($queueNames);
                } catch (FailedJobNotStoredException $e) {
                    // A driver failed a job at pop time and could not store it: the store is broken, so stop
                    throw $e;
                } catch (Throwable $e) {
                    // A pop failure (an unreachable broker, a driver error) must not kill a long-running
                    // worker: report it, wait, and poll again. A single --once run reports it loudly instead.
                    if ($once) {
                        throw $e;
                    }

                    $this->reportPopFailure($e);
                    $job = null;
                }

                if ($job === null) {
                    if ($once) {
                        return $this->stopWith(WorkerStopReason::Once);
                    }

                    $this->pause($sleep);
                } else {
                    $this->process($job, $queue ?? $this->config->queue(), $options->timeout);
                    $jobsProcessed++;

                    // The timeout handler has already logged the stop and ended the process
                    if ($this->timedOut) {
                        return WorkerStopReason::TimedOut;
                    }

                    if ($once) {
                        return $this->stopWith(WorkerStopReason::Once);
                    }
                }

                $reason = $this->checkStopConditions(
                    $conditions,
                    new WorkerRunState($startedAt, $jobsProcessed, $this->processControl->memoryUsage()),
                );

                if ($reason !== null) {
                    return $this->stopWith($reason);
                }
            }
        } catch (FailedJobNotStoredException $e) {
            $this->reportFailedJobNotStored($e);

            throw $e;
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
     * The job's own $timeout replaces the worker's. With a timeout, a timer fails the job once it runs
     * too long and ends the worker process: a job stuck in a blocking call cannot be stopped safely
     * from inside the process.
     *
     * @throws QueueException|SerializationException
     */
    private function process(
        JobInterface $job,
        string $queue,
        int $workerTimeout,
    ): void {
        $this->timedOut = false;
        $error = null;
        $timeout = $job->timeout ?? $workerTimeout;

        if ($job->timeout !== null && $job->timeout > 0 && !$this->guardJobTimeout($job, $job->timeout, $queue)) {
            return;
        }

        if ($timeout > 0) {
            $this->processControl->startTimer(
                $timeout,
                fn () => $this->handleTimeout($job, $queue, $timeout),
            );
        }

        try {
            $this->jobRunner->run($job, $queue);
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
     *
     * A FailedJobNotStoredException is logged at critical level rather than thrown: an exception thrown
     * from the signal handler would surface inside the interrupted job. The process exits either way.
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
        } catch (FailedJobNotStoredException $e) {
            $this->reportFailedJobNotStored($e);
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                '[marko/queue] Could not release or fail timed-out job %s: %s: %s',
                $job->id,
                $e::class,
                $e->getMessage(),
            ));
        }

        $this->logger->error('[marko/queue] ' . $exception->getMessage());

        // terminate() does not return, so the stop is logged here rather than when work() returns
        $this->stopWith(WorkerStopReason::TimedOut);
        $this->processControl->terminate(WorkerStopReason::TimedOut->exitCode());
    }

    /**
     * Check a job's own timeout before it runs. Returns false when the job was failed instead of run.
     *
     * The worker's timeout is checked against each queue's retry_after when queue:work starts, but a job's
     * own timeout is only known now, and it is checked against the retry_after of the queue it was popped
     * from. A job that may run past retry_after would be reclaimed and run twice at
     * once, so it is failed for good with an error that says to raise retry_after. A timeout that cannot
     * be enforced because ext-pcntl is missing is logged as a warning, and the job runs unprotected.
     *
     * @throws SerializationException
     */
    private function guardJobTimeout(
        JobInterface $job,
        int $timeout,
        string $queue,
    ): bool {
        $retryAfter = $this->config->retryAfter($queue);

        if ($timeout >= $retryAfter) {
            $this->failJob(
                $job,
                QueueException::jobTimeoutNotBelowRetryAfter(
                    $job::class,
                    $timeout,
                    $retryAfter,
                    $queue,
                    $this->config->retryAfterKey($queue),
                ),
                $queue,
            );

            return false;
        }

        if (!$this->processControl->isSupported()) {
            $this->logger->warning(sprintf(
                "[marko/queue] Job '%s' sets a %d-second timeout, but ext-pcntl is not loaded,"
                    . ' so the timeout is not enforced. Install ext-pcntl to enforce job timeouts.',
                $job::class,
                $timeout,
            ));
        }

        return true;
    }

    /**
     * The first reason to stop: a stop signal, then each condition in order. Null keeps the worker going.
     *
     * @param list<WorkerStopConditionInterface> $conditions
     */
    private function checkStopConditions(
        array $conditions,
        WorkerRunState $state,
    ): ?WorkerStopReason {
        if (!$this->running) {
            return WorkerStopReason::Signal;
        }

        foreach ($conditions as $condition) {
            $reason = $condition->check($state);

            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
    }

    /**
     * Log why the worker is stopping, as one info line through the injected logger.
     */
    private function stopWith(
        WorkerStopReason $reason,
    ): WorkerStopReason {
        $this->logger->info('[marko/queue] Worker stopping: ' . $reason->message());

        return $reason;
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
     * The driver may block waiting for a job only when the worker has a single queue: blocking on one queue of
     * a priority list would hold back jobs arriving on the others.
     *
     * @param list<?string> $queueNames
     * @return array{0: ?JobInterface, 1: ?string}
     */
    private function popNextJob(
        array $queueNames,
    ): array {
        $block = count($queueNames) === 1;

        foreach ($queueNames as $queueName) {
            $job = $this->queue->pop($queueName, $block);

            if ($job !== null) {
                return [$job, $queueName];
            }
        }

        return [null, null];
    }

    /**
     * Log a failed pop as an error so it is visible without stopping the worker.
     */
    private function reportPopFailure(
        Throwable $e,
    ): void {
        $this->logger->error(sprintf(
            '[marko/queue] Worker could not pop a job, retrying after the sleep interval: %s: %s',
            $e::class,
            $e->getMessage(),
        ));
    }

    /**
     * Log a failed job that could not be stored at critical level, with its context and suggestion, so the
     * reason the worker stopped reaches the logs as well as queue:work's output.
     */
    private function reportFailedJobNotStored(
        FailedJobNotStoredException $e,
    ): void {
        $this->logger->critical(sprintf(
            '[marko/queue] Worker stopping: %s %s %s',
            $e->getMessage(),
            $e->getContext(),
            $e->getSuggestion(),
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
     * then delete it from the queue. A job the handler could not store is not deleted.
     *
     * @throws FailedJobNotStoredException|SerializationException
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
