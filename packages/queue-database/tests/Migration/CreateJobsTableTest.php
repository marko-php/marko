<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
use Marko\Queue\Database\Migration\CreateJobsTable;

test('CreateJobsTable migration exists', function () {
    $migration = new CreateJobsTable();

    expect($migration)->toBeInstanceOf(Migration::class);
});

test('CreateJobsTable creates correct columns', function () {
    $executedStatements = [];

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('execute')
        ->willReturnCallback(function (string $sql) use (&$executedStatements): int {
            $executedStatements[] = $sql;

            return 1;
        });

    $migration = new CreateJobsTable();
    $migration->up($connection);

    expect($executedStatements)->not->toBeEmpty();

    $createStatement = $executedStatements[0];

    // Verify all required columns exist
    expect($createStatement)->toContain('CREATE TABLE');
    expect($createStatement)->toContain('jobs');
    expect($createStatement)->toContain('id VARCHAR(36)');
    expect($createStatement)->toContain('PRIMARY KEY');
    expect($createStatement)->toContain('queue VARCHAR(255)');
    expect($createStatement)->toContain('payload TEXT');
    expect($createStatement)->toContain('attempts INT');
    expect($createStatement)->toContain('reserved_at TIMESTAMP');
    expect($createStatement)->toContain('available_at TIMESTAMP');
    expect($createStatement)->toContain('created_at TIMESTAMP');
});

test('CreateJobsTable creates correct indexes', function () {
    $executedStatements = [];

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('execute')
        ->willReturnCallback(function (string $sql) use (&$executedStatements): int {
            $executedStatements[] = $sql;

            return 1;
        });

    $migration = new CreateJobsTable();
    $migration->up($connection);

    // Should have CREATE TABLE and CREATE INDEX statements
    expect(count($executedStatements))->toBeGreaterThanOrEqual(2);

    // Find the index statement
    $indexStatements = array_filter(
        $executedStatements,
        fn (string $sql) => str_contains($sql, 'CREATE INDEX'),
    );

    expect($indexStatements)->not->toBeEmpty();

    // Check for the composite index on queue and available_at
    $allStatements = implode(' ', $executedStatements);
    expect($allStatements)->toContain('idx_queue_available');
    expect($allStatements)->toContain('queue');
    expect($allStatements)->toContain('available_at');
});

test('Migrations create correct table structure', function () {
    $executedStatements = [];

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('execute')
        ->willReturnCallback(function (string $sql) use (&$executedStatements): int {
            $executedStatements[] = $sql;

            return 1;
        });

    $jobsMigration = new CreateJobsTable();
    $jobsMigration->up($connection);

    $allStatements = implode("\n", $executedStatements);

    // Jobs table structure verification
    expect($allStatements)->toContain('CREATE TABLE')
        ->and($allStatements)->toContain('jobs')
        ->and($allStatements)->toContain('id VARCHAR(36)')
        ->and($allStatements)->toContain('PRIMARY KEY')
        ->and($allStatements)->toContain('queue VARCHAR(255)')
        ->and($allStatements)->toContain('payload TEXT')
        ->and($allStatements)->toContain('attempts INT')
        ->and($allStatements)->toContain('reserved_at TIMESTAMP')
        ->and($allStatements)->toContain('available_at TIMESTAMP')
        ->and($allStatements)->toContain('created_at TIMESTAMP');

    // Verify proper queue defaults
    expect($allStatements)->toContain("DEFAULT 'default'");

    // Verify index for efficient queue lookups
    expect($allStatements)->toContain('CREATE INDEX')
        ->and($allStatements)->toContain('idx_queue_available');
});
