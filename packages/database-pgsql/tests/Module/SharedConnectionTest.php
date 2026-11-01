<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Module;

use Marko\Core\Sleep\SleeperInterface;
use Marko\Database\Connection\ConnectionFactoryInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionBackoff;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\TransactionException;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Tests\Fixtures\SharedConnection\AccountRepository;
use Marko\Database\PgSql\Tests\Fixtures\SharedConnection\AuditEntryRepository;
use Marko\Database\PgSql\Tests\Fixtures\SharedConnection\SharedConnectionContainer;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Seed\SeederRunner;
use Marko\Testing\Fake\FakeSleeper;
use ReflectionProperty;
use RuntimeException;

describe('PostgreSQL shared connection wiring', function (): void {
    it('resolves the same ConnectionInterface instance for two repositories', function (): void {
        $container = SharedConnectionContainer::build(SharedConnectionContainer::config());

        $accounts = $container->get(AccountRepository::class);
        $auditEntries = $container->get(AuditEntryRepository::class);

        expect($accounts->exposedConnection())->toBeInstanceOf(PgSqlConnection::class)
            ->and($accounts->exposedConnection())->toBe($auditEntries->exposedConnection())
            ->and($container->get(ConnectionInterface::class))->toBe($accounts->exposedConnection());
    });

    it('gives the QueryBuilderFactoryInterface the same connection the repositories use', function (): void {
        $container = SharedConnectionContainer::build(SharedConnectionContainer::config());

        $accounts = $container->get(AccountRepository::class);
        $factory = $accounts->exposedQueryBuilderFactory();
        $factoryConnection = new ReflectionProperty($factory, 'connection')->getValue($factory);
        $standaloneFactory = $container->get(QueryBuilderFactoryInterface::class);
        $standaloneConnection = new ReflectionProperty($standaloneFactory, 'connection')->getValue($standaloneFactory);

        expect($factoryConnection)->toBe($accounts->exposedConnection())
            ->and($standaloneConnection)->toBe($accounts->exposedConnection());
    });

    it('resolves PgSqlConnection with the container-bound TransactionBackoff', function (): void {
        $container = SharedConnectionContainer::build(SharedConnectionContainer::config());
        $sleeper = new FakeSleeper();
        $container->instance(SleeperInterface::class, $sleeper);

        $connection = $container->get(ConnectionInterface::class);
        $backoff = new ReflectionProperty($connection, 'transactionBackoff')->getValue($connection);

        expect($backoff)->toBeInstanceOf(TransactionBackoff::class)
            ->and(new ReflectionProperty($backoff, 'sleeper')->getValue($backoff))->toBe($sleeper);
    });

    it('gives connections made by the container-built factory the bound TransactionBackoff', function (): void {
        $container = SharedConnectionContainer::build(SharedConnectionContainer::config());
        $sleeper = new FakeSleeper();
        $container->instance(SleeperInterface::class, $sleeper);

        $connection = $container->get(ConnectionFactoryInterface::class)->make(SharedConnectionContainer::config());
        $backoff = new ReflectionProperty($connection, 'transactionBackoff')->getValue($connection);

        expect(new ReflectionProperty($backoff, 'sleeper')->getValue($backoff))->toBe($sleeper);
    });

    it('resolves TransactionInterface to the shared ConnectionInterface instance', function (): void {
        $container = SharedConnectionContainer::build(SharedConnectionContainer::config());

        expect($container->get(TransactionInterface::class))
            ->toBe($container->get(ConnectionInterface::class));
    });

    it('throws a loud error when the bound connection does not implement TransactionInterface', function (): void {
        $container = SharedConnectionContainer::build(SharedConnectionContainer::config());
        $container->instance(ConnectionInterface::class, new class () implements ConnectionInterface
        {
            public function connect(): void {}

            public function disconnect(): void {}

            public function isConnected(): bool
            {
                return false;
            }

            public function query(
                string $sql,
                array $bindings = [],
            ): array {
                return [];
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                return 0;
            }

            public function prepare(string $sql): StatementInterface
            {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 0;
            }

            public function driverName(): string
            {
                return 'pgsql';
            }

            public function supportsReturning(): bool
            {
                return true;
            }

            public function quoteIdentifier(
                string $identifier,
            ): string {
                return '"' . str_replace('"', '""', $identifier) . '"';
            }
        });

        expect(fn () => $container->get(TransactionInterface::class))
            ->toThrow(TransactionException::class, 'does not support transactions');
    });

    it('resolves the same EntityHydrator instance for two repositories', function (): void {
        $container = SharedConnectionContainer::build(SharedConnectionContainer::config());

        $accounts = $container->get(AccountRepository::class);
        $auditEntries = $container->get(AuditEntryRepository::class);

        expect($accounts->exposedHydrator())->toBe($auditEntries->exposedHydrator());
    });

    it('passes the shared TransactionInterface to SeederRunner when a driver is installed', function (): void {
        $container = SharedConnectionContainer::build(SharedConnectionContainer::config());

        $runner = $container->get(SeederRunner::class);
        $transaction = new ReflectionProperty($runner, 'transaction')->getValue($runner);

        expect($transaction)->toBeInstanceOf(TransactionInterface::class)
            ->and($transaction)->toBe($container->get(ConnectionInterface::class));
    });
});
