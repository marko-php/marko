<?php

declare(strict_types=1);

namespace Marko\Authentication\Database\Tests\Integration\Sqlite;

use DateTimeImmutable;
use Marko\Authentication\Database\Tests\Fixtures\PasswordResetTokenTable;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Sqlite\Introspection\SqliteIntrospector;
use Marko\Database\Sqlite\Query\SqliteQueryBuilderFactory;
use Marko\Database\Sqlite\Sql\SqliteGenerator;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteTestDatabase;

/*
 * DatabasePasswordResetTokenRepository against a real SQLite connection, through the SqliteQueryBuilder
 * INSERT ... ON CONFLICT (provider, user_id) DO UPDATE upsert. The password_reset_tokens table is built from the
 * PasswordResetToken entity through SqliteGenerator, as db:migrate builds it. Runs in the plain suite on a
 * private temp file or in-memory database.
 */

dataset('authentication sqlite kinds', [SqliteTestDatabase::FILE, SqliteTestDatabase::MEMORY]);

beforeEach(function (): void {
    $this->database = null;
});

afterEach(function (): void {
    $this->database?->dispose();
});

function sqliteTokenFixture(
    object $test,
    string $kind,
): void {
    $test->database = new SqliteTestDatabase($kind);
    PasswordResetTokenTable::create(
        $test->database->connection,
        new SqliteGenerator(DatabaseTimezoneConfig::fromName('UTC')),
    );
    $test->queryBuilderFactory = new SqliteQueryBuilderFactory($test->database->connection);
    $test->repository = PasswordResetTokenTable::repository($test->database->connection, $test->queryBuilderFactory);
}

it('persists and retrieves authentication records on sqlite', function (string $kind): void {
    sqliteTokenFixture($this, $kind);

    $this->repository->store(7, 'old-hash', new DateTimeImmutable('2026-10-06 12:00:00 UTC'));
    $this->repository->store(7, 'new-hash', new DateTimeImmutable('2026-10-06 12:05:00 UTC'));

    $record = $this->repository->find(7);

    expect($record->tokenHash)->toBe('new-hash')
        ->and($record->createdAt->getTimestamp())
        ->toBe(new DateTimeImmutable('2026-10-06 12:05:00 UTC')->getTimestamp())
        ->and($this->repository->find(8))->toBeNull()
        ->and($this->database->connection->query('SELECT COUNT(*) AS total FROM password_reset_tokens'))
        ->toBe([['total' => 1]]);
})->with('authentication sqlite kinds');

it('keeps providers apart and deletes only one user\'s token on sqlite', function (string $kind): void {
    sqliteTokenFixture($this, $kind);
    $admins = PasswordResetTokenTable::repository($this->database->connection, $this->queryBuilderFactory, 'admins');
    $createdAt = new DateTimeImmutable('2026-10-06 12:00:00 UTC');
    $this->repository->store(7, 'user-seven', $createdAt);
    $this->repository->store(8, 'user-eight', $createdAt);
    $admins->store(7, 'admin-seven', $createdAt);

    $this->repository->delete(7);

    expect($this->repository->find(7))->toBeNull()
        ->and($this->repository->find(8)->tokenHash)->toBe('user-eight')
        ->and($admins->find(7)->tokenHash)->toBe('admin-seven');
})->with('authentication sqlite kinds');

it('deletes tokens created before the cutoff and counts them on sqlite', function (string $kind): void {
    sqliteTokenFixture($this, $kind);
    $this->repository->store(1, 'stale', new DateTimeImmutable('2026-10-06 10:00:00 UTC'));
    $this->repository->store(2, 'at-cutoff', new DateTimeImmutable('2026-10-06 11:00:00 UTC'));
    $this->repository->store(3, 'fresh', new DateTimeImmutable('2026-10-06 11:30:00 UTC'));

    $deleted = $this->repository->deleteExpired(new DateTimeImmutable('2026-10-06 11:00:00 UTC'));

    expect($deleted)->toBe(1)
        ->and($this->repository->find(1))->toBeNull()
        ->and($this->repository->find(2)->tokenHash)->toBe('at-cutoff');
})->with('authentication sqlite kinds');

it('creates a table that diffs as empty against the entity on sqlite', function (string $kind): void {
    sqliteTokenFixture($this, $kind);

    $diff = PasswordResetTokenTable::diff(new SqliteIntrospector($this->database->connection));

    expect(new SqliteGenerator(DatabaseTimezoneConfig::fromName('UTC'))->generateUp($diff))->toBeEmpty();
})->with('authentication sqlite kinds');
