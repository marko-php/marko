<?php

declare(strict_types=1);

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\PgSql\Query\PgSqlQueryBuilderFactory;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\Database\DatabaseFailedJobRepository;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\Database\Tests\Fixtures\InMemoryFailedJobRepository;
use Marko\Queue\Database\Tests\Fixtures\SqliteConnection;
use Marko\Queue\Database\Tests\Fixtures\TestFailedJobHandler;
use Marko\Queue\Database\Tests\Fixtures\TestJob;
use Marko\Queue\FailedJob;
use Marko\Queue\JobEnvelope;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

/*
 * The queue's raw statements quote their table through ConnectionInterface::quoteIdentifier() (#338). The SQLite
 * connection quotes with backticks here, so a statement that interpolates the name bare or picks its own delimiter
 * shows up, and a table named `order` (reserved in SQLite, MySQL and PostgreSQL) is a syntax error unless quoted.
 */

function backtickQuotedQueue(
    string $table,
): array {
    $connection = SqliteConnection::withQueueTables($table);
    $connection->identifierDelimiter = '`';
    $queue = new DatabaseQueue(
        connection: $connection,
        jobEnvelope: new JobEnvelope(new EncryptionConfig(new FakeConfigRepository([
            'encryption.key' => 'test-hmac-key-for-queue-database',
        ]))),
        failedJobHandler: TestFailedJobHandler::create(
            new InMemoryFailedJobRepository(),
            new JobEnvelope(new EncryptionConfig(new FakeConfigRepository([
                'encryption.key' => 'test-hmac-key-for-queue-database',
            ]))),
            new FakeClock('2026-10-05 12:00:00'),
        ),
        queryBuilderFactory: new PgSqlQueryBuilderFactory($connection),
        clock: new FakeClock('2026-10-05 12:00:00'),
        databaseTimezoneConfig: DatabaseTimezoneConfig::fromName('UTC'),
        table: $table,
    );
    $connection->statements = [];

    return [$queue, $connection];
}

describe('DatabaseQueue identifier quoting', function (): void {
    it('quotes a custom table name in every raw statement', function (): void {
        [$queue, $connection] = backtickQuotedQueue('order');

        $first = $queue->push(new TestJob('first'))->id;
        $queue->later(60, new TestJob('later'));
        $popped = $queue->pop();
        $queue->release($popped);
        $size = $queue->size();
        $queue->delete($first);
        $cleared = $queue->clear();

        // reserveNext() selects through the query builder, which quotes with its own driver's rule
        $raw = array_values(array_filter(
            $connection->statements,
            fn (string $sql): bool => !str_contains($sql, 'FROM "order"'),
        ));

        expect($popped?->id)->toBe($first)
            ->and($size)->toBe(1)
            ->and($cleared)->toBe(1)
            ->and($raw)->toHaveCount(7)
            ->and(array_filter($raw, fn (string $sql): bool => !str_contains($sql, '`order`')))->toBe([]);
    })->issue(338);

    it('quotes the default jobs table', function (): void {
        [$queue, $connection] = backtickQuotedQueue('jobs');

        $queue->push(new TestJob('first'));
        $queue->size();

        expect($connection->statements)->toHaveCount(2)
            ->and($connection->statements[0])->toStartWith('INSERT INTO `jobs` (')
            ->and($connection->statements[1])->toStartWith('SELECT COUNT(*) as count FROM `jobs` WHERE');
    })->issue(338);
});

describe('DatabaseFailedJobRepository identifier quoting', function (): void {
    it('quotes the failed_jobs table in every statement', function (): void {
        $connection = SqliteConnection::withQueueTables();
        $connection->identifierDelimiter = '`';
        $repository = new DatabaseFailedJobRepository($connection, DatabaseTimezoneConfig::fromName('UTC'));
        $connection->statements = [];

        $repository->store(new FailedJob(
            id: 'failed-1',
            queue: 'default',
            payload: 'payload',
            exception: 'boom',
            failedAt: new DateTimeImmutable('2026-10-05 12:00:00'),
        ));
        $all = $repository->all();
        $found = $repository->find('failed-1');
        $count = $repository->count();
        $deleted = $repository->delete('failed-1');
        $repository->clear();

        expect($all)->toHaveCount(1)
            ->and($found?->id)->toBe('failed-1')
            ->and($count)->toBe(1)
            ->and($deleted)->toBeTrue()
            ->and($connection->statements)->toHaveCount(6)
            ->and(array_filter(
                $connection->statements,
                fn (string $sql): bool => !str_contains($sql, '`failed_jobs`'),
            ))->toBe([]);
    })->issue(338);
});
