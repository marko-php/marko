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

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            return [['1' => '1']];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return 0;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 0;
        }

        public function driverName(): string
        {
            return 'sqlite';
        }

        public function supportsReturning(): bool
        {
            return false;
        }

        public function quoteIdentifier(
            string $identifier,
        ): string {
            return '"' . str_replace('"', '""', $identifier) . '"';
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

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            throw new RuntimeException('Connection refused');
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return 0;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 0;
        }

        public function driverName(): string
        {
            return 'sqlite';
        }

        public function supportsReturning(): bool
        {
            return false;
        }

        public function quoteIdentifier(
            string $identifier,
        ): string {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }
    };

    $check = new DatabaseHealthCheck($connection);
    $result = $check->check();

    expect($result->status)->toBe(HealthStatus::Unhealthy)
        ->and($result->message)->toBe('Database connection failed')
        ->and($result->exception)->toBeInstanceOf(RuntimeException::class)
        ->and($result->exception?->getMessage())->toBe('Connection refused');
});
