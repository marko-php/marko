<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Fixtures\Retry;

use PDO;
use PDOException;

/**
 * An in-memory SQLite PDO (real transactions and savepoints) that records
 * transaction statements and can be scripted to fail them, so the retry
 * logic of PgSqlConnection::transaction() can be tested without a server.
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

    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        parent::exec('CREATE TABLE items (name TEXT)');
    }

    public function exec(
        string $statement,
    ): int|false {
        // SQLite has no SET NAMES or SET TIME ZONE; ignore the session statements.
        if (str_starts_with($statement, 'SET ')) {
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

        return parent::exec($statement);
    }

    public function beginTransaction(): bool
    {
        $this->statements[] = 'BEGIN';

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

        return parent::rollBack();
    }
}
