<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Integration;

use Marko\Database\Connection\TransactionInterface;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Tests\Fixtures\IntegrationDatabase;
use Marko\Database\PgSql\Tests\Fixtures\SharedConnection\Account;
use Marko\Database\PgSql\Tests\Fixtures\SharedConnection\AccountRepository;
use Marko\Database\PgSql\Tests\Fixtures\SharedConnection\AuditEntry;
use Marko\Database\PgSql\Tests\Fixtures\SharedConnection\AuditEntryRepository;
use Marko\Database\PgSql\Tests\Fixtures\SharedConnection\SharedConnectionContainer;
use RuntimeException;

/*
 * Runs against a real PostgreSQL server. Set MARKO_TEST_PGSQL_HOST (and
 * optionally MARKO_TEST_PGSQL_PORT, _DATABASE, _USERNAME, _PASSWORD) to
 * enable; the tests skip otherwise. The tests create and drop the
 * shared_accounts and shared_audit_entries tables.
 *
 * Settings come from tests/Fixtures/IntegrationDatabase. With
 * MARKO_INTEGRATION_REQUIRED set (CI), a missing host fails instead of
 * skipping. Part of the integration-services group.
 */

function pgsqlRowCount(
    PgSqlConnection $connection,
    string $table,
): int {
    return (int) $connection->query("SELECT COUNT(*) AS total FROM $table")[0]['total'];
}

pest()->group('integration-services');

beforeEach(function (): void {
    $config = IntegrationDatabase::config();

    if ($config === null) {
        $this->markTestSkipped(IntegrationDatabase::SKIP_REASON);
    }

    $this->observer = new PgSqlConnection($config);
    $this->observer->execute('DROP TABLE IF EXISTS shared_accounts, shared_audit_entries');
    $this->observer->execute('CREATE TABLE shared_accounts (id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL)');
    $this->observer->execute(
        'CREATE TABLE shared_audit_entries (id SERIAL PRIMARY KEY, message VARCHAR(255) NOT NULL)',
    );
});

afterEach(function (): void {
    if (isset($this->observer)) {
        $this->observer->execute('DROP TABLE IF EXISTS shared_accounts, shared_audit_entries');
        $this->observer->disconnect();
    }
});

describe('PostgreSQL transactions across repositories', function (): void {
    it('rolls back writes from two repositories when the transaction callback throws', function (): void {
        $container = SharedConnectionContainer::build(IntegrationDatabase::config());
        $accounts = $container->get(AccountRepository::class);
        $auditEntries = $container->get(AuditEntryRepository::class);

        $run = fn () => $container->get(TransactionInterface::class)->transaction(
            function () use ($accounts, $auditEntries): void {
                $account = new Account();
                $account->name = 'Ada';
                $accounts->save($account);

                $entry = new AuditEntry();
                $entry->message = 'Account created';
                $auditEntries->save($entry);

                throw new RuntimeException('Payment declined');
            },
        );

        expect($run)->toThrow(RuntimeException::class, 'Payment declined')
            ->and(pgsqlRowCount($this->observer, 'shared_accounts'))->toBe(0)
            ->and(pgsqlRowCount($this->observer, 'shared_audit_entries'))->toBe(0);
    });

    it('commits writes from two repositories when the transaction callback succeeds', function (): void {
        $container = SharedConnectionContainer::build(IntegrationDatabase::config());
        $accounts = $container->get(AccountRepository::class);
        $auditEntries = $container->get(AuditEntryRepository::class);

        $container->get(TransactionInterface::class)->transaction(
            function () use ($accounts, $auditEntries): void {
                $account = new Account();
                $account->name = 'Ada';
                $accounts->save($account);

                $entry = new AuditEntry();
                $entry->message = 'Account created';
                $auditEntries->save($entry);
            },
        );

        expect(pgsqlRowCount($this->observer, 'shared_accounts'))->toBe(1)
            ->and(pgsqlRowCount($this->observer, 'shared_audit_entries'))->toBe(1);
    });
});
