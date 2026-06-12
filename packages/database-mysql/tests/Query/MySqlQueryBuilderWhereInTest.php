<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Query;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\MySql\Query\MySqlQueryBuilder;
use RuntimeException;

/**
 * Minimal stub connection that records query SQL and bindings.
 */
class WhereInMockConnection implements ConnectionInterface
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

describe('MySqlQueryBuilder whereIn empty array', function (): void {
    beforeEach(function (): void {
        $this->connection = new WhereInMockConnection();
        $this->builder = new MySqlQueryBuilder($this->connection);
    });

    it('compiles whereIn with an empty array to a no-match condition', function (): void {
        $this->builder->table('users')->whereIn('id', [])->get();

        expect($this->connection->lastQuerySql)->toContain('1 = 0')
            ->and($this->connection->lastQuerySql)->not->toContain('IN ()');
    });

    it('binds no parameters for an empty whereIn', function (): void {
        $this->builder->table('users')->whereIn('id', [])->get();

        expect($this->connection->lastQueryBindings)->toBeEmpty();
    });

    it('still compiles whereIn with a non-empty array to an IN clause', function (): void {
        $this->builder->table('users')->whereIn('id', [1, 2, 3])->get();

        expect($this->connection->lastQuerySql)->toContain('IN (?, ?, ?)')
            ->and($this->connection->lastQueryBindings)->toBe([1, 2, 3]);
    });

    it('composes an empty whereIn with other where clauses using AND', function (): void {
        $this->builder->table('users')->where('status', '=', 'active')->whereIn('id', [])->get();

        expect($this->connection->lastQuerySql)->toContain('AND 1 = 0')
            ->and($this->connection->lastQuerySql)->toContain('WHERE');
    });
});
