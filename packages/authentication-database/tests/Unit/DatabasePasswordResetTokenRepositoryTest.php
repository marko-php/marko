<?php

declare(strict_types=1);

use Marko\Authentication\Command\ClearResetTokensCommand;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\PasswordResetTokenRepositoryInterface;
use Marko\Authentication\Database\Tests\Fixtures\PasswordResetTokenTable;
use Marko\Authentication\Database\Tests\Fixtures\SqlitePasswordResetTokenConnection;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

/*
 * The repository against in-memory SQLite. PgSqlQueryBuilderFactory supplies the upsert, because SQLite accepts
 * PostgreSQL's INSERT ... ON CONFLICT DO UPDATE; the MySQL and PostgreSQL integration tests cover both real drivers.
 */

beforeEach(function (): void {
    $this->connection = new SqlitePasswordResetTokenConnection();
    $this->queryBuilderFactory = new PgSqlQueryBuilderFactory($this->connection);
    $this->repository = PasswordResetTokenTable::repository($this->connection, $this->queryBuilderFactory);
});

it('implements PasswordResetTokenRepositoryInterface', function (): void {
    expect($this->repository)->toBeInstanceOf(PasswordResetTokenRepositoryInterface::class);
});

it('stores a token hash and finds it again with its creation time', function (): void {
    $createdAt = new DateTimeImmutable('2026-10-06 12:00:00 UTC');

    $this->repository->store(7, 'hash-one', $createdAt);
    $record = $this->repository->find(7);

    expect($record->tokenHash)->toBe('hash-one')
        ->and($record->createdAt->getTimestamp())->toBe($createdAt->getTimestamp())
        ->and($this->connection->rows())->toBe([[
            'provider' => 'users',
            'user_id' => '7',
            'token_hash' => 'hash-one',
            'created_at' => '2026-10-06 12:00:00',
        ]]);
});

it('returns null when the user has no token', function (): void {
    expect($this->repository->find(7))->toBeNull();
});

it('replaces the token a user already has instead of adding a second row', function (): void {
    $this->repository->store(7, 'old-hash', new DateTimeImmutable('2026-10-06 12:00:00 UTC'));
    $this->repository->store(7, 'new-hash', new DateTimeImmutable('2026-10-06 12:05:00 UTC'));

    $record = $this->repository->find(7);

    expect($record->tokenHash)->toBe('new-hash')
        ->and($record->createdAt->format('H:i'))->toBe('12:05')
        ->and($this->connection->rows())->toHaveCount(1);
});

it('treats integer and string identifiers as the same user and keeps string identifiers intact', function (): void {
    $uuid = '0198f4c2-7a1b-7c3d-9e8f-0a1b2c3d4e5f';

    $this->repository->store(7, 'int-hash', new DateTimeImmutable('2026-10-06 12:00:00 UTC'));
    $this->repository->store($uuid, 'uuid-hash', new DateTimeImmutable('2026-10-06 12:00:00 UTC'));

    expect($this->repository->find('7')->tokenHash)->toBe('int-hash')
        ->and($this->repository->find($uuid)->tokenHash)->toBe('uuid-hash');
});

it('keeps tokens of the configured reset provider apart from other providers', function (): void {
    $admins = PasswordResetTokenTable::repository($this->connection, $this->queryBuilderFactory, provider: 'admins');
    $createdAt = new DateTimeImmutable('2026-10-06 12:00:00 UTC');

    $this->repository->store(7, 'user-hash', $createdAt);
    $admins->store(7, 'admin-hash', $createdAt);
    $admins->delete(7);

    expect($this->repository->find(7)->tokenHash)->toBe('user-hash')
        ->and($admins->find(7))->toBeNull()
        ->and($this->connection->rows())->toHaveCount(1);
});

it('deletes only the given user\'s token', function (): void {
    $createdAt = new DateTimeImmutable('2026-10-06 12:00:00 UTC');
    $this->repository->store(7, 'hash-seven', $createdAt);
    $this->repository->store(8, 'hash-eight', $createdAt);

    $this->repository->delete(7);

    expect($this->repository->find(7))->toBeNull()
        ->and($this->repository->find(8)->tokenHash)->toBe('hash-eight');
});

it('deletes every token created before the cutoff, of every provider, and counts them', function (): void {
    $admins = PasswordResetTokenTable::repository($this->connection, $this->queryBuilderFactory, provider: 'admins');
    $this->repository->store(1, 'stale', new DateTimeImmutable('2026-10-06 10:00:00 UTC'));
    $admins->store(2, 'stale-admin', new DateTimeImmutable('2026-10-06 10:59:59 UTC'));
    $this->repository->store(3, 'at-cutoff', new DateTimeImmutable('2026-10-06 11:00:00 UTC'));
    $this->repository->store(4, 'fresh', new DateTimeImmutable('2026-10-06 11:30:00 UTC'));

    $deleted = $this->repository->deleteExpired(new DateTimeImmutable('2026-10-06 11:00:00 UTC'));

    expect($deleted)->toBe(2)
        ->and(array_column($this->connection->rows(), 'token_hash'))->toBe(['at-cutoff', 'fresh']);
});

it('writes created_at in the database timezone and reads it back as the same instant', function (): void {
    $repository = PasswordResetTokenTable::repository(
        $this->connection,
        $this->queryBuilderFactory,
        timezone: 'America/New_York',
    );
    $createdAt = new DateTimeImmutable('2026-10-06 12:00:00 UTC');

    $repository->store(7, 'hash', $createdAt);
    $repository->store(8, 'older', new DateTimeImmutable('2026-10-06 11:59:59 UTC'));
    $deleted = $repository->deleteExpired(new DateTimeImmutable('2026-10-06 08:00:00 America/New_York'));

    expect($this->connection->rows()[0]['created_at'])->toBe('2026-10-06 08:00:00')
        ->and($repository->find(7)->createdAt->getTimestamp())->toBe($createdAt->getTimestamp())
        ->and($deleted)->toBe(1);
});

it('gives auth:clear-reset-tokens real rows to clear', function (): void {
    $clock = new FakeClock(new DateTimeImmutable('2026-10-06 12:00:00 UTC'));
    $authConfig = new AuthConfig(new FakeConfigRepository([
        'authentication.password_reset.expire_seconds' => 3600,
    ]));
    $this->repository->store(1, 'expired', new DateTimeImmutable('2026-10-06 10:30:00 UTC'));
    $this->repository->store(2, 'valid', new DateTimeImmutable('2026-10-06 11:30:00 UTC'));
    $stream = fopen('php://memory', 'w+');

    $exitCode = new ClearResetTokensCommand($this->repository, $authConfig, $clock)
        ->execute(new Input(['marko', 'auth:clear-reset-tokens']), new Output($stream));

    rewind($stream);

    expect($exitCode)->toBe(0)
        ->and(stream_get_contents($stream))->toBe("Cleared 1 expired password reset token(s).\n")
        ->and(array_column($this->connection->rows(), 'token_hash'))->toBe(['valid']);
});
