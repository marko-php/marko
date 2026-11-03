<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Sqlite\Connection\SqliteConnection;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteTestDatabase;
use Marko\Testing\Database\TestDatabase;
use Marko\Testing\Database\TruncateDatabase;

use function Marko\Testing\Tests\databaseAppPath;
use function Marko\Testing\Tests\withAppEnv;

/**
 * A TestDatabase over a real SQLite connection; the booted fixture app only supplies the environment.
 */
class SqliteBackedTestDatabase extends TestDatabase
{
    public function __construct(
        private readonly SqliteConnection $sqlite,
    ) {
        parent::__construct(Application::boot(databaseAppPath()));
    }

    public function connection(): ConnectionInterface
    {
        return $this->sqlite;
    }

    public function transaction(): TransactionInterface
    {
        return $this->sqlite;
    }
}

readonly class FixedTablesTruncateDatabase extends TruncateDatabase
{
    /**
     * @param list<string> $tables
     */
    public function __construct(
        TestDatabase $database,
        private array $tables,
    ) {
        parent::__construct($database);
    }

    public function tables(): array
    {
        return $this->tables;
    }
}

describe('TruncateDatabase on a real SQLite database', function (): void {
    it('empties the tables and restarts autoincrement counters', function (): void {
        SqliteTestDatabase::using(SqliteTestDatabase::FILE, function (SqliteTestDatabase $sqlite): void {
            $connection = $sqlite->connection;
            $connection->execute('CREATE TABLE venues (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
            $connection->execute(
                'CREATE TABLE shows (id INTEGER PRIMARY KEY AUTOINCREMENT, venue_id INTEGER NOT NULL REFERENCES venues (id))',
            );
            $connection->execute('INSERT INTO venues (name) VALUES (?), (?)', ['A', 'B']);
            $connection->execute('INSERT INTO shows (venue_id) VALUES (?), (?)', [1, 2]);

            $truncate = new FixedTablesTruncateDatabase(
                new SqliteBackedTestDatabase($connection),
                ['shows', 'venues'],
            );
            withAppEnv('testing', fn () => $truncate->truncate());
            $connection->execute('INSERT INTO venues (name) VALUES (?)', ['C']);

            expect($connection->query('SELECT COUNT(*) AS n FROM shows')[0]['n'])->toBe(0)
                ->and($connection->query('SELECT id FROM venues'))->toBe([['id' => 1]])
                ->and($connection->query('PRAGMA foreign_keys')[0]['foreign_keys'])->toBe(1);
        });
    });

    it('truncates a database that has no autoincrement table', function (): void {
        SqliteTestDatabase::using(SqliteTestDatabase::MEMORY, function (SqliteTestDatabase $sqlite): void {
            $connection = $sqlite->connection;
            $connection->execute('CREATE TABLE venues (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
            $connection->execute('INSERT INTO venues (name) VALUES (?)', ['A']);

            $truncate = new FixedTablesTruncateDatabase(new SqliteBackedTestDatabase($connection), ['venues']);
            withAppEnv('testing', fn () => $truncate->truncate());

            expect($connection->query('SELECT COUNT(*) AS n FROM venues')[0]['n'])->toBe(0);
        });
    });
});
