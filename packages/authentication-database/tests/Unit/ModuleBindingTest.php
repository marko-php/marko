<?php

declare(strict_types=1);

use Marko\Authentication\Contracts\PasswordResetTokenRepositoryInterface;
use Marko\Authentication\Database\Repository\DatabasePasswordResetTokenRepository;
use Marko\Authentication\Database\Tests\Fixtures\SqlitePasswordResetTokenConnection;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Testing\Fake\FakeConfigRepository;

it('replaces the cache token repository once this module is installed', function (): void {
    $connection = new SqlitePasswordResetTokenConnection();
    $container = new Container();
    $container->instance(ModuleRepositoryInterface::class, new ModuleRepository([
        new ModuleManifest(name: 'marko/authentication', version: '1.0.0'),
        new ModuleManifest(name: 'marko/authentication-database', version: '1.0.0'),
    ]));
    $container->instance(ConnectionInterface::class, $connection);
    $container->instance(QueryBuilderFactoryInterface::class, new PgSqlQueryBuilderFactory($connection));
    $container->instance(DatabaseTimezoneConfig::class, DatabaseTimezoneConfig::fromName('UTC'));
    $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository([
        'authentication.password_reset.provider' => 'users',
    ]));

    $module = require dirname(__DIR__, 3) . '/authentication/module.php';
    $container->bind(
        PasswordResetTokenRepositoryInterface::class,
        $module['bindings'][PasswordResetTokenRepositoryInterface::class],
    );

    $repository = $container->get(PasswordResetTokenRepositoryInterface::class);
    $repository->store(7, 'hash', new DateTimeImmutable('2026-10-06 12:00:00 UTC'));

    expect($repository)->toBeInstanceOf(DatabasePasswordResetTokenRepository::class)
        ->and($connection->rows())->toHaveCount(1);
});
