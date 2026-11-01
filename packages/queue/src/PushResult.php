<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Queue\Exceptions\QueueException;

/**
 * The outcome of QueueInterface::push() or later(): the job was queued with an id, or it was skipped with a reason.
 */
readonly class PushResult
{
    /**
     * @throws QueueException When the fields contradict each other
     */
    public function __construct(
        public ?string $id,
        public bool $pushed,
        public ?string $skipReason,
    ) {
        if ($pushed && ($id === null || $skipReason !== null)) {
            throw QueueException::invalidPushResult('a pushed result needs an id and no skip reason');
        }

        if (!$pushed && ($skipReason === null || $id !== null)) {
            throw QueueException::invalidPushResult('a skipped result needs a skip reason and no id');
        }
    }

    /**
     * The job was queued under the given id.
     *
     * @throws QueueException
     */
    public static function pushed(
        string $id,
    ): self {
        return new self(id: $id, pushed: true, skipReason: null);
    }

    /**
     * The job was not queued, for the given reason.
     *
     * @throws QueueException
     */
    public static function skipped(
        string $reason,
    ): self {
        return new self(id: null, pushed: false, skipReason: $reason);
    }
}
