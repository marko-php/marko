<?php

declare(strict_types=1);

namespace Marko\Database\Migration;

use Marko\Database\Connection\ConnectionInterface;

/**
 * Base class for database migrations.
 */
abstract class Migration
{
    /**
     * Run the migration.
     */
    abstract public function up(ConnectionInterface $connection): void;

    /**
     * Reverse the migration.
     */
    abstract public function down(ConnectionInterface $connection): void;

    /**
     * Execute a SQL statement.
     *
     * Helper method for common migration operations.
     */
    protected function execute(
        ConnectionInterface $connection,
        string $sql,
    ): int {
        return $connection->execute($sql);
    }
}
