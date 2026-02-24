<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Health\Checks\DatabaseHealthCheck;
use Marko\Health\Value\HealthStatus;

it('checks database connectivity via DatabaseHealthCheck', function () {
    $connection = new class () implements ConnectionInterface
    {
        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(string $sql, array $bindings = []): array
        {
            return [['1' => '1']];
        }

        public function execute(string $sql, array $bindings = []): int
        {
            return 0;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 0;
        }
    };

    $check = new DatabaseHealthCheck($connection);

    expect($check->getName())->toBe('database');

    $result = $check->check();

    expect($result->status)->toBe(HealthStatus::Healthy)
        ->and($result->name)->toBe('database');
});

it('returns unhealthy status when database query fails', function () {
    $connection = new class () implements ConnectionInterface
    {
        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return false;
        }

        public function query(string $sql, array $bindings = []): array
        {
            throw new RuntimeException('Connection refused');
        }

        public function execute(string $sql, array $bindings = []): int
        {
            return 0;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 0;
        }
    };

    $check = new DatabaseHealthCheck($connection);
    $result = $check->check();

    expect($result->status)->toBe(HealthStatus::Unhealthy)
        ->and($result->message)->toContain('Connection refused');
});
