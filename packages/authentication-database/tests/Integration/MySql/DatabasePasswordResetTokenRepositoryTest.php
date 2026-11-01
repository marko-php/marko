<?php

declare(strict_types=1);

namespace Marko\Authentication\Database\Tests\Integration\MySql;

use DateTimeImmutable;
use Marko\Authentication\Database\Tests\Fixtures\PasswordResetTokenTable;
use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Database\MySql\Introspection\MySqlIntrospector;
use Marko\Database\MySql\Query\MySqlQueryBuilderFactory;
use Marko\Database\MySql\Sql\MySqlGenerator;
use Marko\Database\MySql\Tests\Fixtures\IntegrationDatabase;

/*
 * DatabasePasswordResetTokenRepository against a real MySQL server, through the MySqlQueryBuilder
 * INSERT ... ON DUPLICATE KEY UPDATE upsert; CI also runs this directory against MariaDB. The password_reset_tokens
 * table is built from the PasswordResetToken entity through MySqlGenerator, as db:migrate builds it. Settings come
 * from the database-mysql IntegrationDatabase fixture (MARKO_TEST_MYSQL_*); the tests skip without
 * MARKO_TEST_MYSQL_HOST and fail instead with MARKO_INTEGRATION_REQUIRED set. The tests create and drop the
 * password_reset_tokens table.
 */

pest()->group('integration-services');

beforeEach(function (): void {
    $config = IntegrationDatabase::config();

    if ($config === null) {
        $this->markTestSkipped(IntegrationDatabase::SKIP_REASON);
    }

    $this->database = $config->database;
    $this->connection = new MySqlConnection($config);
    PasswordResetTokenTable::drop($this->connection);
    PasswordResetTokenTable::create($this->connection, new MySqlGenerator());
    $this->queryBuilderFactory = new MySqlQueryBuilderFactory($this->connection);
    $this->repository = PasswordResetTokenTable::repository($this->connection, $this->queryBuilderFactory);
});

afterEach(function (): void {
    if (isset($this->connection)) {
        PasswordResetTokenTable::drop($this->connection);
        $this->connection->disconnect();
    }
});

describe('DatabasePasswordResetTokenRepository on MySQL', function (): void {
    it('stores, replaces and finds a token through the upsert', function (): void {
        $this->repository->store(7, 'old-hash', new DateTimeImmutable('2026-10-06 12:00:00 UTC'));
        $this->repository->store(7, 'new-hash', new DateTimeImmutable('2026-10-06 12:05:00 UTC'));

        $record = $this->repository->find(7);

        expect($record->tokenHash)->toBe('new-hash')
            ->and($record->createdAt->getTimestamp())
            ->toBe(new DateTimeImmutable('2026-10-06 12:05:00 UTC')->getTimestamp())
            ->and($this->repository->find(8))->toBeNull()
            ->and($this->connection->query('SELECT user_id FROM password_reset_tokens'))->toBe([['user_id' => '7']]);
    });

    it('keeps providers apart and deletes only one user\'s token', function (): void {
        $admins = PasswordResetTokenTable::repository($this->connection, $this->queryBuilderFactory, 'admins');
        $createdAt = new DateTimeImmutable('2026-10-06 12:00:00 UTC');
        $this->repository->store(7, 'user-seven', $createdAt);
        $this->repository->store(8, 'user-eight', $createdAt);
        $admins->store(7, 'admin-seven', $createdAt);

        $this->repository->delete(7);

        expect($this->repository->find(7))->toBeNull()
            ->and($this->repository->find(8)->tokenHash)->toBe('user-eight')
            ->and($admins->find(7)->tokenHash)->toBe('admin-seven');
    });

    it('deletes tokens created before the cutoff and counts them', function (): void {
        $this->repository->store(1, 'stale', new DateTimeImmutable('2026-10-06 10:00:00 UTC'));
        $this->repository->store(2, 'at-cutoff', new DateTimeImmutable('2026-10-06 11:00:00 UTC'));
        $this->repository->store(3, 'fresh', new DateTimeImmutable('2026-10-06 11:30:00 UTC'));

        $deleted = $this->repository->deleteExpired(new DateTimeImmutable('2026-10-06 11:00:00 UTC'));

        expect($deleted)->toBe(1)
            ->and($this->repository->find(1))->toBeNull()
            ->and($this->repository->find(2)->tokenHash)->toBe('at-cutoff');
    });

    it('creates a table that diffs as empty against the entity', function (): void {
        $diff = PasswordResetTokenTable::diff(new MySqlIntrospector($this->connection, $this->database));

        expect(new MySqlGenerator()->generateUp($diff))->toBe([]);
    });
});
