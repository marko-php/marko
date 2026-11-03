<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Query;

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Exceptions\LockException;
use Marko\Database\Sqlite\Connection\SqliteConnection;
use Marko\Database\Sqlite\Exceptions\InsertReturningException;
use Marko\Database\Sqlite\Query\SqliteQueryBuilder;

function sqliteBuilderConnection(): SqliteConnection
{
    $connection = new SqliteConnection(DatabaseConfig::fromArray(['driver' => 'sqlite', 'database' => ':memory:']));
    $connection->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT UNIQUE, name TEXT)');

    return $connection;
}

describe('SqliteQueryBuilder', function (): void {
    it('quotes table and column identifiers with double quotes', function (): void {
        $connection = new SqliteRecordingConnection();

        new SqliteQueryBuilder($connection)->table('users')->select('id', 'name AS n')
            ->where('email', '=', 'a')
            ->get();

        expect($connection->lastQuerySql)->toBe('SELECT "id", "name" AS "n" FROM "users" WHERE "email" = ?');
    });

    it('compiles insert with RETURNING and returns the inserted row values', function (): void {
        $connection = new SqliteRecordingConnection(queryReturn: [['id' => 42]]);

        $id = new SqliteQueryBuilder($connection)->table('users')->insert(['email' => 'a@b.c']);

        expect($id)->toBe(42)
            ->and($connection->lastQuerySql)->toBe('INSERT INTO "users" ("email") VALUES (?) RETURNING "id"');

        $real = sqliteBuilderConnection();
        $builder = new SqliteQueryBuilder($real);

        expect($builder->table('users')->insert(['email' => 'x@y.z']))->toBe(1)
            ->and(new SqliteQueryBuilder($real)->table('users')->insert(['email' => 'q@y.z']))->toBe(2);
    });

    it('throws when the RETURNING result lacks the primary key column', function (): void {
        $connection = new SqliteRecordingConnection();

        expect(fn () => new SqliteQueryBuilder($connection)->table('users')->insert(['email' => 'a']))
            ->toThrow(InsertReturningException::class);
    });

    it('compiles upsert as ON CONFLICT DO UPDATE using excluded values', function (): void {
        $connection = new SqliteRecordingConnection();

        new SqliteQueryBuilder($connection)->table('users')->upsert([['email' => 'a', 'name' => 'A']], ['email']);

        expect($connection->lastExecuteSql)->toBe(
            'INSERT INTO "users" ("email", "name") VALUES (?, ?) ON CONFLICT ("email") DO UPDATE SET "name" = excluded."name"',
        );

        $real = sqliteBuilderConnection();
        new SqliteQueryBuilder($real)->table('users')->upsert([['email' => 'a', 'name' => 'A']], ['email']);
        new SqliteQueryBuilder($real)->table('users')->upsert([['email' => 'a', 'name' => 'B']], ['email']);

        expect($real->query('SELECT name FROM users'))->toBe([['name' => 'B']]);
    });

    it('compiles upsert with no update columns as ON CONFLICT DO NOTHING', function (): void {
        $connection = new SqliteRecordingConnection();

        new SqliteQueryBuilder($connection)->table('users')->upsert([['email' => 'a']], ['email']);

        expect($connection->lastExecuteSql)->toBe(
            'INSERT INTO "users" ("email") VALUES (?) ON CONFLICT ("email") DO NOTHING',
        );
    });

    it('compiles a right join natively', function (): void {
        $connection = new SqliteRecordingConnection();

        new SqliteQueryBuilder($connection)->table('users')
            ->rightJoin('posts', 'users.id', '=', 'posts.user_id')
            ->get();

        expect($connection->lastQuerySql)->toBe(
            'SELECT * FROM "users" RIGHT JOIN "posts" ON "users"."id" = "posts"."user_id"',
        );
    });

    it('compiles lockForUpdate and skipLocked inside a transaction to no lock clause', function (): void {
        $connection = new SqliteRecordingConnection();

        new SqliteQueryBuilder($connection)->table('users')->lockForUpdate()->skipLocked()->get();
        $locked = $connection->lastQuerySql;
        new SqliteQueryBuilder($connection)->table('users')->sharedLock()->noWait()->get();

        expect($locked)->toBe('SELECT * FROM "users"')
            ->and($connection->lastQuerySql)->toBe('SELECT * FROM "users"');
    });

    it('still throws a lock exception when skipLocked and noWait are combined', function (): void {
        $connection = new SqliteRecordingConnection();

        expect(fn () => new SqliteQueryBuilder($connection)->table('users')
            ->lockForUpdate()
            ->skipLocked()
            ->noWait()
            ->get())
            ->toThrow(LockException::class);
    });

    it('still throws a lock exception outside a transaction', function (): void {
        $connection = new SqliteRecordingConnection(open: false);

        expect(fn () => new SqliteQueryBuilder($connection)->table('users')->lockForUpdate()->get())
            ->toThrow(LockException::class);
    });

    it('compiles an offset without a limit as LIMIT -1', function (): void {
        $connection = new SqliteRecordingConnection();

        new SqliteQueryBuilder($connection)->table('users')->offset(5)->get();

        expect($connection->lastQuerySql)->toBe('SELECT * FROM "users" LIMIT -1 OFFSET 5');
    });
});
