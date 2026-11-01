<?php

declare(strict_types=1);

namespace Marko\Queue;

use DateTimeImmutable;
use Marko\Queue\Exceptions\QueueException;

abstract class Job implements JobInterface
{
    public private(set) ?string $id = null;

    public private(set) int $attempts = 0;

    public protected(set) ?int $maxAttempts = null;

    /**
     * @var int|list<int>|null
     */
    public protected(set) array|int|null $backoff = null;

    public protected(set) ?int $timeout = null;

    public protected(set) ?int $maxExceptions = null;

    public private(set) int $exceptions = 0;

    public private(set) ?DateTimeImmutable $retryDeadline = null;

    public private(set) ?int $releaseDelay = null;

    public function setId(
        string $id,
    ): void {
        $this->id = $id;
    }

    public function incrementAttempts(): void
    {
        $this->attempts++;
    }

    public function resetAttempts(): void
    {
        $this->attempts = 0;
    }

    public function incrementExceptions(): void
    {
        $this->exceptions++;
    }

    public function setRetryDeadline(
        DateTimeImmutable $deadline,
    ): void {
        $this->retryDeadline = $deadline;
    }

    /**
     * Record a release request. Nothing is thrown: the worker acts on the request after handle() returns.
     *
     * @throws QueueException When the delay is negative
     */
    public function release(
        int $delaySeconds = 0,
    ): void {
        if ($delaySeconds < 0) {
            throw QueueException::invalidReleaseDelay(static::class, $delaySeconds);
        }

        $this->releaseDelay = $delaySeconds;
    }

    public function clearRelease(): void
    {
        $this->releaseDelay = null;
    }

    public function serialize(): string
    {
        return serialize($this);
    }

    public static function unserialize(
        string $data,
    ): static {
        return unserialize($data);
    }
}
