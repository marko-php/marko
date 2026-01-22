<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
use Marko\Queue\Database\Migration\CreateFailedJobsTable;

test('CreateFailedJobsTable migration exists', function () {
    $migration = new CreateFailedJobsTable();

    expect($migration)->toBeInstanceOf(Migration::class);
});

test('CreateFailedJobsTable creates correct columns', function () {
    $executedStatements = [];

    $connection = $this->createMock(ConnectionInterface::class);
    $connection->method('execute')
        ->willReturnCallback(function (string $sql) use (&$executedStatements): int {
            $executedStatements[] = $sql;

            return 1;
        });

    $migration = new CreateFailedJobsTable();
    $migration->up($connection);

    expect($executedStatements)->not->toBeEmpty();

    $createStatement = $executedStatements[0];

    // Verify all required columns exist
    expect($createStatement)->toContain('CREATE TABLE');
    expect($createStatement)->toContain('failed_jobs');
    expect($createStatement)->toContain('id VARCHAR(36)');
    expect($createStatement)->toContain('PRIMARY KEY');
    expect($createStatement)->toContain('queue VARCHAR(255)');
    expect($createStatement)->toContain('payload TEXT');
    expect($createStatement)->toContain('exception TEXT');
    expect($createStatement)->toContain('failed_at TIMESTAMP');
});
