<?php

declare(strict_types=1);

namespace Marko\Database\Migration;

use Closure;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Database\Attributes\WithoutTransaction;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionalDdlInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\LostConnectionException;
use Marko\Database\Exceptions\MigrationException;
use ReflectionObject;
use Throwable;

/**
 * Runs the work of one migration, in a transaction when the connection and the migration allow it.
 *
 * Each migration gets its own transaction, covering its up() or down() and the write to the migrations
 * table, so a failure leaves neither the schema change nor the record behind. A migration runs without one
 * when the connection does not implement TransactionalDdlInterface (or reports no support) or when the
 * migration class carries #[WithoutTransaction]. The failure message states which of these applied.
 */
readonly class MigrationTransaction
{
    /**
     * @param Closure(): void $work The migration's up() or down() and the write to the migrations table
     * @throws MigrationException If the work fails or the transaction cannot be committed
     */
    public function run(
        string $name,
        Migration $migration,
        ConnectionInterface $connection,
        Closure $work,
    ): void {
        $outcome = null;
        $workReturned = false;

        try {
            $transaction = $this->transactionFor($migration, $connection);

            if ($transaction !== null) {
                $outcome = MigrationFailureOutcome::RolledBack;
                $transaction->transaction(function () use ($work, &$workReturned): void {
                    $work();
                    $workReturned = true;
                });
            } else {
                $outcome = MigrationFailureOutcome::MayBePartlyApplied;
                $work();
            }
        } catch (MigrationException $e) {
            throw $e;
        } catch (Throwable $e) {
            if (
                $workReturned
                && $this->lostConnection($e)
            ) {
                $outcome = MigrationFailureOutcome::Unknown;
            }

            throw MigrationException::migrationFailed($name, $e->getMessage(), $e, $outcome);
        }
    }

    /**
     * Whether the connection was lost, in the error or anywhere in its chain.
     */
    private function lostConnection(
        Throwable $error,
    ): bool {
        for ($current = $error; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof LostConnectionException) {
                return true;
            }
        }

        return false;
    }

    /**
     * The transaction to run the migration in, or null when it must run without one.
     *
     * A plugin on the connection hands out a proxy that hides the capabilities of the connection behind
     * it, so the proxy is unwrapped until one that has them is found. The migration itself keeps using the
     * connection it was given, so plugins still see every statement.
     */
    private function transactionFor(
        Migration $migration,
        ConnectionInterface $connection,
    ): ?TransactionInterface {
        $target = $connection;

        while (
            $target instanceof PluginInterceptedInterface
            && !$this->canRunInTransaction($target)
        ) {
            $target = $target->getPluginTarget();
        }

        if (!$this->canRunInTransaction($target)) {
            return null;
        }

        if ((new ReflectionObject($migration))->getAttributes(WithoutTransaction::class) !== []) {
            return null;
        }

        return $target;
    }

    private function canRunInTransaction(
        object $connection,
    ): bool {
        return $connection instanceof TransactionalDdlInterface
            && $connection->supportsTransactionalDdl()
            && $connection instanceof TransactionInterface;
    }
}
