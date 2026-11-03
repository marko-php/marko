<?php

declare(strict_types=1);

use Marko\Database\Exceptions\QueryException;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteTestDatabase;
use Marko\Mcp\Tools\Runtime\Adapters\MarkoQueryConnection;

/*
 * MarkoQueryConnection::readOnlyQuery() against a real SQLite connection: reads work, the database refuses a write
 * that reaches it, and query_only is switched off again afterwards, even after a failed statement.
 */

dataset('mcp sqlite kinds', [SqliteTestDatabase::FILE, SqliteTestDatabase::MEMORY]);

function sqliteProbeDatabase(
    string $kind,
): SqliteTestDatabase {
    $database = new SqliteTestDatabase($kind);
    $database->connection->execute('CREATE TABLE mcp_read_only_probe (id INTEGER PRIMARY KEY, name TEXT)');
    $database->connection->execute("INSERT INTO mcp_read_only_probe (id, name) VALUES (1, 'Alice')");

    return $database;
}

function sqliteQueryOnly(
    SqliteTestDatabase $database,
): int {
    return (int) $database->connection->query('PRAGMA query_only')[0]['query_only'];
}

it('runs mcp read-only queries on sqlite and refuses writes', function (string $kind): void {
    $database = sqliteProbeDatabase($kind);

    try {
        $adapter = new MarkoQueryConnection($database->connection);

        $rows = $adapter->readOnlyQuery('SELECT name FROM mcp_read_only_probe WHERE id = :id', ['id' => 1]);

        expect($rows)->toBe([['name' => 'Alice']])
            ->and(sqliteQueryOnly($database))->toBe(0);

        expect(fn () => $adapter->readOnlyQuery("INSERT INTO mcp_read_only_probe (id, name) VALUES (2, 'Bob')"))
            ->toThrow(QueryException::class)
            ->and(sqliteQueryOnly($database))->toBe(0)
            ->and($database->connection->inTransaction())->toBeFalse()
            ->and($database->connection->query('SELECT COUNT(*) AS total FROM mcp_read_only_probe')[0]['total'])
            ->toBe(1);

        $database->connection->execute("INSERT INTO mcp_read_only_probe (id, name) VALUES (3, 'Carol')");

        expect($adapter->readOnlyQuery('SELECT COUNT(*) AS total FROM mcp_read_only_probe'))
            ->toBe([['total' => 2]]);
    } finally {
        $database->dispose();
    }
})->with('mcp sqlite kinds');
