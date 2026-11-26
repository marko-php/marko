<?php

declare(strict_types=1);

namespace Marko\Database\Connection;

use Closure;
use Marko\Database\Exceptions\LostConnectionException;
use Throwable;

/**
 * The one place that holds the lost-connection rule, composed by the drivers.
 *
 * Trade-off: when the reply to a statement was lost, the statement may have
 * run on the server before the retry, so a retried statement can run twice.
 */
class LostConnectionRecovery
{
    /**
     * @template T
     * @param Closure(): T $attempt
     * @param Closure(): void $dropConnection
     * @return T
     * @throws LostConnectionException|Throwable
     */
    public function run(
        Closure $attempt,
        Closure $dropConnection,
        TransactionState $transactionState,
    ): mixed {
        if ($transactionState->isLost()) {
            throw LostConnectionException::transactionAlreadyLost();
        }

        try {
            return $attempt();
        } catch (LostConnectionException $lost) {
            if ($transactionState->level() > 0) {
                $this->connectionLost($dropConnection, $transactionState);

                throw LostConnectionException::duringTransaction($lost);
            }

            $dropConnection();

            try {
                return $attempt();
            } catch (LostConnectionException $stillLost) {
                $dropConnection();

                throw $stillLost;
            }
        }
    }

    /**
     * Drop the dead connection and, when a transaction is open, mark it lost.
     *
     * @param Closure(): void $dropConnection
     */
    public function connectionLost(
        Closure $dropConnection,
        TransactionState $transactionState,
    ): void {
        $dropConnection();
        $transactionState->markLost();
    }
}
