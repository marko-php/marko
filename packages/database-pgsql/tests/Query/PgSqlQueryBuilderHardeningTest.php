<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Query;

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\PgSql\Query\PgSqlQueryBuilder;
use ReflectionClass;

describe('PgSqlQueryBuilder hardening', function (): void {
    it('escapes an embedded double-quote in a column name when quoting an identifier', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        $reflection = new ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');

        expect($method->invoke($builder, 'col"name'))->toBe('"col""name"');
    });

    it('rejects a where column containing a double-quote or SQL comment', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        expect(fn () => $builder->where('col"name', '=', 'value'))
            ->toThrow(InvalidColumnException::class)
            ->and(fn () => $builder->where('col--name', '=', 'value'))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects a where operator not in the allowlist', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        expect(fn () => $builder->where('status', 'INVALID_OP', 'value'))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects an orWhere operator not in the allowlist', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        expect(fn () => $builder->orWhere('status', 'DROP', 'value'))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects a whereIn column that is not a valid identifier', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        expect(fn () => $builder->whereIn('col"injection', [1, 2]))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects a whereNull column that is not a valid identifier', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        expect(fn () => $builder->whereNull('col--injection'))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects an orderBy column that is not a valid identifier', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        expect(fn () => $builder->orderBy('col"injection'))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects a join operator not in the allowlist', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        expect(fn () => $builder->join('orders', 'users.id', 'INVALID_OP', 'orders.user_id'))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects a join table or column that is not a valid identifier', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        expect(fn () => $builder->join('orders"drop', 'users.id', '=', 'orders.user_id'))
            ->toThrow(InvalidColumnException::class)
            ->and(fn () => $builder->join('orders', 'users.id"drop', '=', 'orders.user_id'))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects a table name that is not a valid identifier', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        expect(fn () => $builder->table('users"injection'))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects an insert column key that is not a valid identifier', function (): void {
        $connection = new MockConnection(
            queryReturn: [['id' => 1]],
        );
        $builder = (new PgSqlQueryBuilder($connection))->table('users');

        expect(fn () => $builder->insert(['col"injection' => 'value']))
            ->toThrow(InvalidColumnException::class);
    });

    it('rejects an update column key that is not a valid identifier', function (): void {
        $connection = new MockConnection();
        $builder = (new PgSqlQueryBuilder($connection))->table('users');

        expect(fn () => $builder->update(['col"injection' => 'value']))
            ->toThrow(InvalidColumnException::class);
    });

    it(
        'still compiles a JSON-path where column (data->name) without rejecting it as an invalid identifier',
        function (): void {
            $connection = new MockConnection(
                queryReturn: [['id' => 1]],
            );
            $builder = new PgSqlQueryBuilder($connection);
    
            $builder->table('users')
                ->where('data->name', '=', 'John')
                ->get();
    
            expect($connection->lastQuerySql)->toContain('->')
                ->and($connection->lastQuerySql)->toContain('WHERE');
        }
    );

    it('still allows count() with no column (COUNT(*))', function (): void {
        $connection = new MockConnection(
            queryReturn: [['aggregate' => 5]],
        );
        $builder = new PgSqlQueryBuilder($connection);

        $result = $builder->table('users')->count();

        expect($result)->toBe(5)
            ->and($connection->lastQuerySql)->toContain('COUNT(*)');
    });

    it('still builds a valid SELECT with a qualified identifier and an allowlisted operator', function (): void {
        $connection = new MockConnection(
            queryReturn: [['id' => 1, 'name' => 'Alice']],
        );
        $builder = new PgSqlQueryBuilder($connection);

        $builder->table('users')
            ->where('users.status', '=', 'active')
            ->get();

        expect($connection->lastQuerySql)->toBe('SELECT * FROM "users" WHERE "users"."status" = ?')
            ->and($connection->lastQueryBindings)->toBe(['active']);
    });
});
