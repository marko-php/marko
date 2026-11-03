<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Integration;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\CheckConstraintViolationException;
use Marko\Database\Exceptions\ForeignKeyConstraintViolationException;
use Marko\Database\Exceptions\NotNullConstraintViolationException;
use Marko\Database\Exceptions\UniqueConstraintViolationException;
use Marko\Database\Repository\Repository;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteTestDatabase;
use PDOException;
use Throwable;

#[Table('constraint_users')]
class SqliteConstraintUser extends Entity
{
    /** @noinspection PhpUnused - Entity property accessed via reflection */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused - Entity property accessed via reflection */
    #[Column(length: 255)]
    public string $email;
}

/**
 * @extends Repository<SqliteConstraintUser>
 */
class SqliteConstraintUserRepository extends Repository
{
    protected const string ENTITY_CLASS = SqliteConstraintUser::class;
}

function sqliteConstraintCatch(
    callable $callback,
): ?Throwable {
    try {
        $callback();
    } catch (Throwable $e) {
        return $e;
    }

    return null;
}

function sqliteConstraintUser(
    string $email,
): SqliteConstraintUser {
    $user = new SqliteConstraintUser();
    $user->email = $email;

    return $user;
}

/**
 * Create the constraint tables on the database and return a user repository over its connection.
 */
function sqliteConstraintSetup(
    SqliteTestDatabase $database,
): SqliteConstraintUserRepository {
    $database->connection->execute(
        'CREATE TABLE constraint_users (id INTEGER PRIMARY KEY AUTOINCREMENT, email VARCHAR(255) NOT NULL, '
        . 'CONSTRAINT constraint_users_email_unique UNIQUE (email))',
    );
    $database->connection->execute(
        'CREATE TABLE constraint_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, '
        . 'CONSTRAINT constraint_posts_user_id_foreign FOREIGN KEY (user_id) REFERENCES constraint_users (id), '
        . 'CONSTRAINT constraint_posts_user_id_positive CHECK (user_id > 0))',
    );
    $metadataFactory = new EntityMetadataFactory();

    return new SqliteConstraintUserRepository(
        $database->connection,
        $metadataFactory,
        new EntityHydrator($metadataFactory),
    );
}

describe('SQLite constraint violations', function (): void {
    it('raises typed constraint exceptions from real sqlite violations', function (string $kind): void {
        SqliteTestDatabase::using($kind, function (SqliteTestDatabase $database): void {
            $users = sqliteConstraintSetup($database);
            $connection = $database->connection;
            $users->save(sqliteConstraintUser('taken@example.com'));

            $unique = sqliteConstraintCatch(fn () => $users->save(sqliteConstraintUser('taken@example.com')));
            $missingParent = sqliteConstraintCatch(
                fn () => $connection->execute('INSERT INTO constraint_posts (user_id) VALUES (?)', [999999]),
            );
            $connection->execute('INSERT INTO constraint_posts (user_id) VALUES (1)');
            $referenced = sqliteConstraintCatch(fn () => $connection->execute('DELETE FROM constraint_users'));
            $connection->execute('PRAGMA foreign_keys = OFF');
            $check = sqliteConstraintCatch(
                fn () => $connection->execute('INSERT INTO constraint_posts (user_id) VALUES (?)', [-1]),
            );
            $notNull = sqliteConstraintCatch(
                fn () => $connection->execute('INSERT INTO constraint_users (email) VALUES (?)', [null]),
            );

            expect($unique)->toBeInstanceOf(UniqueConstraintViolationException::class)
                ->and($unique->table())->toBe('constraint_users')
                ->and($unique->column())->toBe('email')
                ->and($unique->getPrevious())->toBeInstanceOf(PDOException::class)
                ->and($unique->getMessage())->not->toContain('taken@example.com')
                ->and($missingParent)->toBeInstanceOf(ForeignKeyConstraintViolationException::class)
                ->and($missingParent->table())->toBe('constraint_posts')
                ->and($referenced)->toBeInstanceOf(ForeignKeyConstraintViolationException::class)
                ->and($check)->toBeInstanceOf(CheckConstraintViolationException::class)
                ->and($check->table())->toBe('constraint_posts')
                ->and($notNull)->toBeInstanceOf(NotNullConstraintViolationException::class)
                ->and($notNull->column())->toBe('email')
                ->and($notNull->table())->toBe('constraint_users');
        });
    })->with([SqliteTestDatabase::FILE, SqliteTestDatabase::MEMORY]);
});
