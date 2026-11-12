<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Log\Contracts\LoggerInterface;
use Marko\Queue\Command\WorkCommand;
use Marko\Queue\Database\Tests\Fixtures\AlwaysFailingJob;
use Marko\Queue\Database\Tests\Fixtures\SqliteConnection;
use Marko\Queue\Database\Tests\Fixtures\TestJob;
use Marko\Queue\Exceptions\NoDriverException;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueInterface;
use Marko\Queue\WorkerInterface;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeLogger;
use Psr\Clock\ClockInterface;

/**
 * Container wired the way an app with only marko/queue + marko/queue-database is:
 * the two modules' bindings plus the database connection and config the app provides.
 */
function workerTestContainer(
    SqliteConnection $connection,
    array $queueConfig = [],
): Container {
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(EventDispatcherInterface::class, new FakeEventDispatcher());
    $container->instance(ConnectionInterface::class, $connection);
    $container->instance(QueryBuilderFactoryInterface::class, new PgSqlQueryBuilderFactory($connection));
    $container->instance(ClockInterface::class, new FakeClock());
    $container->instance(LoggerInterface::class, new FakeLogger());
    $container->instance(DatabaseTimezoneConfig::class, DatabaseTimezoneConfig::fromName('UTC'));
    $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository([
        'encryption.key' => 'worker-regression-key',
        'queue.driver' => 'database',
        'queue.connection' => 'default',
        'queue.queue' => 'default',
        'queue.retry_after' => 90,
        'queue.timeout' => 0,
        'queue.max_attempts' => 3,
        // queue:work only reads the signal; a path that never exists means no restart was requested
        'queue.restart_signal_driver' => 'file',
        'queue.restart_signal' => sys_get_temp_dir() . '/marko-queue-database-restart-' . bin2hex(random_bytes(6)),
        ...$queueConfig,
    ]));

    $packages = dirname(__DIR__, 3);

    foreach (['queue', 'queue-database'] as $package) {
        $module = require "$packages/$package/module.php";

        foreach ($module['bindings'] as $interface => $implementation) {
            $container->bind($interface, $implementation);
        }

        foreach ($module['arguments'] ?? [] as $class => $arguments) {
            $container->setArguments($class, $arguments);
        }
    }

    return $container;
}

/**
 * Make every job available now, skipping the retry backoff delay.
 */
function skipBackoff(
    SqliteConnection $connection,
): void {
    $connection->execute(
        'UPDATE jobs SET available_at = :now',
        ['now' => new DateTimeImmutable('-1 second', new DateTimeZone('UTC'))->format('Y-m-d H:i:s')],
    );
}

/**
 * Run single-job worker iterations until the jobs table is empty (bounded).
 */
function drainWithWorker(
    Container $container,
    SqliteConnection $connection,
): void {
    $worker = $container->get(WorkerInterface::class);

    for ($i = 0; $i < 20 && countRows($connection, 'jobs') > 0; $i++) {
        skipBackoff($connection);
        $worker->work(once: true);
    }
}

function countRows(
    SqliteConnection $connection,
    string $table,
): int {
    return (int) $connection->query("SELECT COUNT(*) AS count FROM $table")[0]['count'];
}

beforeEach(function (): void {
    AlwaysFailingJob::$handled = 0;
});

describe('database queue worker regressions', function (): void {
    it('attempts an always-failing job exactly maxAttempts times then moves it to failed_jobs', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $container = workerTestContainer($connection);
        $id = $container->get(QueueInterface::class)->push(new AlwaysFailingJob())->id;

        drainWithWorker($container, $connection);

        $failedJob = $container->get(FailedJobRepositoryInterface::class)->find($id);

        expect(AlwaysFailingJob::$handled)->toBe(3)
            ->and($failedJob)->not->toBeNull()
            ->and($failedJob->exception)->toContain('This job always fails');
    });

    it('removes the exhausted job from the jobs table', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $container = workerTestContainer($connection);
        $container->get(QueueInterface::class)->push(new AlwaysFailingJob());

        drainWithWorker($container, $connection);

        expect(countRows($connection, 'jobs'))->toBe(0)
            ->and(countRows($connection, 'failed_jobs'))->toBe(1);
    });

    it('counts an expired reservation as an attempt and eventually fails the job', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $container = workerTestContainer($connection, ['queue.max_attempts' => 2]);
        $queue = $container->get(QueueInterface::class);
        $id = $queue->push(new AlwaysFailingJob())->id;

        // A worker reserves the job and dies before completing or releasing it
        $queue->pop();
        $connection->execute(
            'UPDATE jobs SET reserved_at = :reserved_at WHERE id = :id',
            ['reserved_at' => new DateTimeImmutable('-1 day')->format('Y-m-d H:i:s'), 'id' => $id],
        );

        drainWithWorker($container, $connection);

        expect(AlwaysFailingJob::$handled)->toBe(1)
            ->and(countRows($connection, 'jobs'))->toBe(0)
            ->and($container->get(FailedJobRepositoryInterface::class)->find($id))->not->toBeNull();
    });

    it('honours a changed queue.max_attempts end to end', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $container = workerTestContainer($connection, ['queue.max_attempts' => 5]);
        $container->get(QueueInterface::class)->push(new AlwaysFailingJob());

        drainWithWorker($container, $connection);

        expect(AlwaysFailingJob::$handled)->toBe(5)
            ->and(countRows($connection, 'failed_jobs'))->toBe(1);
    });

    it('honours a job-level maxAttempts over queue.max_attempts end to end', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $container = workerTestContainer($connection, ['queue.max_attempts' => 5]);
        $container->get(QueueInterface::class)->push(new AlwaysFailingJob(maxAttempts: 2));

        drainWithWorker($container, $connection);

        expect(AlwaysFailingJob::$handled)->toBe(2);
    });

    it('pushes to and pops from the configured queue.queue name', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $container = workerTestContainer($connection, ['queue.queue' => 'emails']);
        $container->get(QueueInterface::class)->push(new TestJob());

        expect($connection->query('SELECT queue FROM jobs')[0]['queue'])->toBe('emails');

        $container->get(WorkerInterface::class)->work(once: true);

        expect(countRows($connection, 'jobs'))->toBe(0);
    });

    it(
        'resolves queue:work from the container with only queue and queue-database bindings and runs once',
        function (): void {
            $connection = SqliteConnection::withQueueTables();
            $container = workerTestContainer($connection);
            $container->get(QueueInterface::class)->push(new TestJob());

            $command = $container->get(WorkCommand::class);
            $stream = fopen('php://memory', 'r+');
            $exitCode = $command->execute(new Input(['marko', 'queue:work', '--once']), new Output($stream));

            expect($command)->toBeInstanceOf(WorkCommand::class)
                ->and($exitCode)->toBe(0)
                ->and(countRows($connection, 'jobs'))->toBe(0);
        },
    );

    it('reports the real interface name for an unbound non-driver queue interface', function (): void {
        $container = new Container();

        expect(fn () => $container->get(WorkerInterface::class))
            ->toThrow(NoDriverException::class, 'No implementation is bound for ' . WorkerInterface::class);
    });
});
