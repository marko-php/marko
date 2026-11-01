<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Query;

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\PgSql\Query\PgSqlQueryBuilder;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Marko\Database\Query\QueryBuilderInterface;

describe('PgSqlQueryBuilder whereNotIn', function (): void {
    it('compiles whereNotIn to a NOT IN clause with one binding per value', function (): void {
        $connection = new MockConnection();

        (new PgSqlQueryBuilder($connection))->table('studios')->whereNotIn('status', ['closed', 'paused'])->get();

        expect($connection->lastQuerySql)->toBe('SELECT * FROM "studios" WHERE "status" NOT IN (?, ?)')
            ->and($connection->lastQueryBindings)->toBe(['closed', 'paused']);
    });

    it('compiles whereNotIn with an empty list to a condition that matches every row', function (): void {
        $connection = new MockConnection();

        (new PgSqlQueryBuilder($connection))->table('studios')->whereNotIn('status', [])->get();

        expect($connection->lastQuerySql)->toBe('SELECT * FROM "studios" WHERE 1 = 1')
            ->and($connection->lastQueryBindings)->toBe([]);
    });

    it('rejects an invalid whereNotIn column', function (): void {
        expect(fn () => (new PgSqlQueryBuilder(new MockConnection()))->whereNotIn('status; DROP', [1]))
            ->toThrow(InvalidColumnException::class);
    });
});

describe('PgSqlQueryBuilder whereColumn', function (): void {
    it('compiles a column-to-column comparison with both sides quoted and nothing bound', function (): void {
        $connection = new MockConnection();

        (new PgSqlQueryBuilder($connection))
            ->table('posts')
            ->whereColumn('posts.updated_at', '>', 'posts.created_at')
            ->get();

        expect($connection->lastQuerySql)
            ->toBe('SELECT * FROM "posts" WHERE "posts"."updated_at" > "posts"."created_at"')
            ->and($connection->lastQueryBindings)->toBe([]);
    });

    it('rejects an invalid column on either side', function (): void {
        $builder = new PgSqlQueryBuilder(new MockConnection());

        expect(fn () => $builder->whereColumn('id = 1 OR 1', '=', 'id'))->toThrow(InvalidColumnException::class)
            ->and(fn () => $builder->whereColumn('id', '=', '1'))->toThrow(InvalidColumnException::class);
    });

    it('rejects operators that cannot compare two columns', function (): void {
        $builder = new PgSqlQueryBuilder(new MockConnection());

        expect(fn () => $builder->whereColumn('a', 'IN', 'b'))->toThrow(InvalidColumnException::class)
            ->and(fn () => $builder->whereColumn('a', 'LIKE', 'b'))->toThrow(InvalidColumnException::class);
    });
});

describe('PgSqlQueryBuilder aliased table', function (): void {
    it('compiles an aliased table in SELECT and accepts alias-qualified columns', function (): void {
        $connection = new MockConnection();

        (new PgSqlQueryBuilder($connection))->table('users', 'u')->where('u.id', '=', 5)->get();

        expect($connection->lastQuerySql)->toBe('SELECT * FROM "users" AS "u" WHERE "u"."id" = ?')
            ->and($connection->lastQueryBindings)->toBe([5]);
    });

    it('compiles an aliased table in aggregates, UPDATE and DELETE', function (): void {
        $connection = new MockConnection(queryReturn: [['aggregate' => 3]]);

        (new PgSqlQueryBuilder($connection))->table('users', 'u')->where('u.id', '>', 1)->count();
        expect($connection->lastQuerySql)->toBe('SELECT COUNT(*) as aggregate FROM "users" AS "u" WHERE "u"."id" > ?');

        (new PgSqlQueryBuilder($connection))->table('users', 'u')->where('u.id', '=', 1)->update(['name' => 'x']);
        expect($connection->lastExecuteSql)->toBe('UPDATE "users" AS "u" SET "name" = ? WHERE "u"."id" = ?');

        (new PgSqlQueryBuilder($connection))->table('users', 'u')->where('u.id', '=', 1)->delete();
        expect($connection->lastExecuteSql)->toBe('DELETE FROM "users" AS "u" WHERE "u"."id" = ?');
    });

    it('rejects an alias that is not a plain identifier', function (): void {
        $builder = new PgSqlQueryBuilder(new MockConnection());

        expect(fn () => $builder->table('users', 'u.x'))->toThrow(InvalidColumnException::class)
            ->and(fn () => $builder->table('users', 'u"; --'))->toThrow(InvalidColumnException::class);
    });
});

describe('PgSqlQueryBuilder whereExists', function (): void {
    it('compiles whereExists with a builder', function (): void {
        $connection = new MockConnection();
        $subquery = (new PgSqlQueryBuilder($connection))
            ->table('posts', 'p')
            ->whereColumn('p.user_id', '=', 'users.id');

        (new PgSqlQueryBuilder($connection))->table('users')->whereExists($subquery)->get();

        expect($connection->lastQuerySql)->toBe(
            'SELECT * FROM "users" WHERE EXISTS (SELECT * FROM "posts" AS "p" WHERE "p"."user_id" = "users"."id")',
        );
    });

    it('passes the callable a fresh builder on the same connection from the query builder factory', function (): void {
        $connection = new MockConnection();
        $received = null;

        (new PgSqlQueryBuilder($connection, new PgSqlQueryBuilderFactory($connection)))
            ->table('users')
            ->whereNotExists(function (QueryBuilderInterface $subquery) use (&$received): void {
                $received = $subquery;
                $subquery->table('posts', 'p')->whereColumn('p.user_id', '=', 'users.id');
            })
            ->get();

        expect($received)->toBeInstanceOf(PgSqlQueryBuilder::class)
            ->and($connection->lastQuerySql)->toBe(
                'SELECT * FROM "users" WHERE NOT EXISTS (SELECT * FROM "posts" AS "p" WHERE "p"."user_id" = "users"."id")',
            );
    });

    it('ignores the return value of an arrow-function callable', function (): void {
        $connection = new MockConnection();

        (new PgSqlQueryBuilder($connection))
            ->table('users')
            ->whereExists(fn (QueryBuilderInterface $q) => $q->table('posts')->where('published', '=', true))
            ->get();

        expect($connection->lastQuerySql)
            ->toBe('SELECT * FROM "users" WHERE EXISTS (SELECT * FROM "posts" WHERE "published" = ?)')
            ->and($connection->lastQueryBindings)->toBe([true]);
    });

    it('places subquery bindings after regular where bindings and before whereRaw bindings', function (): void {
        $connection = new MockConnection();

        (new PgSqlQueryBuilder($connection))
            ->table('users')
            ->whereRaw('LOWER(email) = ?', ['raw'])
            ->whereExists(fn (QueryBuilderInterface $q) => $q->table('posts', 'p')
                ->whereColumn('p.user_id', '=', 'users.id')
                ->where('p.title', '=', 'sub'))
            ->where('active', '=', 'outer')
            ->whereNotIn('role', ['banned'])
            ->get();

        expect($connection->lastQuerySql)->toBe(
            'SELECT * FROM "users" WHERE "active" = ? AND "role" NOT IN (?) AND EXISTS (SELECT * FROM "posts" AS "p" '
            . 'WHERE "p"."title" = ? AND "p"."user_id" = "users"."id") AND LOWER(email) = ?',
        )->and($connection->lastQueryBindings)->toBe(['outer', 'banned', 'sub', 'raw']);
    });

    it('nests EXISTS subqueries with their bindings in order', function (): void {
        $connection = new MockConnection();

        (new PgSqlQueryBuilder($connection))
            ->table('users')
            ->whereExists(fn (QueryBuilderInterface $posts) => $posts->table('posts', 'p')
                ->where('p.title', '=', 'one')
                ->whereExists(fn (QueryBuilderInterface $comments) => $comments->table('comments', 'c')
                    ->whereColumn('c.post_id', '=', 'p.id')
                    ->where('c.body', '=', 'two')))
            ->get();

        expect($connection->lastQuerySql)->toBe(
            'SELECT * FROM "users" WHERE EXISTS (SELECT * FROM "posts" AS "p" WHERE "p"."title" = ? AND EXISTS '
            . '(SELECT * FROM "comments" AS "c" WHERE "c"."body" = ? AND "c"."post_id" = "p"."id"))',
        )->and($connection->lastQueryBindings)->toBe(['one', 'two']);
    });

    it('honors whereExists in aggregates and DELETE', function (): void {
        $connection = new MockConnection(queryReturn: [['aggregate' => 1]]);
        $exists = fn (QueryBuilderInterface $q) => $q->table('posts', 'p')
            ->whereColumn('p.user_id', '=', 'users.id')
            ->where('p.flagged', '=', true);

        (new PgSqlQueryBuilder($connection))->table('users')->whereExists($exists)->count();
        expect($connection->lastQuerySql)->toBe(
            'SELECT COUNT(*) as aggregate FROM "users" WHERE EXISTS (SELECT * FROM "posts" AS "p" '
            . 'WHERE "p"."flagged" = ? AND "p"."user_id" = "users"."id")',
        )->and($connection->lastQueryBindings)->toBe([true]);

        (new PgSqlQueryBuilder($connection))->table('users')->whereNotExists($exists)->delete();
        expect($connection->lastExecuteSql)->toStartWith('DELETE FROM "users" WHERE NOT EXISTS (')
            ->and($connection->lastExecuteBindings)->toBe([true]);
    });
});
