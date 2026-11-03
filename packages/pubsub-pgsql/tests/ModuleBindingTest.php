<?php

declare(strict_types=1);

namespace Marko\PubSub\PgSql\Tests;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Container\Container;
use Marko\PubSub\Exceptions\PubSubException;
use Marko\PubSub\PgSql\PgSqlPubSubConnection;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * @param array<string, mixed> $overrides
 * @param list<string> $without Config keys to leave out
 */
function createPubSubPgSqlContainer(
    array $overrides = [],
    array $without = [],
): Container {
    $config = [
        'pubsub-pgsql.host' => 'db.internal',
        'pubsub-pgsql.port' => 5433,
        'pubsub-pgsql.user' => 'app',
        'pubsub-pgsql.password' => 'secret',
        'pubsub-pgsql.database' => 'app',
        'pubsub-pgsql.sslmode' => 'verify-full',
        ...$overrides,
    ];

    foreach ($without as $key) {
        unset($config[$key]);
    }

    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository($config));

    $module = require dirname(__DIR__) . '/module.php';

    foreach ($module['bindings'] as $id => $implementation) {
        $container->bind($id, $implementation);
    }

    return $container;
}

describe('pubsub-pgsql module bindings', function (): void {
    it('passes pubsub-pgsql.sslmode to the connection', function (): void {
        $connection = createPubSubPgSqlContainer()->get(PgSqlPubSubConnection::class);

        expect($connection->sslMode)->toBe('verify-full');
    });

    it('treats a null or empty pubsub-pgsql.sslmode as the libpq default', function (): void {
        $null = createPubSubPgSqlContainer(overrides: ['pubsub-pgsql.sslmode' => null])
            ->get(PgSqlPubSubConnection::class);
        $empty = createPubSubPgSqlContainer(overrides: ['pubsub-pgsql.sslmode' => ''])
            ->get(PgSqlPubSubConnection::class);

        expect($null->sslMode)->toBeNull()
            ->and($empty->sslMode)->toBeNull();
    });

    it('fails loudly when pubsub-pgsql.sslmode is missing from config', function (): void {
        $container = createPubSubPgSqlContainer(without: ['pubsub-pgsql.sslmode']);

        expect(fn () => $container->get(PgSqlPubSubConnection::class))
            ->toThrow(ConfigNotFoundException::class);
    });

    it('fails loudly on an unknown pubsub-pgsql.sslmode', function (): void {
        $container = createPubSubPgSqlContainer(overrides: ['pubsub-pgsql.sslmode' => 'on']);

        expect(fn () => $container->get(PgSqlPubSubConnection::class))
            ->toThrow(PubSubException::class, 'pubsub-pgsql.sslmode');
    });
});
