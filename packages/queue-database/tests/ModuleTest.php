<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Queue\Database\DatabaseFailedJobRepository;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\Database\Tests\Fixtures\SqliteConnection;
use Marko\Queue\Database\Tests\Fixtures\TestJob;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueInterface;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Psr\Clock\ClockInterface;

/**
 * Build a container wired with only the queue-database module bindings.
 */
function queueDatabaseModuleContainer(
    SqliteConnection $connection,
    array $queueConfig = [],
): Container {
    $container = new Container();
    $container->instance(ConnectionInterface::class, $connection);
    $container->instance(QueryBuilderFactoryInterface::class, new PgSqlQueryBuilderFactory($connection));
    $container->instance(ClockInterface::class, new FakeClock());
    $container->instance(DatabaseTimezoneConfig::class, DatabaseTimezoneConfig::fromName('UTC'));
    $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository([
        'encryption.key' => 'module-test-key',
        'queue.driver' => 'database',
        'queue.connection' => 'default',
        'queue.queue' => 'default',
        'queue.retry_after' => 90,
        'queue.max_attempts' => 3,
        ...$queueConfig,
    ]));

    $module = require dirname(__DIR__) . '/module.php';

    foreach ($module['bindings'] as $interface => $implementation) {
        $container->bind($interface, $implementation);
    }

    return $container;
}

test('module.php exists with correct structure', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue('module.php should exist');

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toBeArray();
});

test('module.php binds QueueInterface to a factory that builds DatabaseQueue', function (): void {
    $container = queueDatabaseModuleContainer(SqliteConnection::withQueueTables());

    expect($container->get(QueueInterface::class))->toBeInstanceOf(DatabaseQueue::class);
});

test('module.php passes the container clock to DatabaseQueue', function (): void {
    $connection = SqliteConnection::withQueueTables();
    $container = queueDatabaseModuleContainer($connection);
    $container->instance(ClockInterface::class, new FakeClock('2026-10-05 12:00:00+00:00'));

    $id = $container->get(QueueInterface::class)->later(30, new TestJob('clocked'))->id;
    $row = $connection->query('SELECT available_at FROM jobs WHERE id = :id', ['id' => $id])[0];

    expect($row['available_at'])->toBe('2026-10-05 12:00:30');
});

test('module.php writes queue timestamps in the container database timezone', function (): void {
    $connection = SqliteConnection::withQueueTables();
    $container = queueDatabaseModuleContainer($connection);
    $container->instance(ClockInterface::class, new FakeClock('2026-10-05 12:00:00+00:00'));
    $container->instance(DatabaseTimezoneConfig::class, DatabaseTimezoneConfig::fromName('Europe/Berlin'));

    $id = $container->get(QueueInterface::class)->later(30, new TestJob('zoned'))->id;
    $row = $connection->query('SELECT available_at FROM jobs WHERE id = :id', ['id' => $id])[0];

    expect($row['available_at'])->toBe('2026-10-05 14:00:30');
});

test('module.php binds FailedJobRepositoryInterface', function (): void {
    $modulePath = dirname(__DIR__) . '/module.php';
    $module = require $modulePath;

    expect($module['bindings'])->toHaveKey(FailedJobRepositoryInterface::class)
        ->and($module['bindings'][FailedJobRepositoryInterface::class])->toBe(
            DatabaseFailedJobRepository::class,
        );
});

test('module factory pushes to the configured queue.queue name', function (): void {
    $connection = SqliteConnection::withQueueTables();
    $queue = queueDatabaseModuleContainer($connection, ['queue.queue' => 'high'])->get(QueueInterface::class);

    $queue->push(new TestJob());

    expect($connection->query('SELECT queue FROM jobs')[0]['queue'])->toBe('high')
        ->and($queue->pop())->not->toBeNull();
});

test('module factory reclaims reservations after the configured queue.retry_after', function (): void {
    $connection = SqliteConnection::withQueueTables();
    $queue = queueDatabaseModuleContainer($connection, ['queue.retry_after' => 30])->get(QueueInterface::class);
    $id = $queue->push(new TestJob())->id;
    $queue->pop();

    $reservedAt = fn (int $secondsAgo) => $connection->execute(
        'UPDATE jobs SET reserved_at = :reserved_at WHERE id = :id',
        ['reserved_at' => new DateTimeImmutable("-$secondsAgo seconds")->format('Y-m-d H:i:s'), 'id' => $id],
    );

    $reservedAt(20);
    expect($queue->pop())->toBeNull();

    $reservedAt(40);
    expect($queue->pop()?->id)->toBe($id);
});

test('module factory fails crash-exhausted jobs at the configured queue.max_attempts', function (): void {
    $connection = SqliteConnection::withQueueTables();
    $queue = queueDatabaseModuleContainer($connection, ['queue.max_attempts' => 1])->get(QueueInterface::class);
    $id = $queue->push(new TestJob())->id;
    $queue->pop();
    $connection->execute(
        'UPDATE jobs SET reserved_at = :reserved_at WHERE id = :id',
        ['reserved_at' => new DateTimeImmutable('-1 day')->format('Y-m-d H:i:s'), 'id' => $id],
    );

    expect($queue->pop())->toBeNull()
        ->and((int) $connection->query('SELECT COUNT(*) AS count FROM failed_jobs')[0]['count'])->toBe(1);
});

test('module factory builds the reservation query with the bound query builder factory', function (): void {
    $connection = SqliteConnection::withQueueTables();
    $queue = queueDatabaseModuleContainer($connection)->get(QueueInterface::class);
    $queue->push(new TestJob());

    $queue->pop();

    expect($connection->lockClauses)->toBe(['FOR UPDATE SKIP LOCKED']);
});
