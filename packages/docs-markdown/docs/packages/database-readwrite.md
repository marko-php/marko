---
title: marko/database-readwrite
description: Read/write connection splitting with replica routing for Marko database connections.
---

Read/write connection splitting with replica routing for Marko database connections. Wraps any existing [`marko/database`](/docs/packages/database/) driver connection — all write operations and transactions route to the primary, all reads route to one of your configured replicas. Uses the decorator pattern: `ReadWriteConnection` implements `ConnectionInterface` and `TransactionInterface` so the rest of the application code is unchanged.

## Installation

```bash
composer require marko/database-readwrite
```

This automatically installs `marko/database` (the interface package) as a dependency.

## Configuration

Set `driver` to `readwrite` in your `config/database.php`, then declare a `connections` block with your write primary and one or more read replicas:

```php title="config/database.php"
<?php

declare(strict_types=1);

return [
    'driver' => 'readwrite',
    'connections' => [
        'write' => [
            'driver'   => 'pgsql',
            'host'     => $_ENV['DB_WRITE_HOST'] ?? 'localhost',
            'port'     => (int) ($_ENV['DB_WRITE_PORT'] ?? 5432),
            'database' => $_ENV['DB_DATABASE'] ?? 'marko',
            'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
        ],
        'read' => [
            [
                'driver'   => 'pgsql',
                'host'     => $_ENV['DB_READ_HOST_1'] ?? 'replica-1',
                'port'     => (int) ($_ENV['DB_READ_PORT_1'] ?? 5432),
                'database' => $_ENV['DB_DATABASE'] ?? 'marko',
                'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
            ],
            [
                'driver'   => 'pgsql',
                'host'     => $_ENV['DB_READ_HOST_2'] ?? 'replica-2',
                'port'     => (int) ($_ENV['DB_READ_PORT_2'] ?? 5432),
                'database' => $_ENV['DB_DATABASE'] ?? 'marko',
                'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
            ],
        ],
        'read_strategy' => 'random',  // 'random' (default) or 'weighted'
    ],
];
```

### Config Schema

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `connections.write` | `array` | Yes | Single write (primary) connection config |
| `connections.read` | `array[]` | Yes | One or more replica connection configs |
| `connections.read_strategy` | `string` | No | Replica selection strategy: `random` (default) or `weighted` |
| `read[n].weight` | `int` | No | Required when `read_strategy` is `weighted`; positive integer |

Each connection config inside `write` and `read[]` follows the same structure as a standalone driver config (e.g., `marko/database-pgsql`).

## Replica Strategies

### Random (default)

Selects a replica uniformly at random on each read query. Use when all replicas have similar capacity:

```php title="config/database.php"
'connections' => [
    // ...
    'read_strategy' => 'random',
],
```

### Weighted

Routes a proportional share of read traffic to each replica based on its `weight`. Use when replicas have different hardware or capacity:

```php title="config/database.php"
'connections' => [
    'write' => [ /* primary config */ ],
    'read' => [
        [
            'driver' => 'pgsql',
            'host'   => 'replica-1',
            // ...
            'weight' => 3,  // receives 3/4 of reads
        ],
        [
            'driver' => 'pgsql',
            'host'   => 'replica-2',
            // ...
            'weight' => 1,  // receives 1/4 of reads
        ],
    ],
    'read_strategy' => 'weighted',
],
```

Weights are positive integers. The probability of selecting a replica equals its weight divided by the total of all weights. Each `weight` must be a positive integer or it is rejected at config parse time.

:::note
`WeightedReplicaSelector` calculates probabilities based on the original replica list. When a replica fails and is removed during a request, the weights for the remaining replicas are not rebalanced. This is a known v1 limitation.
:::

## Sticky Writes

After any write operation or transaction, subsequent reads within the same request are routed to the write connection rather than a replica. This prevents stale reads caused by replication lag.

The sticky flag is set by:

- `execute()` — any INSERT, UPDATE, DELETE, or DDL statement
- `beginTransaction()` — entering a transaction

```php
use Marko\Database\Connection\ConnectionInterface;

class OrderService
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function placeOrder(array $data): int
    {
        // Writes set the sticky flag
        $orderId = $this->connection->execute(
            'INSERT INTO orders (user_id, total) VALUES (?, ?)',
            [$data['user_id'], $data['total']],
        );

        // This query routes to the write connection (sticky), not a replica,
        // so it sees the order that was just inserted
        return $this->connection->query(
            'SELECT id FROM orders WHERE id = ?',
            [$orderId],
        )[0]['id'];
    }
}
```

The sticky flag persists until `resetStickyState()` is called. In a PHP-FPM application this happens automatically because each request runs in a fresh process. In long-running processes you must call it manually (see [Long-Running Processes](#long-running-processes)).

## `prepare()` Policy

`prepare()` always routes to the write connection. Prepared statements are typically used for bulk writes or repeated mutation patterns, so routing them to the primary is the safe default. There are no production callers of `prepare()` in the current Marko core, so this policy has no performance impact on stock setups.

## Single-Request Fallback

When a read query fails on a replica with a `PDOException` or `MarkoException`, `ReadWriteConnection` removes that replica from the candidate pool and retries the query on the next available replica. This continues until:

- A replica responds successfully, or
- All replicas are exhausted, at which point `ReadException` is thrown

```
ReadException: All replicas failed to execute the query: <replica-1 message>; <replica-2 message>
Context: While attempting to route a read query to an available replica
Suggestion: Check that at least one replica is reachable and accepting connections
```

Fallback is per-request only. The replica pool is rebuilt fresh on the next request (PHP-FPM) or the next call to `resetStickyState()` (long-running processes).

:::caution
Sticky writes (via `execute()` or `beginTransaction()`) bypass all replicas entirely --- there is no fallback path. If the write connection is unavailable the underlying driver exception is propagated directly.
:::

## Long-Running Processes

In PHP-FPM the sticky flag is cleared automatically at the end of each request because each request is a new process. In a queue worker or other long-running process the sticky flag persists for the lifetime of the process. Call `resetStickyState()` between jobs to restore replica routing:

```php
use Marko\Database\ReadWrite\Connection\ReadWriteConnection;
use Marko\Database\Connection\ConnectionInterface;

class JobWorker
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function run(Job $job): void
    {
        try {
            $job->handle();
        } finally {
            // Always reset between jobs — even if the job threw
            if ($this->connection instanceof ReadWriteConnection) {
                $this->connection->resetStickyState();
            }
        }
    }
}
```

## Multi-Driver Limitation

`ReadWriteConnection` binds `ConnectionInterface` as a container instance. This is the same interface binding used by all other database drivers, so only one driver can be active at a time. You cannot, for example, have a PostgreSQL read/write split alongside a MySQL connection in the same container.

This constraint is inherited from the single-binding design of `ConnectionInterface` in `marko/database` and applies equally to `marko/database-pgsql` and `marko/database-mysql`.

## Customization

Override `ReadWriteConnection` using the [Preferences](/docs/concepts/dependency-injection/#overriding-another-modules-bindings) pattern. Define a `Preference` in your module's `module.php` to substitute your own implementation wherever `ReadWriteConnection` is resolved:

```php title="module.php"
<?php

declare(strict_types=1);

use Marko\Database\ReadWrite\Connection\ReadWriteConnection;
use Acme\Database\ReadWrite\Connection\CustomReadWriteConnection;

return [
    'preferences' => [
        ReadWriteConnection::class => CustomReadWriteConnection::class,
    ],
];
```

Your `CustomReadWriteConnection` must extend `ReadWriteConnection` (or independently implement `ConnectionInterface` and `TransactionInterface`).

## API Reference

### ReadWriteConnection

Implements `ConnectionInterface` and `TransactionInterface`. Routes reads to replicas and writes to the primary.

| Method | Routes To | Description |
|--------|-----------|-------------|
| `query(string $sql, array $bindings = []): array` | Read (replica or write if sticky) | Execute a SELECT and return all rows |
| `execute(string $sql, array $bindings = []): int` | Write (sets sticky) | Execute a write statement; returns affected row count |
| `prepare(string $sql): StatementInterface` | Write (always) | Prepare a statement for repeated execution |
| `lastInsertId(): int` | Write | Get the last auto-increment ID |
| `connect(): void` | Write | Establish the write connection |
| `disconnect(): void` | Write | Close the write connection |
| `isConnected(): bool` | Write | Check if the write connection is open |
| `beginTransaction(): void` | Write (sets sticky) | Start a transaction on the write connection |
| `commit(): void` | Write | Commit the current transaction |
| `rollback(): void` | Write | Roll back the current transaction |
| `inTransaction(): bool` | Write | Check if a transaction is active |
| `transaction(callable $callback): mixed` | Write | Run a callback inside an auto-managed transaction |
| `resetStickyState(): void` | — | Clear the sticky flag; subsequent reads route to replicas again |

### ReadException

Thrown when all replicas fail during a single read query.

| Factory | Description |
|---------|-------------|
| `ReadException::allReplicasFailed(array $messages): self` | Builds the exception with a semicolon-joined summary of each replica's error message |

### ReadWriteConnectionConfig

Parses and validates the `connections` block from `config/database.php`.

| Factory | Throws | Description |
|---------|--------|-------------|
| `ReadWriteConnectionConfig::fromArray(array $config): self` | `ReadWriteConfigException` | Validates presence of `write`, non-empty `read[]`, and valid `read_strategy` |

### RandomReplicaSelector

Selects a replica uniformly at random. Default strategy.

### WeightedReplicaSelector

Selects a replica proportionally to its configured weight. Constructed with an array of integer weights parallel to the replica array.
