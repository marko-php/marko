<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Query;

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\PgSql\Query\PgSqlQueryBuilder;

describe('PgSqlQueryBuilder GROUP BY / HAVING', function (): void {
    it('adds a single GROUP BY column to the compiled SQL', function (): void {
        $conn = new MockConnection();

        (new PgSqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->groupBy('status')
            ->get();

        expect($conn->lastQuerySql)->toBe('SELECT "status" FROM "orders" GROUP BY "status"');
    });

    it('adds multiple GROUP BY columns via variadic arguments', function (): void {
        $conn = new MockConnection();

        (new PgSqlQueryBuilder($conn))
            ->table('orders')
            ->select('status', 'country')
            ->groupBy('status', 'country')
            ->get();

        expect($conn->lastQuerySql)->toBe('SELECT "status", "country" FROM "orders" GROUP BY "status", "country"');
    });

    it('applies HAVING with a parameterized expression', function (): void {
        $conn = new MockConnection();

        (new PgSqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->groupBy('status')
            ->having('COUNT(*) > ?', [5])
            ->get();

        expect($conn->lastQuerySql)->toBe('SELECT "status" FROM "orders" GROUP BY "status" HAVING COUNT(*) > ?')
            ->and($conn->lastQueryBindings)->toBe([5]);
    });

    it('binds HAVING parameters safely against SQL injection', function (): void {
        $conn = new MockConnection();
        $userInput = '5; DROP TABLE orders; --';

        (new PgSqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->groupBy('status')
            ->having('COUNT(*) > ?', [$userInput])
            ->get();

        expect($conn->lastQuerySql)->toBe('SELECT "status" FROM "orders" GROUP BY "status" HAVING COUNT(*) > ?')
            ->and($conn->lastQueryBindings)->toBe([$userInput]);
    });

    it('composes GROUP BY with WHERE in the correct SQL order (WHERE ... GROUP BY ... HAVING)', function (): void {
        $conn = new MockConnection();

        (new PgSqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->where('active', '=', 1)
            ->groupBy('status')
            ->having('COUNT(*) > ?', [2])
            ->get();

        expect($conn->lastQuerySql)->toBe(
            'SELECT "status" FROM "orders" WHERE "active" = ? GROUP BY "status" HAVING COUNT(*) > ?',
        )
            ->and($conn->lastQueryBindings)->toBe([1, 2]);
    });

    it('composes GROUP BY with ORDER BY and LIMIT correctly', function (): void {
        $conn = new MockConnection();

        (new PgSqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->groupBy('status')
            ->orderBy('status', 'ASC')
            ->limit(10)
            ->get();

        expect($conn->lastQuerySql)->toBe(
            'SELECT "status" FROM "orders" GROUP BY "status" ORDER BY "status" ASC LIMIT 10',
        );
    });

    it('validates GROUP BY column identifiers against the alias/identifier whitelist (reuses the whitelist introduced in task 006)', function (): void {
        $conn = new MockConnection();

        expect(
            fn () => (new PgSqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->groupBy('status; DROP TABLE orders--')
            ->get(),
        )->toThrow(InvalidColumnException::class);
    });

    it('rejects HAVING expressions containing semicolons or SQL comments', function (): void {
        $conn = new MockConnection();

        expect(
            fn () => (new PgSqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->groupBy('status')
            ->having('COUNT(*) > 1; DROP TABLE orders--', [])
            ->get(),
        )->toThrow(InvalidColumnException::class);

        expect(
            fn () => (new PgSqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->groupBy('status')
            ->having('COUNT(*) > 1 -- comment', [])
            ->get(),
        )->toThrow(InvalidColumnException::class);

        expect(
            fn () => (new PgSqlQueryBuilder($conn))
            ->table('orders')
            ->select('status')
            ->groupBy('status')
            ->having('COUNT(*) > /* comment */ 1', [])
            ->get(),
        )->toThrow(InvalidColumnException::class);
    });

    it('composes HAVING bindings with WHERE bindings in the correct positional order at execute time', function (): void {
        $conn = new MockConnection();

        (new PgSqlQueryBuilder($conn))
            ->table('orders')
            ->select('status', 'country')
            ->where('active', '=', 1)
            ->groupBy('status', 'country')
            ->having('COUNT(*) BETWEEN ? AND ?', [3, 10])
            ->get();

        expect($conn->lastQuerySql)->toBe(
            'SELECT "status", "country" FROM "orders" WHERE "active" = ? GROUP BY "status", "country" HAVING COUNT(*) BETWEEN ? AND ?',
        )
            ->and($conn->lastQueryBindings)->toBe([1, 3, 10]);
    });
});
