---
title: marko/database-mysql
description: MySQL and MariaDB driver for the Marko framework database layer.
---

MySQL and MariaDB driver for the Marko framework database layer. Provides a MySQL-specific connection, query builder, SQL generator, and schema introspector --- all wired automatically when you install the package.

Implements `ConnectionInterface`, `QueryBuilderInterface`, `SqlGeneratorInterface`, and `IntrospectorInterface` from [`marko/database`](/docs/packages/database/).

## Installation

```bash
composer require marko/database-mysql
```

This automatically installs `marko/database` (the interface package) as a dependency.

## Configuration

Create a configuration file at `config/database.php`:

```php title="config/database.php"
<?php

declare(strict_types=1);

return [
    'driver' => 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
    'database' => $_ENV['DB_DATABASE'] ?? 'marko',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
```

### Environment Variables

Set these in your `.env` file:

```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

## Driver-Specific Notes

### MySQL vs MariaDB

This driver supports both MySQL 8.0+ and MariaDB 10.5+. Both are tested and fully supported.

### Character Set

The default charset is `utf8mb4` which supports the full Unicode range including emojis. This is the recommended setting for new applications.

### Strict Mode

Marko enables MySQL strict mode by default. This ensures data integrity by rejecting invalid data rather than silently truncating or coercing values.

### JSON Columns

MySQL's native JSON type is fully supported. Use the `type: 'json'` parameter in your `#[Column]` attribute:

```php
use Marko\Database\Attributes\Column;

#[Column(type: 'json')]
public array $metadata = [];
```

## Usage

Once configured, the MySQL driver is automatically used when you interact with the database. See [`marko/database`](/docs/packages/database/) for entity definition and repository usage.

```php
use Marko\Database\Connection\ConnectionInterface;

class MyService
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function doSomething(): void
    {
        // Connection is automatically MySQL
        $result = $this->connection->query('SELECT * FROM users');
    }
}
```

## API Reference

### Connection

`MySqlConnection` implements `ConnectionInterface` and `TransactionInterface`. It wraps PDO with lazy connection --- the database connection is not established until the first query.

| Method | Description |
|---|---|
| `query(string $sql, array $bindings = []): array` | Execute a query and return all rows as associative arrays |
| `execute(string $sql, array $bindings = []): int` | Execute a statement and return the affected row count |
| `prepare(string $sql): StatementInterface` | Prepare a statement for repeated execution |
| `lastInsertId(): int` | Get the last auto-increment ID |
| `connect(): void` | Explicitly open the database connection |
| `disconnect(): void` | Close the connection |
| `isConnected(): bool` | Check whether the connection is open |

### Transactions

`MySqlConnection` also implements `TransactionInterface`:

| Method | Description |
|---|---|
| `beginTransaction(): void` | Start a transaction (throws on nested transactions) |
| `commit(): void` | Commit the current transaction |
| `rollback(): void` | Roll back the current transaction |
| `inTransaction(): bool` | Check whether a transaction is active |
| `transaction(callable $callback): mixed` | Execute a callback inside a transaction --- auto-commits on success, rolls back on exception |

### Query Builder

`MySqlQueryBuilder` implements `QueryBuilderInterface` with a fluent API:

| Method | Description |
|---|---|
| `table(string $table): static` | Set the target table |
| `select(string ...$columns): static` | Choose columns to return |
| `where(string $column, string $operator, mixed $value): static` | Add a WHERE condition |
| `orWhere(string $column, string $operator, mixed $value): static` | Add an OR WHERE condition |
| `whereIn(string $column, array $values): static` | Add a WHERE IN condition |
| `whereNull(string $column): static` | Add a WHERE IS NULL condition |
| `whereNotNull(string $column): static` | Add a WHERE IS NOT NULL condition |
| `join(string $table, string $first, string $operator, string $second): static` | Inner join |
| `leftJoin(string $table, string $first, string $operator, string $second): static` | Left join |
| `rightJoin(string $table, string $first, string $operator, string $second): static` | Right join |
| `orderBy(string $column, string $direction = 'ASC'): static` | Order results |
| `limit(int $limit): static` | Limit result count |
| `offset(int $offset): static` | Skip rows |
| `get(): array` | Execute and return all matching rows |
| `first(): ?array` | Execute and return the first row, or `null` |
| `insert(array $data): int` | Insert a row and return the last insert ID |
| `update(array $data): int` | Update matching rows and return the affected count |
| `delete(): int` | Delete matching rows and return the affected count |
| `count(): int` | Return the count of matching rows |
| `raw(string $sql, array $bindings = []): array` | Execute raw SQL |

### SQL Generator

`MySqlGenerator` implements `SqlGeneratorInterface` --- produces MySQL-specific DDL from schema diffs (used by the migration system).

| Abstract Type | MySQL Type |
|---|---|
| `integer` / `int` | `INT` |
| `bigint` | `BIGINT` |
| `smallint` | `SMALLINT` |
| `tinyint` | `TINYINT` |
| `string` | `VARCHAR(n)` (default 255) |
| `text` | `TEXT` |
| `boolean` / `bool` | `TINYINT(1)` |
| `datetime` | `DATETIME` |
| `date` | `DATE` |
| `time` | `TIME` |
| `timestamp` | `TIMESTAMP` |
| `decimal` | `DECIMAL(10,2)` |
| `float` | `FLOAT` |
| `double` | `DOUBLE` |
| `blob` / `binary` | `BLOB` |
| `json` | `JSON` |

### Introspector

`MySqlIntrospector` implements `IntrospectorInterface` --- reads the live database schema via `information_schema` for use by the migration diff calculator.

| Method | Description |
|---|---|
| `getTables(): array` | List all table names in the database |
| `getTable(string $name): ?Table` | Get a full `Table` schema object (columns, indexes, foreign keys) |
| `tableExists(string $name): bool` | Check whether a table exists |
| `getColumns(string $table): array` | Get column definitions for a table |
| `getIndexes(string $table): array` | Get index definitions for a table |
| `getForeignKeys(string $table): array` | Get foreign key definitions for a table |
| `getPrimaryKey(string $table): array` | Get primary key column names for a table |
