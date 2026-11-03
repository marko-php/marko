<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Integration;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Exceptions\UniqueConstraintViolationException;
use Marko\Database\Schema\Table as SchemaTable;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteMigrations;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteTestDatabase;

/*
 * Entities are turned into schema tables the way db:migrate does, migrated onto a real SQLite
 * database (a temp file and :memory:), and diffed again: a settled schema has an empty diff.
 */

function sqliteSettlePlainUsers(): SchemaTable
{
    return SqliteMigrations::schema(new #[Table('settle_users')] class () extends Entity
    {
        /** @noinspection PhpUnused - Entity property for structural definition */
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        /** @noinspection PhpUnused - Entity property for structural definition */
        #[Column(length: 191)]
        public string $email;
    });
}

function sqliteSettleUniqueUsers(): SchemaTable
{
    return SqliteMigrations::schema(new #[Table('settle_users')] class () extends Entity
    {
        /** @noinspection PhpUnused - Entity property for structural definition */
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        /** @noinspection PhpUnused - Entity property for structural definition */
        #[Column(length: 191, unique: true)]
        public string $email;
    });
}

describe('SQLite schema diffs that settle', function (): void {
    it(
        'migrates entities and settles with no schema diff on file and memory databases',
        function (string $kind): void {
            SqliteTestDatabase::using($kind, function (SqliteTestDatabase $database): void {
                $posts = SqliteMigrations::schema(
                    new #[Table('settle_posts'), Index(name: 'idx_settle_posts_title', columns: ['title'])]
                    class () extends Entity
                    {
                        /** @noinspection PhpUnused - Entity property for structural definition */
                        #[Column(primaryKey: true, autoIncrement: true)]
                        public int $id;

                        /** @noinspection PhpUnused - Entity property for structural definition */
                        #[Column(references: 'settle_users.id')]
                        public int $userId;

                        /** @noinspection PhpUnused - Entity property for structural definition */
                        #[Column(length: 120)]
                        public string $title;

                        /** @noinspection PhpUnused - Entity property for structural definition */
                        #[Column(nullable: true)]
                        public ?string $body = null;
                    },
                );

                SqliteMigrations::migrate($database, sqliteSettleUniqueUsers());
                $created = SqliteMigrations::migrate($database, $posts);

                expect($created->isEmpty())->toBeFalse()
                    ->and(SqliteMigrations::diff($database, sqliteSettleUniqueUsers())->isEmpty())->toBeTrue()
                    ->and(SqliteMigrations::diff($database, $posts)->isEmpty())->toBeTrue();
            });
        },
    )->with([SqliteTestDatabase::FILE, SqliteTestDatabase::MEMORY]);

    it(
        'adds and drops uniqueness through a table rebuild and the diff settles each time',
        function (string $kind): void {
            SqliteTestDatabase::using($kind, function (SqliteTestDatabase $database): void {
                SqliteMigrations::migrate($database, sqliteSettlePlainUsers());
                $database->connection->execute("INSERT INTO settle_users (email) VALUES ('a@example.com')");

                SqliteMigrations::migrate($database, sqliteSettleUniqueUsers());
                $uniqueSettled = SqliteMigrations::diff($database, sqliteSettleUniqueUsers())->isEmpty();
                $duplicate = fn () => $database->connection->execute(
                    "INSERT INTO settle_users (email) VALUES ('a@example.com')",
                );

                expect($uniqueSettled)->toBeTrue()
                    ->and($duplicate)->toThrow(UniqueConstraintViolationException::class);

                SqliteMigrations::migrate($database, sqliteSettlePlainUsers());

                expect(SqliteMigrations::diff($database, sqliteSettlePlainUsers())->isEmpty())->toBeTrue()
                    ->and($database->connection->query('SELECT email FROM settle_users'))
                    ->toBe([['email' => 'a@example.com']]);
            });
        },
    )->with([SqliteTestDatabase::FILE, SqliteTestDatabase::MEMORY]);
});
