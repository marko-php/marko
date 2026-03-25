---
title: marko/database-pgsql
description: PostgreSQL driver for the Marko framework database layer — provides connection, query building, introspection, and schema migration support.
---

PostgreSQL driver for the Marko framework database layer. Implements `ConnectionInterface`, `QueryBuilderInterface`, `IntrospectorInterface`, and `SqlGeneratorInterface` from [`marko/database`](/docs/packages/database/) using PostgreSQL-native features --- JSONB, UUID, SERIAL types, PDO-compatible `?` parameter placeholders, and double-quoted identifiers.

## Installation

```bash
composer require marko/database-pgsql
```

This automatically installs `marko/database` (the interface package) as a dependency.

## Configuration

Create a configuration file at `config/database.php`:

```php title="config/database.php"
<?php

declare(strict_types=1);

return [
    'driver' => 'pgsql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => (int) ($_ENV['DB_PORT'] ?? 5432),
    'database' => $_ENV['DB_DATABASE'] ?? 'marko',
    'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'schema' => 'public',
];
```

### Environment Variables

Set these in your `.env` file:

```env
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

## Usage

Once configured, the PostgreSQL driver is automatically used when you interact with the database. See [`marko/database`](/docs/packages/database/) for entity definition and repository usage.

```php
use Marko\Database\Connection\ConnectionInterface;

class MyService
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function doSomething(): void
    {
        // Connection is automatically PostgreSQL
        $result = $this->connection->query('SELECT * FROM users');
    }
}
```

### Query Builder

The PostgreSQL query builder uses PDO-compatible `?` placeholders and double-quoted identifiers. It supports selects, inserts, updates, deletes, joins, ordering, limits, and raw queries:

```php
use Marko\Database\Query\QueryBuilderInterface;

class PostRepository
{
    public function __construct(
        private QueryBuilderInterface $queryBuilder,
    ) {}

    public function findPublished(): array
    {
        return $this->queryBuilder
            ->table('posts')
            ->select('id', 'title', 'published_at')
            ->where('status', '=', 'published')
            ->orderBy('published_at', 'DESC')
            ->limit(10)
            ->get();
    }
}
```

### Transactions

`PgSqlConnection` implements `TransactionInterface`, providing `beginTransaction()`, `commit()`, `rollback()`, and a `transaction()` wrapper that auto-commits on success and rolls back on exception:

```php
use Marko\Database\Connection\ConnectionInterface;

$connection->transaction(function () use ($connection): void {
    $connection->execute('INSERT INTO accounts (name) VALUES (?)', ['Acme']);
    $connection->execute('INSERT INTO ledger (account, amount) VALUES (?, ?)', ['Acme', 100]);
});
```

Nested transactions are not supported --- calling `beginTransaction()` while already in a transaction throws `TransactionException`.

## Driver-Specific Notes

### PostgreSQL Version

This driver supports PostgreSQL 14+. Older versions may work but are not tested.

### Schema

The default schema is `public`. You can specify a different schema in the configuration:

```php
'schema' => 'my_schema',
```

### Native Types

PostgreSQL has excellent support for advanced data types. Marko leverages these native types:

| PHP Type | PostgreSQL Type |
|---|---|
| `array` | JSONB |
| `DateTimeImmutable` | TIMESTAMPTZ |
| `BackedEnum` | VARCHAR (enum values as strings) |

### JSONB Columns

PostgreSQL's JSONB type is fully supported and recommended over JSON for better indexing and query performance:

```php
#[Column(type: 'jsonb')]
public array $metadata = [];
```

### UUID Primary Keys

PostgreSQL has native UUID support. Use the `type` parameter:

```php
#[Column(primaryKey: true, type: 'uuid', default: 'gen_random_uuid()')]
public string $id;
```

## API Reference

### PgSqlConnection

Implements `ConnectionInterface` and `TransactionInterface`. Connects lazily on first query.

| Method | Description |
|---|---|
| `connect(): void` | Establish the PDO connection (called automatically) |
| `disconnect(): void` | Close the connection |
| `isConnected(): bool` | Check if currently connected |
| `query(string $sql, array $bindings = []): array` | Execute a query and return rows as associative arrays |
| `execute(string $sql, array $bindings = []): int` | Execute a statement and return the affected row count |
| `prepare(string $sql): StatementInterface` | Prepare a statement for repeated execution |
| `lastInsertId(): int` | Get the last inserted ID |
| `beginTransaction(): void` | Start a transaction |
| `commit(): void` | Commit the current transaction |
| `rollback(): void` | Roll back the current transaction |
| `inTransaction(): bool` | Check if a transaction is active |
| `transaction(callable $callback): mixed` | Execute a callback inside an auto-managed transaction |

### PgSqlStatement

Implements `StatementInterface`. Wraps a prepared PDO statement.

| Method | Description |
|---|---|
| `execute(array $bindings = []): bool` | Execute the prepared statement with bindings |
| `fetchAll(): array` | Fetch all rows as associative arrays |
| `fetch(): ?array` | Fetch the next row, or `null` if none |
| `rowCount(): int` | Get the number of affected rows |

### PgSqlQueryBuilder

Implements `QueryBuilderInterface`. Fluent builder for PostgreSQL queries.

| Method | Description |
|---|---|
| `table(string $table): static` | Set the target table |
| `select(string ...$columns): static` | Choose columns (defaults to `*`) |
| `where(string $column, string $operator, mixed $value): static` | Add a WHERE condition |
| `orWhere(string $column, string $operator, mixed $value): static` | Add an OR WHERE condition |
| `whereIn(string $column, array $values): static` | Add a WHERE IN condition |
| `whereNull(string $column): static` | Add a WHERE IS NULL condition |
| `whereNotNull(string $column): static` | Add a WHERE IS NOT NULL condition |
| `join(string $table, string $first, string $operator, string $second): static` | INNER JOIN |
| `leftJoin(string $table, string $first, string $operator, string $second): static` | LEFT JOIN |
| `rightJoin(string $table, string $first, string $operator, string $second): static` | RIGHT JOIN |
| `orderBy(string $column, string $direction = 'ASC'): static` | Add ORDER BY clause |
| `limit(int $limit): static` | Set LIMIT |
| `offset(int $offset): static` | Set OFFSET |
| `get(): array` | Execute SELECT and return all rows |
| `first(): ?array` | Execute SELECT with LIMIT 1 and return the row or `null` |
| `insert(array $data): int` | Insert a row and return the `id` via RETURNING |
| `update(array $data): int` | Update matching rows and return affected count |
| `delete(): int` | Delete matching rows and return affected count |
| `count(): int` | Return the count of matching rows |
| `raw(string $sql, array $bindings = []): array` | Execute a raw SQL query |

### PgSqlIntrospector

Implements `IntrospectorInterface`. Reads schema metadata from `information_schema` and `pg_catalog`.

| Method | Description |
|---|---|
| `getTables(): array` | List all table names in the configured schema |
| `getTable(string $name): ?Table` | Get full table metadata (columns, indexes, foreign keys) |
| `tableExists(string $name): bool` | Check if a table exists |
| `getColumns(string $table): array` | Get column definitions for a table |
| `getIndexes(string $table): array` | Get non-primary-key indexes for a table |
| `getForeignKeys(string $table): array` | Get foreign key constraints for a table |
| `getPrimaryKey(string $table): array` | Get primary key column names |

### PgSqlGenerator

Implements `SqlGeneratorInterface`. Generates PostgreSQL DDL for schema migrations --- uses `SERIAL`/`BIGSERIAL` for auto-increment, double-quoted identifiers, and PostgreSQL-specific types (JSONB, BYTEA, etc.).

| Method | Description |
|---|---|
| `generateUp(SchemaDiff $diff): array` | Generate forward-migration SQL statements |
| `generateDown(SchemaDiff $diff): array` | Generate rollback SQL statements |
| `generateCreateTable(Table $table): string` | Generate a CREATE TABLE statement |
| `generateDropTable(string $tableName): string` | Generate a DROP TABLE statement |
| `generateAddColumn(string $table, Column $column): string` | Generate an ALTER TABLE ADD COLUMN statement |
| `generateDropColumn(string $table, string $columnName): string` | Generate an ALTER TABLE DROP COLUMN statement |
| `generateModifyColumn(string $table, Column $column, Column $oldColumn): string` | Generate ALTER COLUMN type/nullability/default changes |
| `generateAddIndex(string $table, Index $index): string` | Generate a CREATE INDEX statement |
| `generateDropIndex(string $table, string $indexName): string` | Generate a DROP INDEX statement |
| `generateAddForeignKey(string $table, ForeignKey $foreignKey): string` | Generate an ADD CONSTRAINT FOREIGN KEY statement |
| `generateDropForeignKey(string $table, string $keyName): string` | Generate a DROP CONSTRAINT statement |

### ConnectionException

Thrown when a PostgreSQL connection fails. Includes the host, port, and database name in the message, with a suggestion to verify server status and credentials.
