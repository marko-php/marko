<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Queue\Database\DatabaseFailedJobRepository;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobRepositoryInterface;

class MockConnection implements ConnectionInterface
{
    public array $executedQueries = [];

    public array $executedStatements = [];

    public function __construct(
        private array $queryResults = [],
        private int $executeResult = 0,
    ) {}

    public function connect(): void {}

    public function disconnect(): void {}

    public function isConnected(): bool
    {
        return true;
    }

    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        $this->executedQueries[] = ['sql' => $sql, 'bindings' => $bindings];

        return array_shift($this->queryResults) ?? [];
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->executedStatements[] = ['sql' => $sql, 'bindings' => $bindings];

        return $this->executeResult;
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        throw new RuntimeException('Not implemented');
    }

    public function lastInsertId(): int
    {
        return 1;
    }
}

function createMockConnection(
    array $queryResults = [],
    int $executeResult = 0,
): MockConnection {
    return new MockConnection($queryResults, $executeResult);
}

test('DatabaseFailedJobRepository implements interface', function (): void {
    $connection = createMockConnection();

    $repository = new DatabaseFailedJobRepository($connection);

    expect($repository)->toBeInstanceOf(FailedJobRepositoryInterface::class);
});

test('DatabaseFailedJobRepository store saves failed job', function (): void {
    $connection = createMockConnection(executeResult: 1);
    $repository = new DatabaseFailedJobRepository($connection);

    $failedJob = new FailedJob(
        id: 'failed-123',
        queue: 'default',
        payload: '{"class":"TestJob","data":{}}',
        exception: 'RuntimeException: Test error',
        failedAt: new DateTimeImmutable('2024-01-15 10:30:00'),
    );

    $repository->store($failedJob);

    expect($connection->executedStatements)->toHaveCount(1);
    expect($connection->executedStatements[0]['sql'])->toContain('INSERT INTO');
    expect($connection->executedStatements[0]['sql'])->toContain('failed_jobs');
    expect($connection->executedStatements[0]['bindings'])->toContain('failed-123');
    expect($connection->executedStatements[0]['bindings'])->toContain('default');
    expect($connection->executedStatements[0]['bindings'])->toContain('{"class":"TestJob","data":{}}');
    expect($connection->executedStatements[0]['bindings'])->toContain('RuntimeException: Test error');
});

test('DatabaseFailedJobRepository all retrieves all failed jobs', function (): void {
    $connection = createMockConnection(queryResults: [
        [
            [
                'id' => 'failed-1',
                'queue' => 'default',
                'payload' => '{"class":"Job1"}',
                'exception' => 'Error 1',
                'failed_at' => '2024-01-15 10:30:00',
            ],
            [
                'id' => 'failed-2',
                'queue' => 'emails',
                'payload' => '{"class":"Job2"}',
                'exception' => 'Error 2',
                'failed_at' => '2024-01-15 11:00:00',
            ],
        ],
    ]);
    $repository = new DatabaseFailedJobRepository($connection);

    $failedJobs = $repository->all();

    expect($failedJobs)->toHaveCount(2);
    expect($failedJobs[0])->toBeInstanceOf(FailedJob::class);
    expect($failedJobs[0]->id)->toBe('failed-1');
    expect($failedJobs[0]->queue)->toBe('default');
    expect($failedJobs[1]->id)->toBe('failed-2');
    expect($failedJobs[1]->queue)->toBe('emails');
    expect($connection->executedQueries[0]['sql'])->toContain('SELECT');
    expect($connection->executedQueries[0]['sql'])->toContain('failed_jobs');
});

test('DatabaseFailedJobRepository find retrieves by ID', function (): void {
    $connection = createMockConnection(queryResults: [
        [
            [
                'id' => 'failed-123',
                'queue' => 'default',
                'payload' => '{"class":"TestJob"}',
                'exception' => 'Test error',
                'failed_at' => '2024-01-15 10:30:00',
            ],
        ],
    ]);
    $repository = new DatabaseFailedJobRepository($connection);

    $failedJob = $repository->find('failed-123');

    expect($failedJob)->toBeInstanceOf(FailedJob::class);
    expect($failedJob->id)->toBe('failed-123');
    expect($failedJob->queue)->toBe('default');
    expect($connection->executedQueries[0]['sql'])->toContain('WHERE');
    expect($connection->executedQueries[0]['bindings'])->toBe(['failed-123']);
});

test('DatabaseFailedJobRepository find returns null for non-existent ID', function (): void {
    $connection = createMockConnection(queryResults: [[]]);
    $repository = new DatabaseFailedJobRepository($connection);

    $failedJob = $repository->find('non-existent');

    expect($failedJob)->toBeNull();
});

test('DatabaseFailedJobRepository delete removes by ID', function (): void {
    $connection = createMockConnection(executeResult: 1);
    $repository = new DatabaseFailedJobRepository($connection);

    $result = $repository->delete('failed-123');

    expect($result)->toBeTrue();
    expect($connection->executedStatements)->toHaveCount(1);
    expect($connection->executedStatements[0]['sql'])->toContain('DELETE FROM');
    expect($connection->executedStatements[0]['sql'])->toContain('failed_jobs');
    expect($connection->executedStatements[0]['bindings'])->toBe(['failed-123']);
});

test('DatabaseFailedJobRepository delete returns false when ID not found', function (): void {
    $connection = createMockConnection(executeResult: 0);
    $repository = new DatabaseFailedJobRepository($connection);

    $result = $repository->delete('non-existent');

    expect($result)->toBeFalse();
});

test('DatabaseFailedJobRepository clear removes all', function (): void {
    $connection = createMockConnection(executeResult: 5);
    $repository = new DatabaseFailedJobRepository($connection);

    $cleared = $repository->clear();

    expect($cleared)->toBe(5);
    expect($connection->executedStatements)->toHaveCount(1);
    expect($connection->executedStatements[0]['sql'])->toContain('DELETE FROM');
    expect($connection->executedStatements[0]['sql'])->toContain('failed_jobs');
    expect($connection->executedStatements[0]['sql'])->not->toContain('WHERE');
});

test('DatabaseFailedJobRepository count returns total', function (): void {
    $connection = createMockConnection(queryResults: [
        [['count' => 42]],
    ]);
    $repository = new DatabaseFailedJobRepository($connection);

    $count = $repository->count();

    expect($count)->toBe(42);
    expect($connection->executedQueries[0]['sql'])->toContain('SELECT COUNT');
    expect($connection->executedQueries[0]['sql'])->toContain('failed_jobs');
});
