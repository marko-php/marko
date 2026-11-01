<?php

declare(strict_types=1);

namespace Marko\Validation\Database\Tests\Fixtures;

use LogicException;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;

/**
 * Records every query() call and answers it with $rows. Identifiers are quoted with double quotes, as PostgreSQL
 * does. Anything but a SELECT is a bug in the lookup, so it throws.
 */
class RecordingConnection implements ConnectionInterface
{
    /**
     * @var list<array{sql: string, bindings: array<mixed>}>
     */
    public array $queries = [];

    /**
     * @param array<array<string, mixed>> $rows
     */
    public function __construct(
        public array $rows = [],
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
        $this->queries[] = ['sql' => $sql, 'bindings' => $bindings];

        return $this->rows;
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        throw new LogicException("A record lookup must not write: $sql");
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        throw new LogicException("A record lookup must not prepare statements: $sql");
    }

    public function lastInsertId(): int
    {
        throw new LogicException('A record lookup must not insert');
    }

    public function driverName(): string
    {
        return 'pgsql';
    }

    public function supportsReturning(): bool
    {
        return true;
    }

    public function quoteIdentifier(
        string $identifier,
    ): string {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
