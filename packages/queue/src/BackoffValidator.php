<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Queue\Exceptions\QueueException;

/**
 * Checks a retry backoff value: a non-negative int (fixed seconds) or a non-empty
 * list of non-negative ints (seconds per attempt). Null is handled by the caller.
 */
class BackoffValidator
{
    /**
     * @param string $source Where the value came from, e.g. "job App\SendEmail" or "config queue.backoff"
     * @return int|list<int>
     * @throws QueueException
     */
    public function validate(
        mixed $backoff,
        string $source,
    ): array|int {
        if (is_int($backoff)) {
            if ($backoff < 0) {
                throw QueueException::invalidBackoff($source, "delay must not be negative; got $backoff");
            }

            return $backoff;
        }

        if (!is_array($backoff)) {
            throw QueueException::invalidBackoff(
                $source,
                'expected an int, a list of ints, or null; got ' . get_debug_type($backoff),
            );
        }

        if (
            $backoff === []
            || !array_is_list($backoff)
        ) {
            throw QueueException::invalidBackoff($source, 'a backoff array must be a non-empty list of ints');
        }

        foreach ($backoff as $delay) {
            if (
                !is_int($delay)
                || $delay < 0
            ) {
                throw QueueException::invalidBackoff(
                    $source,
                    'every backoff list entry must be a non-negative int; got ' . var_export($delay, true),
                );
            }
        }

        /** @var list<int> $backoff */
        return $backoff;
    }
}
