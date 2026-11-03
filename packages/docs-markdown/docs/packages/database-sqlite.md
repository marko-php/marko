---
title: marko/database-sqlite
description: SQLite driver for the Marko framework database layer --- file or in-memory databases with WAL, table-rebuild migrations, and JSON1 queries.
---

SQLite driver for the Marko framework database layer. Implements `ConnectionInterface`, `QueryBuilderInterface`, `IntrospectorInterface`, and `SqlGeneratorInterface` from [`marko/database`](/docs/packages/database/) for a database that lives in one file (or in memory), with no server to run.

## Installation

```bash
composer require marko/database-sqlite
```

This automatically installs `marko/database` (the interface package) as a dependency. The driver needs `ext-pdo_sqlite` and **SQLite 3.39 or newer** (for `RIGHT JOIN` and the JSON operators it relies on). An older library fails on connect with a `ConnectionException` that names the version found and the version required.

## Configuration

Create a configuration file at `config/database.php`. Only `driver` and `database` are required; `host`, `port`, `username` and `password` are not used.

```php title="config/database.php"
<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    'driver' => 'sqlite',
    'database' => Env::string('DB_DATABASE', 'storage/database.sqlite'),
];
```

`database` is the path to the database file, or `:memory:`:

- A **relative path** resolves against the project root. Absolute paths and `file:` URIs are used as given.
- **`:memory:`** gives each connection its own private database that disappears when the connection closes. It suits tests and scratch work.
- A missing file is created on connect, together with its directory. Set `create_if_missing` to `false` to fail loudly instead.

### The `sqlite` Options

Driver-specific options live in a `sqlite` sub-array:

```php title="config/database.php"
return [
    'driver' => 'sqlite',
    'database' => 'storage/database.sqlite',
    'sqlite' => [
        'create_if_missing' => false,
        'pragmas' => [
            'busy_timeout' => 10000,
            'synchronous' => 'FULL',
        ],
    ],
];
```

| Key | Default | Meaning |
|-----|---------|---------|
| `sqlite.create_if_missing` | `true` | Create the database file and its directory when missing. When `false`, a missing file throws a `ConnectionException` |
| `sqlite.pragmas` | `[]` | Pragmas merged over the defaults below |

### Pragmas

These are applied on every connect, in this order:

| Pragma | Default | Why |
|--------|---------|-----|
| `journal_mode` | `WAL` | Readers keep reading while a writer writes. Skipped for `:memory:` |
| `busy_timeout` | `5000` | Milliseconds a contended connection waits for the write lock before failing |
| `synchronous` | `NORMAL` | The usual pairing with WAL: durable across application crashes |
| `foreign_keys` | `ON` | SQLite ignores foreign keys unless asked |
| `temp_store` | `MEMORY` | Temporary tables and indexes stay off disk |

`sqlite.pragmas` overrides these and may add `cache_size`, `wal_autocheckpoint`, `mmap_size`, `locking_mode`, `auto_vacuum`, `journal_size_limit`, `recursive_triggers` and `secure_delete`. Booleans map to `1` and `0`. Any other pragma name, and any value that is not an integer or a bare word, throws a `ConnectionException` on connect: pragma values are interpolated into SQL, so the driver accepts nothing it cannot check.

## Choosing SQLite

SQLite fits when one machine runs the application:

- Small and mid-sized sites, internal tools and admin back offices
- Local development and tests without a database server
- Single-node queue, session and notification storage
- Embedded or desktop-style deployments

Choose [`marko/database-pgsql`](/docs/packages/database-pgsql/) or [`marko/database-mysql`](/docs/packages/database-mysql/) when several application servers must share one database, when write concurrency is high, or when you need read replicas ([`marko/database-readwrite`](/docs/packages/database-readwrite/) does not support SQLite).

## Concurrency and Locking

SQLite allows **one writer at a time** for the whole database. With WAL, readers are never blocked by that writer.

Every outermost transaction starts with `BEGIN IMMEDIATE`, so it takes the write lock up front instead of failing halfway through when it tries to upgrade. Nested transactions use savepoints.

**Row locks compile to nothing.** `lockForUpdate()`, `sharedLock()`, `skipLocked()` and `noWait()` are accepted and validated as on the other drivers, but add no SQL: the transaction already holds the only write lock, so the guarantee they promise still holds.

**Contended writers fail loudly.** A connection that cannot get the write lock within `busy_timeout` throws `LockTimeoutException`. It does not skip rows, as `skipLocked()` does on PostgreSQL and MySQL. When `marko/queue-database` is on SQLite, a worker that loses the race for a job logs the `LockTimeoutException` and carries on with its next poll, so a contended claim is a logged, retried pop rather than a crash. Raise `busy_timeout` if your writes are long.

## Column Types

Tables are created without `STRICT`, with the Marko type declared as the column type (`VARCHAR(255)`, `DATETIME`, `DECIMAL(10,2)`, `BLOB`, ...). SQLite stores values by *type affinity*, not by declared type, and the driver reads the declared type back during introspection so schema diffs settle with no spurious change.

- **`VARCHAR(n)` length is not enforced.** SQLite stores longer strings in full. Validate length in the application (see [`marko/validation`](/docs/packages/validation/)).
- **`decimal` is stored with REAL semantics.** `DECIMAL(p,s)` has NUMERIC affinity, so values are stored as REAL or INTEGER with float precision. Do not rely on exact decimal arithmetic in SQL; keep money in integer minor units.
- **Auto-increment keys** are `INTEGER PRIMARY KEY AUTOINCREMENT`, the form SQLite requires. A `bigint` or `smallint` key keeps its Marko type in a comment inside the column definition, which the introspector reads back.
- **Unique columns** are created as named unique indexes, the same as the other drivers.
- Composite primary keys are emitted as a table-level `PRIMARY KEY (...)`.

### Dates and Time Zones

SQLite has no session time zone. Marko writes datetimes in the configured `database.timezone`. SQLite's own `CURRENT_TIMESTAMP`, `CURRENT_DATE` and `CURRENT_TIME` defaults are always **UTC**, so a column using one is only correct when `database.timezone` is UTC. If it is not, the generator throws a `MigrationException` instead of creating a column whose default disagrees with the rest of your data. `NOW()`, `LOCALTIMESTAMP`, `CURRENT_TIMESTAMP(n)` and `gen_random_uuid()` defaults are not translated and throw too.

## Migrations and Table Rebuilds

`ALTER TABLE` in SQLite is limited. The driver uses native `ADD COLUMN`, `DROP COLUMN`, `CREATE INDEX` and `DROP INDEX` where they work, and **rebuilds the table** otherwise: it creates a new table with the target definition, copies the rows across, drops the old table, renames the new one and recreates its indexes. A rebuild happens for:

- Column modifications (type, nullability, default)
- Adding or dropping foreign keys
- Adding a primary key column, a `NOT NULL` column without a constant default, a column with an expression default, or a column with a `REFERENCES` clause
- Dropping a primary key column, or a column that an index, foreign key or partial-index predicate uses

Rebuilds are direction-aware: the `down` of a native column drop is an add, which may itself need a rebuild.

A rebuild ends with a **foreign key guard**: the migration fails, and the whole transaction rolls back, if the rebuilt table leaves any foreign key violation in the database. `PRAGMA foreign_key_check` is the check, run through a temporary guard table so it fails the migration loudly.

Rebuilds need the table's current definition, which `marko/database` supplies to the generator as `TableDiff::$currentTable`. Plain, unique and partial indexes are supported. A fulltext index throws a `MigrationException`, because SQLite has no fulltext index type.

## Query Builder

The query builder supports the same surface as the other drivers: `INSERT ... ON CONFLICT` upserts, `RETURNING`, right joins, unions, subqueries, aggregates and pagination. SQLite needs `LIMIT` before `OFFSET`, so an offset-only query is compiled with `LIMIT -1`.

### JSON

JSON queries use the JSON1 functions built into SQLite 3.39+:

```php
$users = $connection->query(
    'SELECT * FROM users WHERE "settings"->>? = ?',
    ['theme', 'dark'],
);
```

Through the builder, `whereJson*` path, contains and exists conditions compile to `->`, `->>`, `json_type()` and `json_each()`.

`whereJsonContains` accepts scalar and list needles. An **object needle or a nested-array needle throws** a `Marko\Database\Sqlite\Exceptions\UnsupportedFeatureException`, because SQLite has no containment operator for them and an approximation would return wrong rows silently.

### Text Comparison

`LIKE` and `caseInsensitive()` (which uses `LOWER()`) fold **ASCII letters only**: `élan` does not match `ÉLAN`. A `unique` constraint is **case-sensitive** by default (binary collation), like PostgreSQL.

### Inserts That Return Values

`RETURNING` needs SQLite 3.35+, which the 3.39 floor satisfies, so generated primary keys read back on single and batch inserts. SQLite documents the order of `RETURNING` rows as unspecified; the batch insert path relies on the order SQLite produces today, which is insertion order, and a test guards it.

## Other Packages on SQLite

`marko/queue-database`, `marko/session-database`, `marko/notification-database`, `marko/authentication-database` and `marko/validation-database` run on SQLite unchanged and are tested against it. `marko/testing`'s `TruncateDatabase` empties tables with `DELETE` and resets the autoincrement counters. The MCP read-only query tool relies on `PRAGMA query_only` on SQLite: a write fails with a `QueryException`, and the pragma is switched off again afterwards.

## Limits

- One writer at a time; there is no row-level locking or `SKIP LOCKED`.
- No read/write splitting (`marko/database-readwrite`).
- Column length and exact decimal precision are not enforced (see [Column Types](#column-types)).
- Only SQLite 3.39 and newer.

## Long-Running Workers

`SqliteConnection` implements `ResettableInterface`. Between requests, `reset()` rolls back any transaction a failed request left open and drops its pending after-commit callbacks.

## API Reference

The driver registers implementations for the `marko/database` interfaces:

| Interface | Implementation |
|-----------|----------------|
| `Marko\Database\Connection\ConnectionInterface` | `Marko\Database\Sqlite\Connection\SqliteConnection` |
| `Marko\Database\Connection\ConnectionFactoryInterface` | `Marko\Database\Sqlite\Connection\SqliteConnectionFactory` |
| `Marko\Database\Query\QueryBuilderInterface` | `Marko\Database\Sqlite\Query\SqliteQueryBuilder` |
| `Marko\Database\Query\QueryBuilderFactoryInterface` | `Marko\Database\Sqlite\Query\SqliteQueryBuilderFactory` |
| `Marko\Database\Introspection\IntrospectorInterface` | `Marko\Database\Sqlite\Introspection\SqliteIntrospector` |
| `Marko\Database\Diff\SqlGeneratorInterface` | `Marko\Database\Sqlite\Sql\SqliteGenerator` |

See [marko/database](/docs/packages/database/) for the full interface reference.
