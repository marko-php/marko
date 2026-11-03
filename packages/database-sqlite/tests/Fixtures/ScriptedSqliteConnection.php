<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Fixtures;

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\TransactionBackoff;
use Marko\Database\Sqlite\Connection\SqliteConnection;
use PDO;

/**
 * A SqliteConnection running on a ScriptedSqlitePdo.
 */
class ScriptedSqliteConnection extends SqliteConnection
{
    public readonly ScriptedSqlitePdo $scripted;

    public function __construct(
        TransactionBackoff $transactionBackoff = new TransactionBackoff(),
    ) {
        $this->scripted = new ScriptedSqlitePdo();

        parent::__construct(
            DatabaseConfig::fromArray(['driver' => 'sqlite', 'database' => ':memory:']),
            transactionBackoff: $transactionBackoff,
        );
    }

    protected function createPdo(
        string $dsn,
        array $options,
    ): PDO {
        return $this->scripted;
    }
}
