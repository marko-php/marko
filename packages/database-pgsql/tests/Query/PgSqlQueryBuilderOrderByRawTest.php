<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Query;

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\PgSql\Query\PgSqlQueryBuilder;

describe('PgSqlQueryBuilder orderByRaw', function (): void {
    it('appends a raw expression to the order clause without quoting', function (): void {
        $conn = new MockConnection();

        (new PgSqlQueryBuilder($conn))
            ->table('products')
            ->orderByRaw('LOWER(name)')
            ->get();

        expect($conn->lastQuerySql)->toBe('SELECT * FROM "products" ORDER BY LOWER(name) ASC');
    });

    it('rejects expressions containing semicolons', function (): void {
        $conn = new MockConnection();

        expect(
            fn () => (new PgSqlQueryBuilder($conn))
                ->table('products')
                ->orderByRaw('name; DROP TABLE products--'),
        )->toThrow(InvalidColumnException::class);
    });

    it('rejects expressions containing SQL comments (-- or /*)', function (): void {
        $conn = new MockConnection();

        expect(
            fn () => (new PgSqlQueryBuilder($conn))
                ->table('products')
                ->orderByRaw('name -- comment'),
        )->toThrow(InvalidColumnException::class);

        expect(
            fn () => (new PgSqlQueryBuilder($conn))
                ->table('products')
                ->orderByRaw('name /* comment */'),
        )->toThrow(InvalidColumnException::class);
    });

    it('preserves direction asc or desc on the emitted ORDER BY', function (): void {
        $connAsc = new MockConnection();

        (new PgSqlQueryBuilder($connAsc))
            ->table('products')
            ->orderByRaw('LOWER(name)', 'asc')
            ->get();

        expect($connAsc->lastQuerySql)->toBe('SELECT * FROM "products" ORDER BY LOWER(name) ASC');

        $connDesc = new MockConnection();

        (new PgSqlQueryBuilder($connDesc))
            ->table('products')
            ->orderByRaw('LOWER(name)', 'desc')
            ->get();

        expect($connDesc->lastQuerySql)->toBe('SELECT * FROM "products" ORDER BY LOWER(name) DESC');
    });

    it('composes correctly with a regular orderBy call before or after', function (): void {
        $connRawFirst = new MockConnection();

        (new PgSqlQueryBuilder($connRawFirst))
            ->table('products')
            ->orderByRaw('LOWER(name)')
            ->orderBy('price', 'DESC')
            ->get();

        expect($connRawFirst->lastQuerySql)->toBe(
            'SELECT * FROM "products" ORDER BY "price" DESC, LOWER(name) ASC',
        );

        $connRawAfter = new MockConnection();

        (new PgSqlQueryBuilder($connRawAfter))
            ->table('products')
            ->orderBy('price', 'DESC')
            ->orderByRaw('LOWER(name)')
            ->get();

        expect($connRawAfter->lastQuerySql)->toBe(
            'SELECT * FROM "products" ORDER BY "price" DESC, LOWER(name) ASC',
        );
    });

    it('emits a single ORDER BY clause with comma-separated entries for mixed regular and raw orders', function (): void {
        $conn = new MockConnection();

        (new PgSqlQueryBuilder($conn))
            ->table('products')
            ->orderBy('category', 'ASC')
            ->orderByRaw('LOWER(name)', 'ASC')
            ->orderBy('price', 'DESC')
            ->get();

        expect($conn->lastQuerySql)->toBe(
            'SELECT * FROM "products" ORDER BY "category" ASC, "price" DESC, LOWER(name) ASC',
        );
    });
});
