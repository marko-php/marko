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

use Marko\Config\Env;

return [
    'driver' => 'pgsql',
    'host' => Env::string('DB_HOST', 'localhost'),
    'port' => Env::int('DB_PORT', 5432, min: 1, max: 65535),
    'database' => Env::string('DB_DATABASE', 'marko'),
    'username' => Env::string('DB_USERNAME', 'postgres'),
    'password' => Env::string('DB_PASSWORD', ''),
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

### TLS

Set `sslmode` in `config/database.php`; it is passed to libpq unchanged. Use `verify-full` for any server reached over a network: it encrypts the connection and checks that the server certificate is valid for `host`.

```php title="config/database.php"
return [
    'driver' => 'pgsql',
    // ...
    'sslmode' => Env::string('DB_SSLMODE', 'verify-full'),
    'ssl_ca' => Env::nullableString('DB_SSL_CA'),     // CA that signed the server certificate (sslrootcert)
    'ssl_cert' => Env::nullableString('DB_SSL_CERT'), // optional client certificate (sslcert)
    'ssl_key' => Env::nullableString('DB_SSL_KEY'),   // optional client key (sslkey)
];
```

| `sslmode` | Encrypted | Server verified |
|---|---|---|
| unset / `prefer` | If the server offers TLS --- an attacker in between can strip it | No |
| `require` | Yes | No |
| `verify-ca` | Yes | CA only, not the host name |
| `verify-full` | Yes | CA and host name |

Without `ssl_ca`, `verify-ca` and `verify-full` read the CA from `~/.postgresql/root.crt`. `ssl_cert` and `ssl_key` must be set together.

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

Transactions nest: a `beginTransaction()` (or `transaction()`) inside an open transaction issues `SAVEPOINT marko_sp_N`, and the matching `commit()` / `rollback()` issues `RELEASE SAVEPOINT` / `ROLLBACK TO SAVEPOINT`. Rolling back to a savepoint also clears PostgreSQL's "current transaction is aborted" state, so the outer transaction can carry on after a failed statement in a nested one. See [Nested Transactions](/docs/packages/database/#nested-transactions) and [After-Commit Callbacks](/docs/packages/database/#after-commit-callbacks).

Pass `attempts` to retry the outermost transaction on a deadlock (`40P01`) or serialization failure (`40001`), for example under `SERIALIZABLE` isolation. Set the isolation level as the first statement of the callback, so every attempt gets it:

```php
$connection->transaction(function () use ($connection): void {
    $connection->execute('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
    // ...
}, attempts: 3);
```

See [Concurrency Errors and Retries](/docs/packages/database/#concurrency-errors-and-retries).

Row locks compile to `FOR UPDATE` / `FOR SHARE`, with optional `SKIP LOCKED` / `NOWAIT`, appended after `LIMIT`/`OFFSET`. PostgreSQL rejects `FOR UPDATE` together with `DISTINCT`, `GROUP BY` or `HAVING`, and on the nullable side of an outer join.

`upsert()` compiles to `INSERT ... ON CONFLICT (...) DO UPDATE SET col = EXCLUDED.col`, or `DO NOTHING` when there is nothing to update. PostgreSQL requires a unique index or constraint on exactly the `$uniqueBy` columns, and rejects a batch that contains the same conflict key twice (`ON CONFLICT DO UPDATE command cannot affect row a second time`). A statement can bind at most 65,535 values, so split very large batches.

## Driver-Specific Notes

### PostgreSQL Version

This driver supports PostgreSQL 14+. Older versions may work but are not tested.

### Schema

Connections use the database role's default `search_path` (normally `public`). There is no `schema` configuration key; to work in a different schema, set the role's `search_path` in PostgreSQL (for example, `ALTER ROLE app SET search_path TO my_schema;`).

### Session Time Zone

After `SET NAMES`, every connection runs `SET TIME ZONE`, so the session follows [`database.timezone`](/docs/packages/database/#the-database-session-time-zone) whatever the server's or the role's `TimeZone` is, and a reconnect is pinned again:

| `database.timezone` | Statement |
|---|---|
| `UTC` (default) | `SET TIME ZONE 'UTC'` |
| A region such as `America/New_York` | `SET TIME ZONE 'America/New_York'` |
| A fixed offset or abbreviation such as `+05:30` or `CEST` | `SET TIME ZONE INTERVAL '+05:30' HOUR TO MINUTE` |

A fixed offset is sent as an interval because PostgreSQL reads a bare `'+05:30'` as a POSIX zone and inverts its sign. `DEFAULT CURRENT_TIMESTAMP`, `NOW()` and `LOCALTIMESTAMP` on `TIMESTAMP` columns then give the time in `database.timezone`. PostgreSQL ships its own zone data, so any zone in `pg_timezone_names` works. One the server rejects fails `connect()` with a `ConnectionException` naming the zone.

Behind PgBouncer in transaction pooling mode, session settings (`SET NAMES` and `SET TIME ZONE` alike) don't stick to the server connection your next statement runs on. Use session pooling, or set the same zone on the role (`ALTER ROLE app SET timezone = 'UTC'`).

### Native Types

PostgreSQL has excellent support for advanced data types. Marko leverages these native types:

| PHP Type | PostgreSQL Type |
|---|---|
| `array` | JSONB |
| `DateTimeImmutable` | TIMESTAMPTZ |
| `BackedEnum` | VARCHAR (enum values as strings) |

### JSONB Columns

PostgreSQL stores `#[Column(type: 'json')]` properties as `JSONB` natively --- the binary format with better indexing and query performance than plain `JSON`. Use `array` or `?array` as the property type:

```php
use Marko\Database\Attributes\Column;

#[Column(type: 'json')]
public array $metadata = [];

#[Column(type: 'json')]
public ?array $settings = null;
```

Values are serialized and deserialized automatically. The root value must be an array --- top-level JSON scalars are not supported. See [marko/database](/docs/packages/database/) for JSON query operators (`whereJsonContains`, arrow-path syntax, `@>` containment, GIN index setup).

### UUID Primary Keys

PostgreSQL has native UUID support. Use the `type` and `default` parameters on the primary key column, and `generated: true` to let the database generate the key:

```php
use Marko\Database\Attributes\Column;

#[Column(primaryKey: true, type: 'uuid', default: 'gen_random_uuid()', generated: true)]
public string $id;
```

This generates `"id" UUID DEFAULT gen_random_uuid() PRIMARY KEY` (`gen_random_uuid()` is built into PostgreSQL 13+). A call to a function with no arguments is read as an expression, not a string; see [Column Defaults](/docs/packages/database/#column-defaults) for the other forms. With `generated: true`, `Repository::save()` and `insertBatch()` leave an unset key out of the `INSERT` and read the generated UUID back with `INSERT ... RETURNING`, so `$article->id` holds it after saving. `PgSqlConnection::supportsReturning()` returns `true`. Without `generated: true`, the key must be set in PHP before saving; see [Database-generated keys](/docs/packages/database/#database-generated-keys).

`Repository::find()` and `findOrFail()` accept `int|string`, so UUID-keyed repositories work without any additional configuration:

```php
$article = $articleRepository->find('018e2b3c-d1a2-7000-a1b2-c3d4e5f60708');
```

### Type Changes

Without a `USING` clause, `ALTER COLUMN ... TYPE` only makes conversions PostgreSQL considers safe, so `VARCHAR` to `INTEGER` fails with `column "quantity" cannot be cast automatically to type integer`. A generated type change always casts explicitly, in both the up and the down migration:

```sql
ALTER TABLE "items" ALTER COLUMN "quantity" DROP DEFAULT,
    ALTER COLUMN "quantity" TYPE INTEGER USING "quantity"::INTEGER,
    ALTER COLUMN "quantity" SET DEFAULT 0
```

- **The cast is explicit, not lenient.** A row that doesn't convert (`'abc'` to `INTEGER`) fails the migration inside its transaction, and nothing changes.
- **The default is dropped before the type changes and set again after it.** An old default that can't be cast (`'0'` on a `VARCHAR` becoming `INTEGER`) never blocks the change. An auto-increment column keeps its sequence default, so widening an `integer` key to `bigint` keeps generating ids from the same sequence.
- **Anything a cast can't express** (splitting a column, parsing a custom format) still needs a hand-written migration.

#### Auto-Increment Keys

A `SERIAL` sequence has its own type, and its `MAXVALUE` comes from that type. Widening only the column would leave the sequence stopping at 2,147,483,647. When an auto-increment key changes between `smallint`, `integer` and `bigint`, the generated migration changes the column and the sequence that feeds it in one statement:

```sql
DO $$
DECLARE
    sequence_name text := pg_get_serial_sequence('"posts"', 'id');
BEGIN
    IF sequence_name IS NULL THEN
        RAISE EXCEPTION USING MESSAGE = 'Column "id" of table "posts" is auto-increment, but no sequence is owned by it, ...';
    END IF;
    ALTER TABLE "posts" ALTER COLUMN "id" TYPE BIGINT USING "id"::BIGINT;
    EXECUTE format('ALTER SEQUENCE %s AS BIGINT', sequence_name);
END
$$
```

- **Widening raises `MAXVALUE` with the type**, so the insert after id 2,147,483,647 gets 2,147,483,648.
- **The down migration narrows both back.** When the sequence has already passed the smaller type's range, PostgreSQL refuses (`RESTART value (...) cannot be greater than MAXVALUE (...)`), and the column keeps its wider type.
- **The sequence must be owned by the column.** `SERIAL` and `BIGSERIAL` columns, including every key Marko creates, already are. A key that reads from a sequence it doesn't own fails before anything changes, with an error naming the table and column. Run `ALTER SEQUENCE ... OWNED BY "posts"."id"`, then run the migration again.
- **Names containing `$$` are safe.** The block is dollar-quoted with `$$` only when that cannot occur inside it. Otherwise it uses `$marko$` (or `$marko_1$`, `$marko_2$`, ...), so a table or column name can never end the block early.
- **Identity columns** (`GENERATED ... AS IDENTITY`) work the same way. PostgreSQL already changes their sequence with the column, so the sequence step changes nothing.

### Expression Defaults

PostgreSQL stores a deparsed form of an expression default, with its own casts and parentheses: `now() + interval '1 day'` comes back as `(now() + '1 day'::interval)`, and `'a' || 'b'` on a `text` column as `('a'::text || 'b'::text)`. Write the expression as you would in SQL:

```php
use Marko\Database\Attributes\Column;
use Marko\Database\Schema\Expression;

#[Column(type: 'timestamp', default: new Expression("now() + interval '1 day'"))]
public DateTimeImmutable $expiresAt;
```

When an `Expression` default differs from the stored one, `PgSqlIntrospector::matchesStoredDefault()` opens a transaction, creates `CREATE TEMP TABLE marko_default_probe` with one column of the real column's type (`format_type()`) and your expression as its default, reads back the default PostgreSQL stored, and rolls back. Inside an open transaction it uses a savepoint. If both defaults deparse to the same text, `db:diff` reports no change. If PostgreSQL rejects the expression or the probe table, the diff fails with an `ExpressionDefaultProbeException` naming the column, expression and database error; the post-migration drift check of `db:migrate` outside development only warns. The connection's user needs the `TEMPORARY` privilege on the database, which PostgreSQL grants to everyone by default. See [Column Defaults](/docs/packages/database/#column-defaults).

### Unique Constraints

An inline `UNIQUE` (a new table or column with `unique: true`) creates a unique constraint, `<table>_<column>_key`, which `DROP INDEX` cannot remove. The introspector marks the index behind it (`Index::$constraint`), so removing `unique: true` from the column generates:

```sql
ALTER TABLE "users" DROP CONSTRAINT "users_email_key"
```

and the down migration restores it with `ALTER TABLE "users" ADD CONSTRAINT "users_email_key" UNIQUE ("email")`. Adding `unique: true` to an existing column creates a unique index, `<table>_<column>_unique`, dropped with `DROP INDEX` in down. PostgreSQL silently truncates identifiers to 63 bytes, so Marko [shortens a derived name over 63 bytes with a hash](/docs/packages/database/#index-and-foreign-key-names) (the `_unique`, `_index` and `fk_` names) and rejects a declared `#[Index]` name over 63 bytes, rather than let the database hold a name the entity does not. See [Unique Columns on Existing Tables](/docs/packages/database/#unique-columns-on-existing-tables).

### Partial Indexes

`#[Index(..., where: '...')]` generates `CREATE INDEX ... WHERE <predicate>`, and the introspector reads the predicate back from `pg_indexes`, so a partial index round-trips without drift. See [Partial Indexes](/docs/packages/database/#partial-indexes).

## Postgres-Wire-Compatible Databases (CockroachDB, YugabyteDB, etc.)

`PgSqlConnection` speaks pure PDO over the PostgreSQL wire protocol and contains no Postgres-specific dialect logic, so any database that is wire-compatible with PostgreSQL can reuse it without a custom driver. Point your `DB_HOST` at CockroachDB, YugabyteDB, or another compatible engine and the rest of the stack works as-is. See the [Wire-compatible database variants](/docs/packages/database/#wire-compatible-database-variants) section of the database guide for the full pattern and configuration example.

## API Reference

### PgSqlConnection

Implements `ConnectionInterface`, `TransactionInterface`, `PendingAfterCommitInterface` and `ResettableInterface`. Connects lazily on first query. The driver module registers one shared instance per request (per worker under a long-running runtime), and `TransactionInterface` resolves to that same instance. See [Transactions](/docs/packages/database/#transactions).

| Method | Description |
|---|---|
| `connect(): void` | Establish the PDO connection (called automatically) |
| `disconnect(): void` | Close the connection and discard the transaction depth and pending callbacks |
| `isConnected(): bool` | Check if currently connected |
| `query(string $sql, array $bindings = []): array` | Execute a query and return rows as associative arrays |
| `execute(string $sql, array $bindings = []): int` | Execute a statement and return the affected row count |
| `prepare(string $sql): StatementInterface` | Prepare a statement for repeated execution |
| `lastInsertId(): int` | Get the last inserted ID |
| `supportsReturning(): bool` | Always `true`: `INSERT ... RETURNING` is available, so repositories read generated keys back |
| `quoteIdentifier(string $identifier): string` | Quote a table or column name with double quotes through `PgSqlIdentifier` (`group` becomes `"group"`, and `displayName` keeps its case); needs no live connection |
| `beginTransaction(): void` | Start a transaction, or a savepoint when one is open |
| `commit(): void` | Commit the innermost level (`RELEASE SAVEPOINT` when nested); throws `TransactionException` when none is open |
| `rollback(): void` | Roll back the innermost level (`ROLLBACK TO SAVEPOINT` when nested); throws `TransactionException` when none is open |
| `inTransaction(): bool` | Check if a transaction is active |
| `transactionLevel(): int` | Number of open levels (0 outside a transaction) |
| `transaction(callable $callback, int $attempts = 1, int\|Closure\|null $backoff = null): mixed` | Execute a callback inside an auto-managed transaction (a savepoint when nested); the outermost call runs up to `$attempts` times on a deadlock or serialization failure, waiting between attempts as `$backoff` says ([Backoff](/docs/packages/database/#backoff)) |
| `afterCommit(callable $callback): void` | Run the callback after the outermost commit (immediately outside a transaction) |
| `afterRollback(callable $callback): void` | Run the callback if its level rolls back |
| `runPendingAfterCommitCallbacks(): void` | Run the queued `afterCommit()` callbacks without committing (`PendingAfterCommitInterface`); for test helpers such as `RefreshDatabase`, not production code |
| `reset(): void` | Roll back every level left open by a failed request and drop pending callbacks; never opens a connection |

### PgSqlStatement

Implements `StatementInterface`. Wraps a prepared PDO statement.

| Method | Description |
|---|---|
| `execute(array $bindings = []): bool` | Execute the prepared statement with bindings |
| `fetchAll(): array` | Fetch all rows as associative arrays |
| `fetch(): ?array` | Fetch the next row, or `null` if none |
| `rowCount(): int` | Get the number of affected rows |

### PgSqlExceptionTranslator

Turns a `PDOException` raised by `query()`, `execute()`, `prepare()` or `PgSqlStatement::execute()` into a typed exception from `marko/database`, keyed on the SQLSTATE: `23505` unique, `23503` foreign key, `23502` not null, `23514` check, `40P01` `DeadlockException`, `40001` `SerializationFailureException`, `55P03` `LockTimeoutException` (`NOWAIT` and `lock_timeout`), anything else `QueryException`. Failed `BEGIN`, `COMMIT`, `SAVEPOINT`, `RELEASE SAVEPOINT` and `ROLLBACK` statements are translated the same way, so a serialization failure detected at `COMMIT` is a `SerializationFailureException`. The constraint, table and column are parsed from the server message, and the `DETAIL:` line (which echoes row data) is never copied. `PgSqlConnection` and `PgSqlStatement` take it as an optional constructor argument (`exceptionTranslator`). See [Query and Constraint Exceptions](/docs/packages/database/#query-and-constraint-exceptions) and [Concurrency Errors and Retries](/docs/packages/database/#concurrency-errors-and-retries).

| Method | Description |
|---|---|
| `translate(PDOException $exception, string $sql, array $bindings): QueryException` | Map a driver error to the typed exception, keeping the `PDOException` as `getPrevious()` |

### PgSqlQueryBuilder

Implements `QueryBuilderInterface`. Fluent builder for PostgreSQL queries.

| Method | Description |
|---|---|
| `table(string $table, ?string $alias = null): static` | Set the target table, optionally aliased (`table AS alias`) |
| `select(string ...$columns): static` | Choose columns (defaults to `*`) |
| `selectRaw(string $expression, array $bindings = []): static` | Append a raw SQL expression to the SELECT list |
| `where(string $column, string $operator, mixed $value): static` | Add a WHERE condition |
| `orWhere(string $column, string $operator, mixed $value): static` | Add an OR WHERE condition |
| `whereIn(string $column, array $values): static` | Add a WHERE IN condition |
| `whereNotIn(string $column, array $values): static` | Add a WHERE NOT IN condition (an empty list matches every row) |
| `whereColumn(string $first, string $operator, string $second): static` | Compare two columns; both sides are identifiers, never bindings |
| `whereExists(callable\|QueryBuilderInterface $subquery): static` | Add a WHERE EXISTS subquery (a builder, or a callable that configures a fresh one) |
| `whereNotExists(callable\|QueryBuilderInterface $subquery): static` | Add a WHERE NOT EXISTS subquery |
| `whereNull(string $column): static` | Add a WHERE IS NULL condition |
| `whereNotNull(string $column): static` | Add a WHERE IS NOT NULL condition |
| `whereRaw(string $expression, array $bindings = []): static` | Add a raw SQL WHERE condition, AND-combined with other conditions |
| `join(string $table, string $first, string $operator, string $second): static` | INNER JOIN |
| `leftJoin(string $table, string $first, string $operator, string $second): static` | LEFT JOIN |
| `rightJoin(string $table, string $first, string $operator, string $second): static` | RIGHT JOIN |
| `orderBy(string $column, string $direction = 'ASC'): static` | Add ORDER BY clause |
| `orderByRaw(string $expression, string $direction = 'ASC'): static` | Order by a raw SQL expression |
| `limit(int $limit): static` | Set LIMIT |
| `offset(int $offset): static` | Set OFFSET |
| `get(): array` | Execute SELECT and return all rows |
| `first(): ?array` | Execute SELECT with LIMIT 1 and return the row or `null` |
| `insert(array $data, ?string $primaryKey = null): int` | Insert a row and return the generated primary key via `RETURNING`. Defaults to `id`; pass the column name when the table uses a non-`id` primary key. Throws `InsertReturningException` if the `RETURNING` clause does not produce the expected column. |
| `update(array $data): int` | Update matching rows and return affected count |
| `delete(): int` | Delete matching rows and return affected count |
| `count(?string $column = null): int` | Return the count of matching rows (`COUNT(*)` or `COUNT(column)`) |
| `sum(string $column): int\|float` | Return the sum of a column |
| `avg(string $column): int\|float` | Return the average of a column |
| `min(string $column): int\|float` | Return the minimum value of a column |
| `max(string $column): int\|float` | Return the maximum value of a column |
| `distinct(): static` | Add DISTINCT to the SELECT clause |
| `groupBy(string ...$columns): static` | Add GROUP BY columns |
| `having(string $expression, array $bindings = []): static` | Add a HAVING condition |
| `union(QueryBuilderInterface $query): static` | Append a UNION (deduplicates rows) |
| `unionAll(QueryBuilderInterface $query): static` | Append a UNION ALL (keeps duplicates) |
| `whereJsonContains(string $column, mixed $value): static` | WHERE JSONB column @> value (containment) |
| `whereJsonExists(string $path): static` | WHERE JSON key/path exists |
| `whereJsonMissing(string $path): static` | WHERE JSON key/path does not exist |
| `raw(string $sql, array $bindings = []): array` | Execute a raw SQL query |
| `lockForUpdate(): static` | Append `FOR UPDATE` (requires an open transaction) |
| `sharedLock(): static` | Append `FOR SHARE` (requires an open transaction) |
| `skipLocked(): static` | Append `SKIP LOCKED` to the lock |
| `noWait(): static` | Append `NOWAIT` to the lock |
| `upsert(array $rows, array $uniqueBy, ?array $update = null): int` | `INSERT ... ON CONFLICT (...) DO UPDATE` / `DO NOTHING`; returns the affected-row count |

### PgSqlConnectionFactory

Implements `ConnectionFactoryInterface`. Creates `PgSqlConnection` instances from a `DatabaseConfig`. Used by `marko/database-readwrite` to build per-connection instances for the write primary and each read replica.

| Method | Description |
|---|---|
| `make(DatabaseConfig $config): ConnectionInterface` | Create and return a new `PgSqlConnection` for the given config |

The factory hands every connection it makes the container-bound `TransactionBackoff`, so the write primary and the replicas wait between `transaction()` retries the same way as the default connection.

### PgSqlIntrospector

Implements `IntrospectorInterface` and `ExpressionDefaultMatcherInterface`. Reads schema metadata from `information_schema` and `pg_catalog`.

| Method | Description |
|---|---|
| `getTables(): array` | List all table names in the configured schema |
| `getTable(string $name): ?Table` | Get full table metadata (columns, indexes, foreign keys) |
| `tableExists(string $name): bool` | Check if a table exists |
| `getColumns(string $table): array` | Get column definitions for a table. A default that isn't a literal is returned as an `Expression`, and a string literal that reads like a function (`'now()'`) as a `Literal` |
| `getIndexes(string $table): array` | Get non-primary-key indexes for a table. An index behind a unique constraint has `constraint` set |
| `getForeignKeys(string $table): array` | Get foreign key constraints for a table |
| `getPrimaryKey(string $table): array` | Get primary key column names |
| `matchesStoredDefault(string $table, string $column, Expression $expression): bool` | Whether the column would store its current default if it were declared with `$expression`, checked on a temporary table that is rolled back. Throws `ExpressionDefaultProbeException` when PostgreSQL rejects the expression or the probe table |

### PgSqlIdentifier

`PgSqlIdentifier` is the driver's one identifier-quoting rule. `PgSqlConnection::quoteIdentifier()`, `PgSqlGenerator`, `PgSqlQueryBuilder` and `PgSqlIntrospector` all quote through it, so a name is quoted the same way in migrations, queries and repository SQL.

| Method | Description |
|---|---|
| `static quote(string $identifier): string` | Wrap a name in double quotes and double any double quote inside it. Each part of a `table.column` name is quoted separately: `"posts"."order"`. Quoted names keep their case |

### PgSqlGenerator

Implements `SqlGeneratorInterface`. Generates PostgreSQL DDL for schema migrations --- uses `SERIAL`/`BIGSERIAL` for auto-increment, identifiers quoted through `PgSqlIdentifier`, and PostgreSQL-specific types (JSONB, BYTEA, etc.).

| Method | Description |
|---|---|
| `generateUp(SchemaDiff $diff): array` | Generate forward-migration SQL statements |
| `generateDown(SchemaDiff $diff): array` | Generate rollback SQL statements |
| `generateCreateTable(Table $table): string` | Generate a CREATE TABLE statement |
| `generateDropTable(string $tableName): string` | Generate a DROP TABLE statement |
| `generateAddColumn(string $table, Column $column): string` | Generate an ALTER TABLE ADD COLUMN statement. A primary key column gets its `ADD PRIMARY KEY` in the same statement |
| `generateDropColumn(string $table, string $columnName): string` | Generate an ALTER TABLE DROP COLUMN statement |
| `generateModifyColumn(string $table, Column $column, Column $oldColumn): string` | Generate one ALTER TABLE with the type (cast with `USING`), nullability and default changes from `$oldColumn` to `$column`. Throws `MigrationException` when none of those differ, or when the primary key or auto-increment changes |
| `generateAddIndex(string $table, Index $index): string` | Generate a CREATE INDEX statement, or `ADD CONSTRAINT ... UNIQUE` for an index that backs a unique constraint |
| `generateDropIndex(string $table, string $indexName): string` | Generate a DROP INDEX statement |
| `generateAddForeignKey(string $table, ForeignKey $foreignKey): string` | Generate an ADD CONSTRAINT FOREIGN KEY statement |
| `generateDropForeignKey(string $table, string $keyName): string` | Generate a DROP CONSTRAINT statement |

### InsertReturningException

Thrown by `PgSqlQueryBuilder::insert()` when the `RETURNING` clause does not return the expected primary key column. This happens when the `$primaryKey` argument does not match an actual column in the table, or when the column is absent from the result set. The exception message includes the table name and the column that was expected.

### ConnectionException

Thrown when a PostgreSQL connection fails. Includes the host, port, and database name in the message, with a suggestion to verify server status and credentials.
