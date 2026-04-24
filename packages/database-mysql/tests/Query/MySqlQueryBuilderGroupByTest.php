<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Query;

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Database\MySql\Query\MySqlQueryBuilder;

/**
 * Recording connection that captures the last SQL and bindings passed to query().
 */
function makeRecordingConnection(string &$lastSql, array &$lastBindings): MySqlConnection
{
    return new class ($lastSql, $lastBindings) extends MySqlConnection
    {
        public function __construct(
            public string &$lastSql,
            public array &$lastBindings,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(string $sql, array $bindings = []): array
        {
            $this->lastSql = $sql;
            $this->lastBindings = $bindings;

            return [];
        }

        public function execute(string $sql, array $bindings = []): int
        {
            return 0;
        }

        public function lastInsertId(): int
        {
            return 0;
        }

        public function beginTransaction(): void {}

        public function commit(): void {}

        public function rollback(): void {}
    };
}

describe('MySqlQueryBuilder GROUP BY / HAVING', function (): void {
    it('adds a single GROUP BY column to the compiled SQL', function (): void {
        $sql = '';
        $bindings = [];
        $conn = makeRecordingConnection($sql, $bindings);

        (new MySqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->groupBy('status')
            ->get();

        expect($sql)->toBe('SELECT `status` FROM `orders` GROUP BY `status`');
    });

    it('adds multiple GROUP BY columns via variadic arguments', function (): void {
        $sql = '';
        $bindings = [];
        $conn = makeRecordingConnection($sql, $bindings);

        (new MySqlQueryBuilder($conn))
            ->table('orders')
            ->select('status', 'country')
            ->groupBy('status', 'country')
            ->get();

        expect($sql)->toBe('SELECT `status`, `country` FROM `orders` GROUP BY `status`, `country`');
    });

    it('applies HAVING with a parameterized expression', function (): void {
        $sql = '';
        $bindings = [];
        $conn = makeRecordingConnection($sql, $bindings);

        (new MySqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->groupBy('status')
            ->having('COUNT(*) > ?', [5])
            ->get();

        expect($sql)->toBe('SELECT `status` FROM `orders` GROUP BY `status` HAVING COUNT(*) > ?')
            ->and($bindings)->toBe([5]);
    });

    it('binds HAVING parameters safely against SQL injection', function (): void {
        $sql = '';
        $bindings = [];
        $conn = makeRecordingConnection($sql, $bindings);

        $userInput = '5; DROP TABLE orders; --';

        (new MySqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->groupBy('status')
            ->having('COUNT(*) > ?', [$userInput])
            ->get();

        // The expression itself is safe (no semicolons/comments allowed),
        // and the user-supplied value goes through a binding, not inline SQL.
        expect($sql)->toBe('SELECT `status` FROM `orders` GROUP BY `status` HAVING COUNT(*) > ?')
            ->and($bindings)->toBe([$userInput]);
    });

    it('composes GROUP BY with WHERE in the correct SQL order (WHERE ... GROUP BY ... HAVING)', function (): void {
        $sql = '';
        $bindings = [];
        $conn = makeRecordingConnection($sql, $bindings);

        (new MySqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->where('active', '=', 1)
            ->groupBy('status')
            ->having('COUNT(*) > ?', [2])
            ->get();

        expect($sql)->toBe(
            'SELECT `status` FROM `orders` WHERE `active` = ? GROUP BY `status` HAVING COUNT(*) > ?',
        )
            ->and($bindings)->toBe([1, 2]);
    });

    it('composes GROUP BY with ORDER BY and LIMIT correctly', function (): void {
        $sql = '';
        $bindings = [];
        $conn = makeRecordingConnection($sql, $bindings);

        (new MySqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->groupBy('status')
            ->orderBy('status', 'ASC')
            ->limit(10)
            ->get();

        expect($sql)->toBe(
            'SELECT `status` FROM `orders` GROUP BY `status` ORDER BY `status` ASC LIMIT 10',
        );
    });

    it('validates GROUP BY column identifiers against the alias/identifier whitelist (reuses the whitelist introduced in task 006)', function (): void {
        $sql = '';
        $bindings = [];
        $conn = makeRecordingConnection($sql, $bindings);

        expect(
            fn () => (new MySqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->groupBy('status; DROP TABLE orders--')
            ->get(),
        )->toThrow(InvalidColumnException::class);
    });

    it('rejects HAVING expressions containing semicolons or SQL comments', function (): void {
        $sql = '';
        $bindings = [];
        $conn = makeRecordingConnection($sql, $bindings);

        expect(
            fn () => (new MySqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->groupBy('status')
            ->having('COUNT(*) > 1; DROP TABLE orders--', [])
            ->get(),
        )->toThrow(InvalidColumnException::class);

        expect(
            fn () => (new MySqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->groupBy('status')
            ->having('COUNT(*) > 1 -- comment', [])
            ->get(),
        )->toThrow(InvalidColumnException::class);

        expect(
            fn () => (new MySqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->groupBy('status')
            ->having('COUNT(*) > /* comment */ 1', [])
            ->get(),
        )->toThrow(InvalidColumnException::class);
    });

    it('composes HAVING bindings with WHERE bindings in the correct positional order at execute time', function (): void {
        $sql = '';
        $bindings = [];
        $conn = makeRecordingConnection($sql, $bindings);

        (new MySqlQueryBuilder($conn))
            ->table('orders')
            ->select('status', 'country')
            ->where('active', '=', 1)
            ->groupBy('status', 'country')
            ->having('COUNT(*) BETWEEN ? AND ?', [3, 10])
            ->get();

        expect($sql)->toBe(
            'SELECT `status`, `country` FROM `orders` WHERE `active` = ? GROUP BY `status`, `country` HAVING COUNT(*) BETWEEN ? AND ?',
        )
            ->and($bindings)->toBe([1, 3, 10]);
    });
});
