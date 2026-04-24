<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Query;

use Marko\Database\Exceptions\UnionShapeMismatchException;
use Marko\Database\PgSql\Query\PgSqlQueryBuilder;
use Marko\Database\Query\QueryBuilderInterface;
use ReflectionClass;

describe('PgSqlQueryBuilder', function (): void {
    it('implements QueryBuilderInterface', function (): void {
        $reflection = new ReflectionClass(PgSqlQueryBuilder::class);

        expect($reflection->implementsInterface(QueryBuilderInterface::class))->toBeTrue();
    });

    it('quotes identifiers with double quotes', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        $reflection = new ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');

        expect($method->invoke($builder, 'users'))->toBe('"users"')
            ->and($method->invoke($builder, 'id'))->toBe('"id"')
            ->and($method->invoke($builder, 'created_at'))->toBe('"created_at"');
    });

    it('builds SELECT queries with column selection', function (): void {
        $connection = new MockConnection(
            queryReturn: [['id' => 1, 'name' => 'John', 'email' => 'john@example.com']],
        );

        $builder = new PgSqlQueryBuilder($connection);
        $result = $builder
            ->table('users')
            ->select('id', 'name', 'email')
            ->get();

        expect($connection->lastQuerySql)->toBe('SELECT "id", "name", "email" FROM "users"')
            ->and($connection->lastQueryBindings)->toBe([])
            ->and($result)->toBe([
                ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ]);
    });

    it('builds WHERE clauses with parameter binding', function (): void {
        $connection = new MockConnection(
            queryReturn: [['id' => 1, 'status' => 'active']],
        );

        $builder = new PgSqlQueryBuilder($connection);
        $result = $builder
            ->table('users')
            ->where('status', '=', 'active')
            ->get();

        expect($connection->lastQuerySql)->toBe('SELECT * FROM "users" WHERE "status" = ?')
            ->and($connection->lastQueryBindings)->toBe(['active'])
            ->and($result)->toBe([
                ['id' => 1, 'status' => 'active'],
            ]);
    });

    it('builds WHERE IN clauses correctly', function (): void {
        $connection = new MockConnection(
            queryReturn: [
                ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Jane'],
                ['id' => 3, 'name' => 'Bob'],
            ],
        );

        $builder = new PgSqlQueryBuilder($connection);
        $result = $builder
            ->table('users')
            ->whereIn('id', [1, 2, 3])
            ->get();

        expect($connection->lastQuerySql)->toBe('SELECT * FROM "users" WHERE "id" IN (?, ?, ?)')
            ->and($connection->lastQueryBindings)->toBe([1, 2, 3])
            ->and($result)->toHaveCount(3);
    });

    it('builds JOIN clauses with proper syntax', function (): void {
        $connection = new MockConnection();

        $builder = new PgSqlQueryBuilder($connection);
        $builder
            ->table('posts')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->get();

        expect($connection->lastQuerySql)->toBe(
            'SELECT * FROM "posts" INNER JOIN "users" ON "posts"."user_id" = "users"."id"',
        );
    });

    it('builds ORDER BY with ASC/DESC', function (): void {
        $connection = new MockConnection();

        $builder = new PgSqlQueryBuilder($connection);
        $builder
            ->table('users')
            ->orderBy('created_at', 'DESC')
            ->get();

        expect($connection->lastQuerySql)->toBe('SELECT * FROM "users" ORDER BY "created_at" DESC');
    });

    it('builds LIMIT and OFFSET clauses', function (): void {
        $connection = new MockConnection();

        $builder = new PgSqlQueryBuilder($connection);
        $builder
            ->table('users')
            ->limit(10)
            ->offset(20)
            ->get();

        expect($connection->lastQuerySql)->toBe('SELECT * FROM "users" LIMIT 10 OFFSET 20');
    });

    it('builds INSERT statements with RETURNING id', function (): void {
        $connection = new MockConnection(
            queryReturn: [['id' => 42]],
        );

        $builder = new PgSqlQueryBuilder($connection);
        $id = $builder
            ->table('users')
            ->insert([
                'name' => 'John',
                'email' => 'john@example.com',
            ]);

        expect($connection->lastQuerySql)->toBe(
            'INSERT INTO "users" ("name", "email") VALUES (?, ?) RETURNING "id"',
        )
            ->and($connection->lastQueryBindings)->toBe(['John', 'john@example.com'])
            ->and($id)->toBe(42);
    });

    it('builds UPDATE statements with WHERE clause', function (): void {
        $connection = new MockConnection(executeReturn: 1);

        $builder = new PgSqlQueryBuilder($connection);
        $affected = $builder
            ->table('users')
            ->where('id', '=', 1)
            ->update([
                'name' => 'Jane',
                'status' => 'inactive',
            ]);

        expect($connection->lastExecuteSql)->toBe(
            'UPDATE "users" SET "name" = ?, "status" = ? WHERE "id" = ?',
        )
            ->and($connection->lastExecuteBindings)->toBe(['Jane', 'inactive', 1])
            ->and($affected)->toBe(1);
    });

    it('builds DELETE statements with WHERE clause', function (): void {
        $connection = new MockConnection(executeReturn: 1);

        $builder = new PgSqlQueryBuilder($connection);
        $affected = $builder
            ->table('users')
            ->where('id', '=', 1)
            ->delete();

        expect($connection->lastExecuteSql)->toBe('DELETE FROM "users" WHERE "id" = ?')
            ->and($connection->lastExecuteBindings)->toBe([1])
            ->and($affected)->toBe(1);
    });

    it('returns last insert ID using RETURNING', function (): void {
        $connection = new MockConnection(
            queryReturn: [['id' => 123]],
        );

        $builder = new PgSqlQueryBuilder($connection);
        $id = $builder
            ->table('posts')
            ->insert(['title' => 'My Post']);

        expect($connection->lastQuerySql)->toBe(
            'INSERT INTO "posts" ("title") VALUES (?) RETURNING "id"',
        )
            ->and($id)->toBe(123);
    });

    it('returns affected row count after update/delete', function (): void {
        $connection = new MockConnection(executeReturn: 5);

        $builder = new PgSqlQueryBuilder($connection);
        $affected = $builder
            ->table('users')
            ->where('role', '=', 'guest')
            ->update(['status' => 'inactive']);

        expect($connection->lastExecuteSql)->toBe(
            'UPDATE "users" SET "status" = ? WHERE "role" = ?',
        )
            ->and($connection->lastExecuteBindings)->toBe(['inactive', 'guest'])
            ->and($affected)->toBe(5);
    });

    it('executes raw queries with parameter binding', function (): void {
        $connection = new MockConnection(
            queryReturn: [['id' => 1, 'name' => 'John', 'age' => 25]],
        );

        $builder = new PgSqlQueryBuilder($connection);
        $result = $builder->raw('SELECT * FROM users WHERE age > $1', [18]);

        expect($connection->lastQuerySql)->toBe('SELECT * FROM users WHERE age > $1')
            ->and($connection->lastQueryBindings)->toBe([18])
            ->and($result)->toBe([
                ['id' => 1, 'name' => 'John', 'age' => 25],
            ]);
    });

    it('emits SELECT DISTINCT when distinct() is called', function (): void {
        $connection = new MockConnection();

        $builder = new PgSqlQueryBuilder($connection);
        $builder
            ->table('users')
            ->select('status')
            ->distinct()
            ->get();

        expect($connection->lastQuerySql)->toBe('SELECT DISTINCT "status" FROM "users"');
    });

    it('combines two queries with UNION producing deduplicated rows', function (): void {
        $connection = new MockConnection(
            queryReturn: [['name' => 'Alice'], ['name' => 'Bob']],
        );

        $left = new PgSqlQueryBuilder($connection);
        $left->table('users')->select('name');

        $right = new PgSqlQueryBuilder(new MockConnection());
        $right->table('admins')->select('name');

        $left->union($right)->get();

        expect($connection->lastQuerySql)->toBe(
            '(SELECT "name" FROM "users") UNION (SELECT "name" FROM "admins")',
        );
    });

    it('throws UnionShapeMismatchException when the two queries select different numbers of columns', function (): void {
        $connection = new MockConnection();

        $left = new PgSqlQueryBuilder($connection);
        $left->table('users')->select('name', 'email');

        $right = new PgSqlQueryBuilder(new MockConnection());
        $right->table('admins')->select('name');

        expect(fn () => $left->union($right))
            ->toThrow(UnionShapeMismatchException::class);
    });

    it('combines two queries with UNION ALL preserving duplicates', function (): void {
        $connection = new MockConnection(
            queryReturn: [['name' => 'Alice'], ['name' => 'Alice']],
        );

        $left = new PgSqlQueryBuilder($connection);
        $left->table('users')->select('name');

        $right = new PgSqlQueryBuilder(new MockConnection());
        $right->table('admins')->select('name');

        $left->unionAll($right)->get();

        expect($connection->lastQuerySql)->toBe(
            '(SELECT "name" FROM "users") UNION ALL (SELECT "name" FROM "admins")',
        );
    });

    it('composes UNION with ORDER BY applied to the combined result', function (): void {
        $connection = new MockConnection(
            queryReturn: [['name' => 'Alice'], ['name' => 'Bob']],
        );

        $left = new PgSqlQueryBuilder($connection);
        $left->table('users')->select('name');

        $right = new PgSqlQueryBuilder(new MockConnection());
        $right->table('admins')->select('name');

        $left->union($right)->orderBy('name', 'ASC')->get();

        expect($connection->lastQuerySql)->toBe(
            '(SELECT "name" FROM "users") UNION (SELECT "name" FROM "admins") ORDER BY "name" ASC',
        );
    });

    it('composes UNION with LIMIT applied to the combined result', function (): void {
        $connection = new MockConnection(
            queryReturn: [['name' => 'Alice']],
        );

        $left = new PgSqlQueryBuilder($connection);
        $left->table('users')->select('name');

        $right = new PgSqlQueryBuilder(new MockConnection());
        $right->table('admins')->select('name');

        $left->union($right)->limit(1)->get();

        expect($connection->lastQuerySql)->toBe(
            '(SELECT "name" FROM "users") UNION (SELECT "name" FROM "admins") LIMIT 1',
        );
    });

    it('parameterizes bindings from both sides of the UNION safely', function (): void {
        $connection = new MockConnection(
            queryReturn: [['name' => 'Alice'], ['name' => 'Bob']],
        );

        $left = new PgSqlQueryBuilder($connection);
        $left->table('users')->select('name')->where('status', '=', 'active');

        $right = new PgSqlQueryBuilder(new MockConnection());
        $right->table('admins')->select('name')->where('role', '=', 'superadmin');

        $left->union($right)->get();

        expect($connection->lastQuerySql)->toBe(
            '(SELECT "name" FROM "users" WHERE "status" = ?) UNION (SELECT "name" FROM "admins" WHERE "role" = ?)',
        )
            ->and($connection->lastQueryBindings)->toBe(['active', 'superadmin']);
    });

    it('quotes both the column and the alias using driver-specific identifier quoting', function (): void {
        $connection = new MockConnection();
        $builder = new PgSqlQueryBuilder($connection);

        $builder
            ->table('users')
            ->select('users.name as author_name')
            ->get();

        expect($connection->lastQuerySql)->toBe(
            'SELECT "users"."name" AS "author_name" FROM "users"',
        );
    });
});
