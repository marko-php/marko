<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Fixtures;

use PDO;
use PDOException;

/**
 * An in-memory SQLite PDO that records the transaction statements sent through exec() and the use
 * of PDO's own transaction methods, and can be scripted to fail a statement.
 */
class ScriptedSqlitePdo extends PDO
{
    /**
     * @var list<string>
     */
    public array $statements = [];

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
        if (preg_match('/^(BEGIN|COMMIT|ROLLBACK|SAVEPOINT|RELEASE)/', $statement) === 1) {
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
        $this->statements[] = 'PDO::beginTransaction';

        return parent::beginTransaction();
    }

    public function commit(): bool
    {
        $this->statements[] = 'PDO::commit';

        return parent::commit();
    }

    public function rollBack(): bool
    {
        $this->statements[] = 'PDO::rollBack';

        return parent::rollBack();
    }
}
