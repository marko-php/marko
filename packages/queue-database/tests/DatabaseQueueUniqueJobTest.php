<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Marko\Queue\Database\DatabaseFailedJobRepository;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\Database\Tests\Fixtures\SqliteConnection;
use Marko\Queue\Database\Tests\Fixtures\TestRetryPolicy;
use Marko\Queue\Database\Tests\Fixtures\UniqueJobStack;
use Marko\Queue\Database\Tests\Fixtures\UniqueTestJob;
use Marko\Queue\QueueInterface;

function uniqueDatabaseQueue(
    UniqueJobStack $stack,
    SqliteConnection $connection,
): QueueInterface {
    return $stack->wrap(new DatabaseQueue(
        connection: $connection,
        jobEnvelope: $stack->jobEnvelope,
        failedJobHandler: $stack->failedJobHandler(
            new DatabaseFailedJobRepository($connection, DatabaseTimezoneConfig::fromName('UTC')),
        ),
        queryBuilderFactory: new PgSqlQueryBuilderFactory($connection),
        clock: $stack->clock,
        databaseTimezoneConfig: DatabaseTimezoneConfig::fromName('UTC'),
        retryPolicy: TestRetryPolicy::create(3, $stack->clock),
    ));
}

describe('DatabaseQueue unique jobs (no driver code)', function (): void {
    it('stores one row for duplicate pushes and releases the lock from the stored payload', function (): void {
        $stack = new UniqueJobStack();
        $connection = SqliteConnection::withQueueTables();
        $queue = uniqueDatabaseQueue($stack, $connection);
        $first = new UniqueTestJob();

        $pushed = $queue->push($first);
        $skipped = $queue->push(new UniqueTestJob());
        $size = $queue->size();
        $popped = $queue->pop();

        expect($pushed->pushed)->toBeTrue()
            ->and($skipped->pushed)->toBeFalse()
            ->and($size)->toBe(1)
            ->and($popped->uniqueLockOwner)->toBe($first->uniqueLockOwner);

        $stack->jobRunner()->run($popped);

        expect($stack->lockIsFree($first))->toBeTrue();
    });

    it('releases the lock when pop() fails a job exhausted by crashed reservations', function (): void {
        $stack = new UniqueJobStack();
        $connection = SqliteConnection::withQueueTables();
        $queue = uniqueDatabaseQueue($stack, $connection);
        $job = new UniqueTestJob(maxAttempts: 1);
        $id = $queue->push($job)->id;

        $queue->pop();
        $connection->execute(
            'UPDATE jobs SET reserved_at = :reserved_at WHERE id = :id',
            ['reserved_at' => new DateTimeImmutable('-1 day')->format('Y-m-d H:i:s'), 'id' => $id],
        );

        expect($queue->pop())->toBeNull()
            ->and($stack->lockIsFree($job))->toBeTrue();
    });
});
