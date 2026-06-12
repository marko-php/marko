<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Query;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\MySql\Query\MySqlQueryBuilder;
use RuntimeException;

/**
 * Minimal stub connection that records the last compiled SQL.
 */
class LimitOffsetMockConnection implements ConnectionInterface
{
    public string $lastQuerySql = '';

    /** @var array<mixed> */
    public array $lastQueryBindings = [];

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
        $this->lastQuerySql = $sql;
        $this->lastQueryBindings = $bindings;

        return [];
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
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

    public function driverName(): string
    {
        return 'mysql';
    }
}

describe('MySqlQueryBuilder limit/offset clause', function (): void {
    beforeEach(function (): void {
        $this->connection = new LimitOffsetMockConnection();
        $this->builder = new MySqlQueryBuilder($this->connection);
    });

    it('produces valid MySQL SQL for an offset without a limit', function (): void {
        $this->builder
            ->table('users')
            ->offset(10)
            ->get();

        expect($this->connection->lastQuerySql)
            ->toContain('LIMIT 18446744073709551615 OFFSET 10');
    });

    it('does not emit a bare OFFSET clause without a LIMIT', function (): void {
        $this->builder
            ->table('users')
            ->offset(5)
            ->get();

        // A bare " OFFSET n" with no preceding LIMIT is a MySQL syntax error.
        // The SQL must contain a LIMIT clause whenever an OFFSET is present.
        expect($this->connection->lastQuerySql)
            ->toContain('LIMIT')
            ->toContain('OFFSET 5');
    });

    it('leaves limit and offset together unchanged', function (): void {
        $this->builder
            ->table('users')
            ->limit(20)
            ->offset(10)
            ->get();

        expect($this->connection->lastQuerySql)
            ->toContain('LIMIT 20 OFFSET 10');
    });

    it('leaves a limit without an offset unchanged', function (): void {
        $this->builder
            ->table('users')
            ->limit(50)
            ->get();

        expect($this->connection->lastQuerySql)
            ->toContain('LIMIT 50')
            ->not->toContain('OFFSET');
    });
});
