<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools\Runtime\Adapters;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Mcp\Tools\Runtime\Contracts\QueryConnectionInterface;

/**
 * Adapts marko/database's ConnectionInterface to the MCP QueryConnectionInterface
 * so the query_database tool can run SELECT queries against the live app database.
 */
readonly class MarkoQueryConnection implements QueryConnectionInterface
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function query(
        string $sql,
        array $params = [],
    ): array
    {
        return array_values($this->connection->query($sql, $params));
    }
}
