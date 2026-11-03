<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Integration;

use Marko\Database\Exceptions\LockTimeoutException;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteTestDatabase;
use Throwable;

/*
 * Two connections to one temp file. The contender uses a short busy_timeout, so a blocked write fails
 * fast instead of waiting out the 5000 ms default. :memory: databases are private to a connection,
 * so these cases need a file.
 */

describe('SQLite concurrency', function (): void {
    it("lets a reader query during another connection's open write transaction in WAL mode", function (): void {
        SqliteTestDatabase::using(SqliteTestDatabase::FILE, function (SqliteTestDatabase $database): void {
            $writer = $database->connection;
            $writer->execute('CREATE TABLE ledger (id INTEGER PRIMARY KEY, amount INTEGER NOT NULL)');
            $writer->execute('INSERT INTO ledger (amount) VALUES (10)');
            $reader = $database->connect(['busy_timeout' => 50]);

            $writer->execute('BEGIN IMMEDIATE');
            $writer->execute('INSERT INTO ledger (amount) VALUES (20)');
            $duringWrite = $reader->query('SELECT SUM(amount) AS total FROM ledger');
            $writer->execute('COMMIT');
            $afterCommit = $reader->query('SELECT SUM(amount) AS total FROM ledger');
            $reader->disconnect();

            expect((int) $duringWrite[0]['total'])->toBe(10)
                ->and((int) $afterCommit[0]['total'])->toBe(30);
        });
    });

    it('throws the translated busy exception when a second writer exceeds busy_timeout', function (): void {
        SqliteTestDatabase::using(SqliteTestDatabase::FILE, function (SqliteTestDatabase $database): void {
            $holder = $database->connection;
            $holder->execute('CREATE TABLE ledger (id INTEGER PRIMARY KEY, amount INTEGER NOT NULL)');
            $contender = $database->connect(['busy_timeout' => 50]);

            $holder->execute('BEGIN IMMEDIATE');
            $holder->execute('INSERT INTO ledger (amount) VALUES (1)');

            $caught = null;

            try {
                $contender->execute('INSERT INTO ledger (amount) VALUES (2)');
            } catch (Throwable $e) {
                $caught = $e;
            }

            $holder->execute('COMMIT');
            $contender->execute('INSERT INTO ledger (amount) VALUES (2)');
            $total = $holder->query('SELECT COUNT(*) AS total FROM ledger');
            $contender->disconnect();

            expect($caught)->toBeInstanceOf(LockTimeoutException::class)
                ->and((int) $total[0]['total'])->toBe(2);
        });
    });
});
