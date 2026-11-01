<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Integration\QueueSession\Job\FailingJob;
use Marko\Integration\QueueSession\Job\MarkerJob;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueInterface;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Testing\Database\TestDatabase;
use Marko\Testing\Database\TruncateDatabase;

/*
 * The cases QueueSessionTablesPgSqlTest and QueueSessionTablesMySqlTest run against their own server (#337): a
 * fresh `db:migrate` creates jobs, failed_jobs and sessions from the entities marko/queue-database and
 * marko/session-database ship, and the queue worker, the database session handler and TruncateDatabase work on
 * the tables it created. Each file's beforeEach boots the fixture as $this->app and $this->project.
 */

const QUEUE_SESSION_TABLES = ['failed_jobs', 'jobs', 'sessions'];

/**
 * Register the cases inside the calling test file.
 */
function queueSessionTableCases(): void
{
    it('creates jobs, failed_jobs and sessions on a fresh database with db:migrate', function (): void {
        $introspector = $this->app->container->get(IntrospectorInterface::class);
        $before = array_intersect(QUEUE_SESSION_TABLES, $introspector->getTables());

        $result = migrateQueueSessionProject($this->app);
        $after = array_values(array_intersect(QUEUE_SESSION_TABLES, $introspector->getTables()));

        expect($before)->toBe([])
            ->and($result['exitCode'])->toBe(0, $result['output'])
            ->and($result['output'])->toContain('Generated:')
            ->and($after)->toBe(QUEUE_SESSION_TABLES);
    })->issue(337);

    it('has nothing to migrate on a second db:migrate', function (): void {
        migrateQueueSessionProject($this->app);
        $files = glob("$this->project/database/migrations/*.php") ?: [];

        $second = migrateQueueSessionProject($this->app);

        expect($files)->not->toBeEmpty()
            ->and($second['exitCode'])->toBe(0, $second['output'])
            ->and($second['output'])->toContain('Nothing to migrate.')
            ->and($second['output'])->not->toContain('Generated:')
            ->and(glob("$this->project/database/migrations/*.php") ?: [])->toBe($files);
    })->issue(337);

    it('runs a queued job and records a failing one through queue:work', function (): void {
        migrateQueueSessionProject($this->app);
        $container = $this->app->container;
        $queue = $container->get(QueueInterface::class);
        $marker = "$this->project/storage/marker.txt";
        $queue->push(new MarkerJob($marker));
        $failingId = $queue->push(new FailingJob())->id;

        $first = runIntegrationCommand($this->app, 'queue:work', ['--once']);
        $second = runIntegrationCommand($this->app, 'queue:work', ['--once']);
        $failed = $container->get(FailedJobRepositoryInterface::class)->find($failingId);

        expect($first['exitCode'])->toBe(0, $first['output'])
            ->and($second['exitCode'])->toBe(0, $second['output'])
            ->and(file_get_contents($marker))->toBe('ran')
            ->and($failed?->exception)->toContain('FailingJob failed on purpose')
            ->and($queue->size())->toBe(0);
    })->issue(337);

    it('stores, resumes and destroys a session through the database handler', function (): void {
        migrateQueueSessionProject($this->app);
        $handler = $this->app->container->get(SessionHandlerInterface::class);
        $id = str_repeat('a', 40);

        $handler->write($id, 'first');
        $handler->write($id, 'second');
        $known = $handler->validateId($id);
        $read = $handler->read($id);
        $handler->destroy($id);

        expect($known)->toBeTrue()
            ->and($read)->toBe('second')
            ->and($handler->validateId($id))->toBeFalse()
            ->and($handler->read($id))->toBe('');
    })->issue(337);

    it('empties jobs, failed_jobs and sessions with TruncateDatabase', function (): void {
        migrateQueueSessionProject($this->app);
        $container = $this->app->container;
        $connection = $container->get(ConnectionInterface::class);
        $queue = $container->get(QueueInterface::class);
        $queue->push(new FailingJob());
        runIntegrationCommand($this->app, 'queue:work', ['--once']);
        $queue->push(new MarkerJob("$this->project/storage/never.txt"));
        $container->get(SessionHandlerInterface::class)->write(str_repeat('b', 40), 'payload');
        $count = fn (string $table): int => (int) $connection->query(
            "SELECT COUNT(*) AS total FROM $table",
        )[0]['total'];
        $before = array_map($count, QUEUE_SESSION_TABLES);

        $truncate = new TruncateDatabase(new TestDatabase($this->app));
        withIntegrationAppEnv('testing', fn () => $truncate->truncate());

        expect($before)->toBe([1, 1, 1])
            ->and($truncate->tables())->toBe(QUEUE_SESSION_TABLES)
            ->and(array_map($count, QUEUE_SESSION_TABLES))->toBe([0, 0, 0]);
    })->issue(337);
}
