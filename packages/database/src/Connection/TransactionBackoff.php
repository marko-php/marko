<?php

declare(strict_types=1);

namespace Marko\Database\Connection;

use Closure;
use Marko\Core\Sleep\SleeperInterface;
use Marko\Core\Sleep\UsleepSleeper;
use Marko\Database\Exceptions\TransactionConflictException;
use Marko\Database\Exceptions\TransactionException;
use Random\Randomizer;

/**
 * Waits between the attempts of a retried transaction() so that competing
 * transactions stop colliding again at the same instant.
 *
 * The backoff argument of transaction() selects the delay:
 *
 * - null (the default): exponential backoff with full jitter, a random delay
 *   between 0 and min(500, 10 * 2 ** ($attempt - 1)) milliseconds
 * - int: a fixed delay in milliseconds (0 retries at once)
 * - Closure(int $attempt, TransactionConflictException $conflict): int, a
 *   custom delay in milliseconds
 *
 * $attempt is the number of the attempt that just failed, starting at 1.
 */
class TransactionBackoff
{
    public const int BASE_DELAY_MILLISECONDS = 10;

    public const int MAX_DELAY_MILLISECONDS = 500;

    public function __construct(
        private readonly SleeperInterface $sleeper = new UsleepSleeper(),
        private readonly Randomizer $randomizer = new Randomizer(),
    ) {}

    /**
     * Reject a backoff that can never produce a valid delay, before the
     * transaction begins.
     *
     * @throws TransactionException When $backoff is a negative int
     */
    public function validate(
        int|Closure|null $backoff,
    ): void {
        if (is_int($backoff) && $backoff < 0) {
            throw TransactionException::invalidBackoff($backoff);
        }
    }

    /**
     * Sleep for the delay $backoff gives after failed attempt $attempt.
     *
     * @throws TransactionException When a Closure backoff returns a negative delay
     */
    public function wait(
        int $attempt,
        int|Closure|null $backoff,
        TransactionConflictException $conflict,
    ): void {
        $this->sleeper->sleep($this->delay($attempt, $backoff, $conflict));
    }

    /**
     * @throws TransactionException
     */
    private function delay(
        int $attempt,
        int|Closure|null $backoff,
        TransactionConflictException $conflict,
    ): int {
        if ($backoff === null) {
            return $this->randomizer->getInt(0, $this->defaultUpperBound($attempt));
        }

        if (is_int($backoff)) {
            $this->validate($backoff);

            return $backoff;
        }

        $delay = $backoff($attempt, $conflict);

        if (!is_int($delay) || $delay < 0) {
            throw TransactionException::invalidBackoffDelay($delay);
        }

        return $delay;
    }

    private function defaultUpperBound(
        int $attempt,
    ): int {
        $bound = self::BASE_DELAY_MILLISECONDS;

        for ($i = 1; $i < $attempt && $bound < self::MAX_DELAY_MILLISECONDS; $i++) {
            $bound *= 2;
        }

        return min($bound, self::MAX_DELAY_MILLISECONDS);
    }
}
