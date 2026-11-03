<?php

declare(strict_types=1);

use Marko\Database\Exceptions\CheckConstraintViolationException;
use Marko\Database\Exceptions\ForeignKeyConstraintViolationException;
use Marko\Database\Exceptions\LockTimeoutException;
use Marko\Database\Exceptions\NotNullConstraintViolationException;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\Exceptions\SerializationFailureException;
use Marko\Database\Exceptions\TransactionConflictException;
use Marko\Database\Exceptions\UniqueConstraintViolationException;
use Marko\Database\Sqlite\Connection\SqliteExceptionTranslator;
use Pdo\Sqlite;

/**
 * Runs $sql against a fresh in-memory database built from $schema and
 * returns what the translator makes of the PDOException it raises.
 *
 * @param array<int|string, mixed> $bindings
 */
function sqliteViolation(
    string $schema,
    string $sql,
    array $bindings = [],
    bool $extendedCodes = true,
): QueryException {
    $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    if ($extendedCodes) {
        $pdo->setAttribute(Sqlite::ATTR_EXTENDED_RESULT_CODES, true);
    }

    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec($schema);

    try {
        $pdo->prepare($sql)->execute($bindings);
    } catch (PDOException $pdoException) {
        return new SqliteExceptionTranslator()->translate($pdoException, $sql, $bindings);
    }

    throw new LogicException('Expected the statement to fail');
}

/**
 * Builds a PDOException shaped like the ones pdo_sqlite throws.
 */
function sqliteDriverError(
    int $resultCode,
    string $message,
    string $sqlState = 'HY000',
): PDOException {
    $exception = new PDOException("SQLSTATE[$sqlState]: General error: $resultCode $message");
    $exception->errorInfo = [$sqlState, $resultCode, $message];

    return $exception;
}

describe('SqliteExceptionTranslator', function (): void {
    it(
        'translates a unique violation into UniqueConstraintViolationException with table and column',
        function (): void {
            $exception = sqliteViolation(
                'CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, name TEXT, UNIQUE (email, name)); '
                . "INSERT INTO users (email, name) VALUES ('taken@example.com', 'a')",
                'INSERT INTO users (email, name) VALUES (?, ?)',
                ['taken@example.com', 'a'],
            );

            expect($exception)->toBeInstanceOf(UniqueConstraintViolationException::class)
                ->and($exception->table())->toBe('users')
                ->and($exception->column())->toBe('email, name')
                ->and($exception->getPrevious())->toBeInstanceOf(PDOException::class)
                ->and($exception->getMessage())->not->toContain('taken@example.com');
        },
    );

    it('translates a primary key violation into UniqueConstraintViolationException', function (): void {
        $exception = sqliteViolation(
            "CREATE TABLE tags (code TEXT PRIMARY KEY); INSERT INTO tags VALUES ('a')",
            'INSERT INTO tags (code) VALUES (?)',
            ['a'],
        );

        expect($exception)->toBeInstanceOf(UniqueConstraintViolationException::class)
            ->and($exception->table())->toBe('tags')
            ->and($exception->column())->toBe('code');
    });

    it('translates a foreign key violation into ForeignKeyConstraintViolationException', function (): void {
        $exception = sqliteViolation(
            'CREATE TABLE users (id INTEGER PRIMARY KEY); '
            . 'CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INTEGER REFERENCES users (id))',
            'INSERT INTO posts (user_id) VALUES (?)',
            [99],
        );

        expect($exception)->toBeInstanceOf(ForeignKeyConstraintViolationException::class)
            ->and($exception->table())->toBe('posts');
    });

    it('translates a not null violation into NotNullConstraintViolationException with column', function (): void {
        $exception = sqliteViolation(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL)',
            'INSERT INTO users (email) VALUES (?)',
            [null],
        );

        expect($exception)->toBeInstanceOf(NotNullConstraintViolationException::class)
            ->and($exception->table())->toBe('users')
            ->and($exception->column())->toBe('email');
    });

    it('translates a check violation into CheckConstraintViolationException', function (): void {
        $exception = sqliteViolation(
            'CREATE TABLE items (qty INTEGER, CONSTRAINT qty_positive CHECK (qty > 0))',
            'INSERT INTO items (qty) VALUES (?)',
            [-1],
        );

        expect($exception)->toBeInstanceOf(CheckConstraintViolationException::class)
            ->and($exception->constraintName())->toBe('qty_positive')
            ->and($exception->table())->toBe('items');
    });

    it(
        'translates SQLITE_BUSY into LockTimeoutException and SQLITE_BUSY_SNAPSHOT into SerializationFailureException',
        function (): void {
            $translator = new SqliteExceptionTranslator();

            $busy = $translator->translate(sqliteDriverError(5, 'database is locked'), 'UPDATE t SET a = 1', []);
            $locked = $translator->translate(
                sqliteDriverError(6, 'database table is locked'),
                'UPDATE t SET a = 1',
                [],
            );
            $snapshot = $translator->translate(sqliteDriverError(517, 'database is locked'), 'UPDATE t SET a = 1', []);

            expect($busy)->toBeInstanceOf(LockTimeoutException::class)
                ->not->toBeInstanceOf(TransactionConflictException::class)
                ->and($locked)->toBeInstanceOf(LockTimeoutException::class)
                ->and($snapshot)->toBeInstanceOf(SerializationFailureException::class)
                ->toBeInstanceOf(TransactionConflictException::class);
        },
    );

    it(
        'classifies constraint errors by message when only the primary result code is available and falls back to QueryException otherwise',
        function (): void {
            $translator = new SqliteExceptionTranslator();
            $classify = fn (string $message): QueryException => $translator->translate(
                sqliteDriverError(19, $message, '23000'),
                'INSERT INTO users (a) VALUES (1)',
                [],
            );

            $real = sqliteViolation(
                'CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT UNIQUE); '
                . "INSERT INTO users (email) VALUES ('a')",
                'INSERT INTO users (email) VALUES (?)',
                ['a'],
                extendedCodes: false,
            );

            expect($real)->toBeInstanceOf(UniqueConstraintViolationException::class)
                ->and($classify('UNIQUE constraint failed: users.email'))->toBeInstanceOf(
                    UniqueConstraintViolationException::class,
                )
                ->and($classify('PRIMARY KEY must be unique'))->toBeInstanceOf(
                    UniqueConstraintViolationException::class,
                )
                ->and($classify('FOREIGN KEY constraint failed'))->toBeInstanceOf(
                    ForeignKeyConstraintViolationException::class,
                )
                ->and($classify('NOT NULL constraint failed: users.email'))->toBeInstanceOf(
                    NotNullConstraintViolationException::class,
                )
                ->and($classify('CHECK constraint failed: qty_positive'))->toBeInstanceOf(
                    CheckConstraintViolationException::class,
                )
                ->and($classify('something else entirely'))->toBeInstanceOf(QueryException::class)
                ->not->toBeInstanceOf(UniqueConstraintViolationException::class)
                ->and($translator->translate(sqliteDriverError(1, 'no such table: x'), 'SELECT * FROM x', []))
                ->toBeInstanceOf(QueryException::class);
        },
    );
});
