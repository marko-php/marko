<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Query;

use Closure;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use RuntimeException;

/**
 * Mock connection that records queries and returns expected results.
 */
class MockConnection implements ConnectionInterface
{
    public string $lastQuerySql = '';

    /** @var array */
    public array $lastQueryBindings = [];

    public string $lastExecuteSql = '';

    /** @var array */
    public array $lastExecuteBindings = [];

    /**
     * @param array<array<string, mixed>> $queryReturn
     */
    public function __construct(
        private readonly array $queryReturn = [],
        private readonly int $executeReturn = 0,
        private readonly ?Closure $queryCallback = null,
        private readonly ?Closure $executeCallback = null,
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
        $this->lastQuerySql = $sql;
        $this->lastQueryBindings = $bindings;

        if ($this->queryCallback !== null) {
            ($this->queryCallback)($sql, $bindings);
        }

        return $this->queryReturn;
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->lastExecuteSql = $sql;
        $this->lastExecuteBindings = $bindings;

        if ($this->executeCallback !== null) {
            ($this->executeCallback)($sql, $bindings);
        }

        return $this->executeReturn;
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
}
