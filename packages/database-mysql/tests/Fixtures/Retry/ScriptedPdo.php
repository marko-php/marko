<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Fixtures\Retry;

use PDO;
use PDOException;

/**
 * An in-memory SQLite PDO (real transactions and savepoints) that records
 * transaction statements and can be scripted to fail them, so the retry
 * logic of MySqlConnection::transaction() can be tested without a server.
 *
 * endTransactionOnServer() mimics what MySQL does on a deadlock: the server
 * rolls the whole transaction back, so inTransaction() turns false, ROLLBACK
 * fails with "There is no active transaction" and the savepoints are gone.
 */
class ScriptedPdo extends PDO
{
    /**
     * @var list<string>
     */
    public array $statements = [];

    /**
     * Failures thrown by the next COMMIT calls, in order.
     *
     * @var list<PDOException>
     */
    public array $commitFailures = [];

    /**
     * Failures thrown by the next exec() of a statement starting with the key.
     *
     * @var array<string, PDOException>
     */
    public array $execFailures = [];

    private bool $serverEndedTransaction = false;

    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        parent::exec('CREATE TABLE items (name TEXT)');
    }

    public function endTransactionOnServer(): void
    {
        parent::rollBack();
        $this->serverEndedTransaction = true;
    }

    public function inTransaction(): bool
    {
        return !$this->serverEndedTransaction
            && parent::inTransaction();
    }

    public function exec(
        string $statement,
    ): int|false {
        // SQLite has no SET NAMES; ignore the encoding query.
        if (str_starts_with($statement, 'SET NAMES')) {
            return 0;
        }

        if (preg_match('/^(SAVEPOINT|RELEASE|ROLLBACK)/', $statement) === 1) {
            $this->statements[] = $statement;
        }

        foreach ($this->execFailures as $prefix => $failure) {
            if (str_starts_with($statement, $prefix)) {
                unset($this->execFailures[$prefix]);

                throw $failure;
            }
        }

        if (
            $this->serverEndedTransaction
            && preg_match('/^(RELEASE|ROLLBACK TO) SAVEPOINT (\w+)/', $statement, $matches) === 1
        ) {
            throw new PDOException(
                "SQLSTATE[42000]: Syntax error or access violation: 1305 SAVEPOINT $matches[2] does not exist",
            );
        }

        return parent::exec($statement);
    }

    public function beginTransaction(): bool
    {
        $this->statements[] = 'BEGIN';
        $this->serverEndedTransaction = false;

        return parent::beginTransaction();
    }

    public function commit(): bool
    {
        $this->statements[] = 'COMMIT';
        $failure = array_shift($this->commitFailures);

        if ($failure !== null) {
            throw $failure;
        }

        return parent::commit();
    }

    public function rollBack(): bool
    {
        $this->statements[] = 'ROLLBACK';

        if ($this->serverEndedTransaction) {
            throw new PDOException('There is no active transaction');
        }

        return parent::rollBack();
    }
}
