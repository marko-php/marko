<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Fixtures;

use Marko\Database\Sqlite\Connection\SqliteConnection;
use PDO;

/**
 * Exposes the PDO the connection opened, and can pretend to run on an older SQLite library.
 */
class CapturingSqliteConnection extends SqliteConnection
{
    public ?PDO $pdoCreated = null;

    public ?string $versionOverride = null;

    protected function createPdo(
        string $dsn,
        array $options,
    ): PDO {
        return $this->pdoCreated = parent::createPdo($dsn, $options);
    }

    protected function sqliteVersion(
        PDO $pdo,
    ): string {
        return $this->versionOverride ?? parent::sqliteVersion($pdo);
    }
}
