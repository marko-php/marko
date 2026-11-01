<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Queue\Exceptions\FailedJobNotStoredException;
use Marko\Queue\Exceptions\SerializationException;
use Throwable;

/**
 * The single path every final job failure goes through: the worker (last attempt, timeout,
 * invalid backoff), the database driver (attempts used up by crashed reservations) and the
 * sync driver all call it.
 *
 * The caller deletes the job from its queue afterwards; the handler never touches the queue.
 */
interface FailedJobHandlerInterface
{
    /**
     * Store the job as failed, call failed() on a FailableJobInterface job, then dispatch
     * JobFailedEvent.
     *
     * When the job cannot be stored, it throws without calling failed() or dispatching the event. The
     * caller must not delete the job then: it stays in its queue and is failed again later.
     *
     * @param string $note Extra text appended to the stored exception
     * @throws FailedJobNotStoredException|SerializationException
     */
    public function handle(
        JobInterface $job,
        string $queue,
        Throwable $exception,
        string $note = '',
    ): void;
}
