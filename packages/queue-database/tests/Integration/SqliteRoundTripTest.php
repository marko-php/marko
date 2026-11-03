<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Exceptions\LockTimeoutException;
use Marko\Database\Sqlite\Connection\SqliteConnection;
use Marko\Database\Sqlite\Query\SqliteQueryBuilderFactory;
use Marko\Database\Sqlite\Sql\SqliteGenerator;
use Marko\Database\Sqlite\Tests\Fixtures\SqliteTestDatabase;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\Database\DatabaseFailedJobRepository;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\Database\Tests\Fixtures\PrivateStateJob;
use Marko\Queue\Database\Tests\Fixtures\QueueTables;
use Marko\Queue\Database\Tests\Fixtures\TestFailedJobHandler;
use Marko\Queue\Database\Tests\Fixtures\TestRetryPolicy;
use Marko\Queue\JobEnvelope;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

/*
 * The database queue against a real SQLite database. Each test gets its own temp file, so these run in plain
 * `composer test`, without the integration-services group. Row locks compile to nothing on SQLite: BEGIN IMMEDIATE
 * serializes the writers that reserve jobs.
 */

function sqliteQueueEnvelope(): JobEnvelope
{
    return new JobEnvelope(new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'sqlite-test-key'])));
}

function sqliteFileQueue(
    SqliteConnection $connection,
    FakeClock $clock = new FakeClock(),
): DatabaseQueue {
    return new DatabaseQueue(
        connection: $connection,
        jobEnvelope: sqliteQueueEnvelope(),
        failedJobHandler: TestFailedJobHandler::create(
            new DatabaseFailedJobRepository($connection, DatabaseTimezoneConfig::fromName('UTC')),
            sqliteQueueEnvelope(),
            $clock,
        ),
        queryBuilderFactory: new SqliteQueryBuilderFactory($connection),
        clock: $clock,
        databaseTimezoneConfig: DatabaseTimezoneConfig::fromName('UTC'),
        retryPolicy: TestRetryPolicy::create(1, $clock),
    );
}

describe('database queue on SQLite', function (): void {
    beforeEach(function (): void {
        $this->database = new SqliteTestDatabase(SqliteTestDatabase::FILE);
        QueueTables::create($this->database->connection, new SqliteGenerator(DatabaseTimezoneConfig::fromName('UTC')));
    });

    afterEach(function (): void {
        $this->database->dispose();
    });

    it('pushes reserves and deletes queue jobs on sqlite', function (): void {
        $connection = $this->database->connection;
        $queue = sqliteFileQueue($connection);

        $id = $queue->push(new PrivateStateJob("se\0cret", 'shared'))->id;
        $sizeBefore = $queue->size();

        /** @var PrivateStateJob $popped */
        $popped = $queue->pop();
        $sizeAfter = $queue->size();
        $secondPop = $queue->pop();
        $deleted = $queue->delete($id);

        expect($sizeBefore)->toBe(1)
            ->and($popped)->toBeInstanceOf(PrivateStateJob::class)
            ->and($popped->id)->toBe($id)
            ->and($popped->secret())->toBe("se\0cret")
            ->and($popped->shared())->toBe('shared')
            ->and($sizeAfter)->toBe(0)
            ->and($secondPop)->toBeNull()
            ->and($deleted)->toBeTrue()
            ->and($connection->query('SELECT id FROM jobs'))->toBeEmpty();
    });

    it('never hands the same queue job to two workers sharing a sqlite file', function (): void {
        $workerA = $this->database->connection;
        $workerB = $this->database->connect(['busy_timeout' => 50]);

        try {
            $first = sqliteFileQueue($workerA)->push(new PrivateStateJob('first', 'shared'))->id;
            $second = sqliteFileQueue($workerA)->push(new PrivateStateJob('second', 'shared'))->id;

            // Sequential claims: A pops and commits, then B pops and gets the other job.
            $poppedByA = sqliteFileQueue($workerA)->pop();
            $poppedByB = sqliteFileQueue($workerB)->pop();
            $nothingLeft = sqliteFileQueue($workerB)->pop();

            expect([$poppedByA?->id, $poppedByB?->id])->toEqualCanonicalizing([$first, $second])
                ->and($poppedByA?->id)->not->toBe($poppedByB?->id)
                ->and($nothingLeft)->toBeNull();
        } finally {
            $workerB->disconnect();
        }
    });

    it(
        'raises LockTimeoutException for a worker that claims while another holds the write lock on sqlite',
        function (): void {
            $workerA = $this->database->connection;
            $workerB = $this->database->connect(['busy_timeout' => 50]);
            sqliteFileQueue($workerA)->push(new PrivateStateJob('only', 'shared'));

            $workerA->beginTransaction();

            try {
                $poppedByA = sqliteFileQueue($workerA)->pop();
                $claimByB = fn () => sqliteFileQueue($workerB)->pop();

                expect($poppedByA)->not->toBeNull()
                    ->and($claimByB)->toThrow(LockTimeoutException::class);
            } finally {
                $workerA->rollback();
                $workerB->disconnect();
            }

            expect(sqliteFileQueue($this->database->connect())->pop())->not->toBeNull();
        },
    );
});
