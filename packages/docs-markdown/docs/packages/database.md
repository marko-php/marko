---
title: marko/database
description: Entity-driven schema definition with the Data Mapper pattern.
---

Database abstraction with entity-driven schema, type inference, migrations, and seeders.

**This package has no implementation.** Install `marko/database-mysql`, `marko/database-pgsql` or `marko/database-sqlite` for actual database connectivity.

## Installation

```bash
composer require marko/database
```

You typically install a driver package (like `marko/database-pgsql`) which requires this automatically.

## Entity-Driven Schema

Your entity class is the single source of truth for both your PHP code and database structure. No separate migration files to write by hand, no XML mappings, no YAML configuration. Define your entities with attributes, and Marko generates the SQL to make your database match.

### Complete Example

```php title="app/blog/Entity/Post.php"
<?php

declare(strict_types=1);

namespace App\Blog\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Table;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Entity\Entity;

#[Table('blog_posts')]
#[Index('idx_status_created', ['status', 'created_at'])]
class Post extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 255)]
    public string $title;

    #[Column(length: 255, unique: true)]
    public string $slug;

    #[Column(type: 'text')]
    public ?string $content = null;

    #[Column(default: 'draft')]
    public PostStatus $status = PostStatus::Draft;

    #[Column(references: 'users.id', onDelete: 'cascade')]
    public int $authorId;

    #[Column(type: 'timestamp', default: 'CURRENT_TIMESTAMP')]
    public DateTimeImmutable $createdAt;

    #[Column(type: 'timestamp')]
    public ?DateTimeImmutable $updatedAt = null;
}
```

### Attributes Overview

| Attribute | Purpose |
|-----------|---------|
| `#[Table]` | Defines table name (`name:`) or marks an extender (`extends:`); `unmanagedIndexes:` lists hand-made indexes the diff never drops (see [Hand-Made Indexes](#hand-made-indexes)) |
| `#[Column]` | Column configuration (name, primaryKey, autoIncrement, length, type, unique, default, references, onDelete, onUpdate, nullable, generated) |
| `#[Index]` | Composite and unique indexes; `where:` makes a partial index (see [Partial Indexes](#partial-indexes)) |
| `#[Cast]` | Converts a property with a custom cast class (see [Casts](#casts)) |
| `#[Encrypted]` | Stores a property encrypted (see [Encrypted Columns](#encrypted-columns)) |
| `#[Timestamps]` | Fills `createdAt`/`updatedAt` automatically (see [Automatic Timestamps](#automatic-timestamps)) |
| `#[HasOne]` | Declares a has-one relationship to another entity |
| `#[HasMany]` | Declares a has-many relationship to another entity |
| `#[BelongsTo]` | Declares a belongs-to relationship to another entity |
| `#[BelongsToMany]` | Declares a many-to-many relationship through a pivot entity |

Property names are automatically converted from camelCase to snake_case for column names. For example, `$createdAt` maps to the `created_at` column. Use the `name` parameter to override this: `#[Column(name: 'custom_column')]`.

### Reserved Words and Mixed Case

Table and column names can be SQL reserved words (`key`, `group`, `order`, `user`, `rank`) or mixed case (`#[Column(name: 'displayName')]`). Every name Marko writes into SQL is quoted with the driver's delimiter: the migration DDL, the query builder, the repository (`find()`, `findBy()`, `save()`, `insertBatch()`, `delete()`, `exists()` and the rest), `DataMigration`'s `insert()`/`update()`/`delete()` helpers, `DatabaseTestHelper`, the migrations table, and the hand-written SQL in other packages (the search driver, the database queue, sessions, notifications, `TruncateDatabase` and the admin-auth pivots). MySQL and MariaDB use backticks, PostgreSQL double quotes, and a delimiter inside a name is doubled. The quoting comes from `ConnectionInterface::quoteIdentifier()`, so use it too when you write your own SQL:

```php
$sql = sprintf(
    'SELECT * FROM %s WHERE %s = ?',
    $this->connection->quoteIdentifier('permissions'),
    $this->connection->quoteIdentifier('group'),
);
```

Never write the delimiter yourself (`` `jobs` `` or `"jobs"`): that is a second copy of one driver's rule, which breaks on the other driver and on a decorator or third-party driver. In the framework's own packages a test (`tests/SqlIdentifierQuotingTest.php`) fails on any SQL string in `packages/*/src` that quotes a name by hand; only the `marko/database-mysql` and `marko/database-pgsql` drivers own a delimiter.

On PostgreSQL a quoted name is case-sensitive. A mixed-case `#[Table]` or `#[Column(name: ...)]` name therefore has to match the table exactly as it was created. Tables created by `db:migrate` always match. A table created by a hand-written migration with unquoted mixed-case names was folded to lower case by PostgreSQL, so declare the lower-case name on the entity.

### String Comparison and Collation

`#[Column]` has no collation option, so `db:migrate` creates string columns with the server's default collation, and string equality (`findBy()`, `WHERE`, `isColumnUnique()`) and unique indexes follow it. The drivers disagree:

| Driver | Default collation | `'Posts.Edit' = 'posts.edit'` |
|--------|-------------------|-------------------------------|
| MySQL 8 | `utf8mb4_0900_ai_ci`: case- and accent-insensitive | true |
| MariaDB | `utf8mb4_general_ci` or `utf8mb4_uca1400_ai_ci`: case-insensitive (the `ai` ones accent-insensitive too); PAD SPACE collations also ignore trailing spaces | true |
| PostgreSQL | Deterministic: exact, byte for byte | false |

PHP compares strings exactly too (`===`, array keys). So on MySQL/MariaDB a unique column rejects `Mark@example.com` next to `mark@example.com` and `findBy(['email' => 'Mark@example.com'])` finds the lower-case row, while PostgreSQL stores both and finds only the exact match. When a value's meaning must not depend on the driver, give it one canonical form in PHP before it reaches SQL: lowercase case-insensitive values such as emails on save and lookup, and validate identifiers against a lowercase pattern. [marko/admin-auth](/docs/packages/admin-auth/#keys-slugs-and-emails) does both for its permission keys, role slugs and admin emails.

### Type Inference Rules

Marko infers database types from PHP types:

| PHP Type | Database Type |
|----------|---------------|
| `int` | INT (or SERIAL/BIGSERIAL if autoIncrement) |
| `string` | VARCHAR(255) by default, TEXT if type='text' |
| `bool` | BOOLEAN |
| `float` | DECIMAL or FLOAT |
| `?type` | Column is NULLABLE |
| `DateTimeImmutable` | VARCHAR unless declared --- use `type: 'timestamp'` or `type: 'datetime'` |
| `BackedEnum` | ENUM with cases as values |
| `array` or `?array` with `type: 'json'` | JSON (MySQL) / JSONB (PostgreSQL) |
| Union type (e.g. `int\|string`) | No inference — requires an explicit `type:` |
| Default values | From property initializers |

### Column Defaults

`#[Column(default: ...)]` sets the column's `DEFAULT`. A string is stored as a quoted string literal, except for two shortcuts that are SQL expressions:

- the timestamp keywords `CURRENT_TIMESTAMP`, `CURRENT_DATE`, `CURRENT_TIME`, `LOCALTIMESTAMP` and `LOCALTIME`, with or without a precision (`CURRENT_TIMESTAMP(6)`)
- a call to a function with no arguments, such as `NOW()`, `gen_random_uuid()` or `UUID()`

For any other expression, pass an `Expression`. To store a string that matches a shortcut as text, pass a `Literal`:

```php
use Marko\Database\Attributes\Column;
use Marko\Database\Schema\Expression;
use Marko\Database\Schema\Literal;

#[Column(type: 'timestamp', default: 'CURRENT_TIMESTAMP')]
public DateTimeImmutable $createdAt;

#[Column(type: 'timestamp', default: new Expression("now() + interval '1 day'"))]
public DateTimeImmutable $expiresAt;

#[Column(default: new Literal('todo()'))]
public string $placeholder;
```

| Default | PostgreSQL | MySQL |
|---------|------------|-------|
| `'draft'` | `DEFAULT 'draft'` | `DEFAULT 'draft'` |
| `'CURRENT_TIMESTAMP(6)'` | `DEFAULT CURRENT_TIMESTAMP(6)` | `DEFAULT CURRENT_TIMESTAMP(6)` |
| `'NOW()'` | `DEFAULT NOW()` | `DEFAULT NOW()` |
| `'gen_random_uuid()'` | `DEFAULT gen_random_uuid()` | --- |
| `'UUID()'` | --- | `DEFAULT (UUID())` |
| `new Expression("lower('ABC')")` | `DEFAULT lower('ABC')` | `DEFAULT (lower('ABC'))` |
| `new Literal('todo()')` | `DEFAULT 'todo()'` | `DEFAULT 'todo()'` |

The SQL is not checked against the database: a function the database doesn't have (`UUID()` on PostgreSQL, `gen_random_uuid()` on MySQL) fails when the migration runs. MySQL needs 8.0.13 or later for any expression default except the `CURRENT_TIMESTAMP` family; the generator adds the parentheses MySQL requires.

The introspectors read an expression default back as an `Expression`, so the diff compares it with the entity's. The comparison ignores case, whitespace and parentheses around the whole expression, because databases report `NOW()` as `now()` and MySQL reports `(UUID())` as `uuid()`.

A database stores its own rewritten form of a more complex expression, not the text you wrote. PostgreSQL reports `now() + interval '1 day'` as `(now() + '1 day'::interval)`, and MySQL reports `CONCAT('a', 'b')` as `concat(_utf8mb4'a',_utf8mb4'b')`. Write the expression the way you would in SQL. When an `Expression` default still differs from the database's after that comparison, `db:diff` and `db:migrate` ask the database how it would store your expression. They declare it on a temporary column of the same type, read back what the database stored, and compare that with the column's current default. If the two match, the column is unchanged. If not, the diff modifies the column and the migration sets your expression as written. The temporary table is dropped (MySQL) or rolled back (PostgreSQL), so the real schema never changes. Columns whose defaults already compare equal, and columns the diff modifies for another reason, are never probed.

If the probe fails, `db:diff` fails with an `ExpressionDefaultProbeException` (a `MigrationException`) naming the table, column, expression and database error. That happens when the database rejects the expression (`now() + 'tomorrow'` on a timestamp column), which you now see at diff time instead of when the migration runs, or when the connection's user may not create a temporary table. `db:migrate` in development fails the same way. The drift check that `db:migrate` runs outside development, after the pending migrations are applied, only warns on STDERR and exits with the migrations' own status, so a deploy that migrated successfully isn't reported as failed. See [Environment Behaviour](#environment-behaviour). A third-party driver whose introspector doesn't implement `ExpressionDefaultMatcherInterface` keeps the plain text comparison.

### Union-Typed Columns

A union type has no single PHP type to infer a column type from, so it must declare one explicitly. This is how polymorphic foreign keys are modeled — an attachment can point at entities whose primary keys are `int` or `string`:

```php
#[Column(type: 'varchar', length: 255)]
public int|string $attachableId = 0;
```

Without an explicit `type:`, metadata parsing throws `EntityException` — Marko will not guess which side of the union wins.

> A varchar-backed union always hydrates to a PHP `string`, even when the value was written as an `int`. Compare loosely or cast explicitly rather than strict-comparing against an integer primary key.

### String and UUID Primary Keys

Primary keys are not limited to integers. Any property marked `#[Column(primaryKey: true)]` serves as the primary key. `find()` and `findOrFail()` accept `int|string`.

> **Every entity must declare exactly one `#[Column(primaryKey: true)]` property.** Marko validates this at metadata-parse time and throws `MissingPrimaryKeyException` if none is found. There is no silent `id` fallback.

UUID primary keys work on both drivers. A `uuid` column is `UUID` on PostgreSQL and `CHAR(36)` on MySQL. The key comes from one of two places: you set it in PHP, or the database generates it through an [expression default](#column-defaults).

#### Database-generated keys

Add `generated: true` to let the database generate the key. `save()` and `insertBatch()` leave an unset (or `null`) key out of the `INSERT`, so the column default fills it, then read the value back with `INSERT ... RETURNING` and set it on the entity:

```php title="app/blog/Entity/Article.php"
<?php

declare(strict_types=1);

namespace App\Blog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('articles')]
class Article extends Entity
{
    #[Column(primaryKey: true, type: 'uuid', default: 'gen_random_uuid()', generated: true)]
    public string $id;

    #[Column(length: 255)]
    public string $title;
}
```

```php
$article = new Article();
$article->title = 'Hello';
$articleRepository->save($article);

$article->id; // '3f0c2a9e-...', generated by PostgreSQL
$articleRepository->find($article->id); // the saved row
```

The returned value goes through the property's cast, so a `string` key stays a string. `insertBatch()` gives each entity its own key; see [Bulk Insert](#bulk-insert) for how they are matched. A key you set yourself is inserted as is.

Reading the key back needs `INSERT ... RETURNING`, which the connection reports through `ConnectionInterface::supportsReturning()`. PostgreSQL and MariaDB 10.5+ support it, so generated keys read back on both (`marko/database-mysql` checks which server it is connected to, see [MySQL vs MariaDB](/docs/packages/database-mysql/#mysql-vs-mariadb)). MySQL doesn't, so saving an entity with an unset generated key on MySQL throws a `RepositoryException` that names the entity and tells you to set the key in PHP.

`generated: true` is checked when the entity's metadata is parsed: it is only allowed on the primary key, not together with `autoIncrement`, and only with a `default` (otherwise nothing generates the value). Each mistake throws an `EntityException`.

`upsert()` also leaves an unset generated key to the database, on every driver, but never reads keys back.

#### Keys set in PHP

Without `generated: true`, the key must be set before saving. A database default on the column still fills the key of rows inserted without one, such as rows from raw SQL or another application. On MySQL, set the key in PHP even when the column has a `UUID()` default:

```php
use Ramsey\Uuid\Uuid;

$article = new Article();
$article->id = Uuid::uuid4()->toString();
$article->title = 'Hello';
$articleRepository->save($article);
```

Saving or batch-inserting an entity whose key is unset or `null`, when the key is neither `autoIncrement` nor `generated`, throws a `RepositoryException` saying to set the key or mark the column `generated: true`. The row never reaches the database as a `NULL` key.

### JSON Columns

Store structured data directly in a column using `#[Column(type: 'json')]`. The property type must be `array` or `?array`. MySQL uses the native `JSON` type; PostgreSQL uses `JSONB`.

```php title="app/blog/Entity/Post.php"
<?php

declare(strict_types=1);

namespace App\Blog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('posts')]
class Post extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 255)]
    public string $title;

    #[Column(type: 'json')]
    public array $metadata = [];

    #[Column(type: 'json')]
    public ?array $settings = null;
}
```

JSON columns serialize on write and deserialize on read automatically using `JSON_THROW_ON_ERROR`. The value must be an array or null --- top-level JSON scalars are out of scope.

JSON is the pragmatic alternative to EAV tables and to running a separate document store. For structured-but-variable attributes (e.g., product options, user preferences, webhook payloads), a JSON column keeps everything in one place without the overhead of a second data layer.

### JSON query operators

Query inside JSON columns using arrow-path syntax in `where()` and `select()`, or the dedicated JSON methods:

```php
// Arrow path in where() — driver translates to JSON_EXTRACT (MySQL) or -> / ->> (PostgreSQL)
$this->query()->where('data->user->name', '=', 'Alice')->getEntities();

// Select a nested value
$this->query()->select('id', 'data->>name as display_name')->get();

// whereJsonContains — value is present in a JSON array
$this->query()->whereJsonContains('tags', 'php')->getEntities();

// whereJsonExists / whereJsonMissing — check for key presence
$this->query()->whereJsonExists('settings->notifications')->getEntities();
$this->query()->whereJsonMissing('profile->avatar')->getEntities();
```

**Path syntax:**
- `data->user->name` --- extract a nested value (returns JSON on MySQL, typed value on PostgreSQL)
- `data->>name` --- extract as text (unquoted string)

**JSON indexing** is done via raw DDL in your migration or schema setup --- the query builder does not generate index DDL for you:

```sql
-- PostgreSQL: GIN index for containment queries
CREATE INDEX idx_posts_metadata ON posts USING gin(metadata jsonb_path_ops);

-- MySQL: generated column + B-tree index
ALTER TABLE posts
    ADD COLUMN metadata_status VARCHAR(50)
        GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.status'))) STORED,
    ADD INDEX idx_posts_metadata_status (metadata_status);
```

## Value Conversion

Every value moving between an entity property and a column goes through one pipeline in `EntityHydrator`: `toPhpValue()` on read, `toDatabaseValue()` on write. Inserts, batch inserts, updates and `findBy()`/`findOneBy()`/`existsBy()` criteria all use it, so an insert and an update of the same value always bind the same database value.

Each property is converted by a cast, chosen in this order:

| Property | Cast |
|----------|------|
| `#[Cast(SomeCast::class)]` | `SomeCast` |
| `array` (or `type: 'json'`) | `JsonCast` --- JSON encode/decode |
| `BackedEnum` | `EnumCast` --- stored as the backing value |
| `DateTimeImmutable` | `DateTimeCast` --- see [Datetimes and Timezones](#datetimes-and-timezones) |
| `int`, `float`, `bool`, `string` | `ScalarCast` --- coerced to the declared type on read |

`NULL` never reaches a cast: SQL `NULL` hydrates to PHP `null`, and `null` is written as `NULL`.

### Datetimes and Timezones

`DateTimeImmutable` values are converted to the database timezone before they are formatted, and read back in that timezone. The stored instant never depends on the PHP default timezone or on the timezone of the object you assigned:

```php
$appointment->startsAt = new DateTimeImmutable('2026-07-04 09:30:00', new DateTimeZone('America/New_York'));
$appointmentRepository->save($appointment); // stored as '2026-07-04 13:30:00'

$loaded = $appointmentRepository->find($appointment->id);
$loaded->startsAt->getTimestamp() === $appointment->startsAt->getTimestamp(); // true
$loaded->startsAt->getTimezone()->getName(); // 'UTC'
```

The database timezone defaults to UTC. Set the optional `timezone` key to change it:

```php title="config/database.php"
return [
    // ...driver, host, port, database, username, password
    'timezone' => 'UTC',
];
```

An invalid identifier fails loudly (a `ConfigurationException` naming the value) when the database config is loaded or the first time a datetime is converted.

> **Behaviour change:** before casts were introduced, datetimes were formatted in whatever timezone the object carried and read back in the PHP default timezone, so a value saved from a non-UTC object came back as a different instant. Rows written that way by a non-UTC application hold local wall-clock times; convert them to UTC (or set `timezone` to the zone they were written in) when upgrading.

The same rule covers the tables Marko packages write with hand-built SQL or string properties instead of `DateTimeCast`. Each one converts the injected clock's time to `database.timezone` before it formats it:

| Table | Columns | Written by |
|---|---|---|
| `jobs` | `created_at`, `available_at`, `reserved_at` (and the pop, reclaim and `size()` cutoffs) | `DatabaseQueue` ([`marko/queue-database`](/docs/packages/queue-database/#time-and-testing)) |
| `failed_jobs` | `failed_at` (also read back in this zone) | `DatabaseFailedJobRepository` ([`marko/queue-database`](/docs/packages/queue-database/)) |
| `personal_access_tokens` | `expires_at` (also read back in this zone by `TokenGuard`), `created_at` | `TokenManager` ([`marko/authentication-token`](/docs/packages/authentication-token/)) |
| `notifications` | `created_at`, `read_at` | `DatabaseChannel` ([`marko/notification`](/docs/packages/notification/)), `DatabaseNotificationRepository` ([`marko/notification-database`](/docs/packages/notification-database/)) |
| `webhook_attempts` | `attempted_at` | `WebhookDeliveryService` ([`marko/webhook`](/docs/packages/webhook/)) |

Code of your own that stores times as strings can use the same conversion. Inject `DatabaseTimezoneConfig` and call `format()` to write and `parse()` to read:

```php
use Marko\Database\Config\DatabaseTimezoneConfig;

public function __construct(
    private ClockInterface $clock,
    private DatabaseTimezoneConfig $databaseTimezoneConfig,
) {}

// '2026-07-04 13:30:00' for 09:30 in New York, with the default UTC zone
$stored = $this->databaseTimezoneConfig->format($this->clock->now());

// a DateTimeImmutable in the database timezone, the same instant that was stored
$instant = $this->databaseTimezoneConfig->parse($stored);
```

In tests, `DatabaseTimezoneConfig::fromName('UTC')` builds one without reading `config/database.php`.

> **Behaviour change:** the tables above used to be written in the clock's timezone (PHP's default timezone for `SystemClock`). An app whose PHP default timezone isn't its database timezone has existing rows holding local wall-clock times. See [upgrading the queue tables](/docs/packages/queue-database/#upgrading-rows-written-in-the-old-timezone) for the drain-or-convert steps; the same conversion SQL applies to the other tables.

Datetimes are stored to the second (`Y-m-d H:i:s`). Declare the column type explicitly --- `#[Column(type: 'timestamp')]` or `#[Column(type: 'datetime')]` --- because a `DateTimeImmutable` property does not infer one.

#### The database session time zone

The MySQL/MariaDB and PostgreSQL drivers pin the database session to `database.timezone` every time they connect, including after a reconnect. Times the database produces itself then agree with the times Marko writes:

- `DEFAULT CURRENT_TIMESTAMP`, `NOW()` and `CURRENT_TIMESTAMP` in raw SQL give the current time in `database.timezone`, not in the server's zone.
- MySQL `TIMESTAMP` columns convert from and to the session zone, so with the default UTC a value is stored as written, even when it falls in a daylight-saving gap of the server's zone.
- Every process (web, CLI workers, replicas through [`marko/database-readwrite`](/docs/packages/database-readwrite/)) uses the same zone, whatever each server's own zone is.

| `database.timezone` | MySQL / MariaDB | PostgreSQL |
|---|---|---|
| `UTC` (default) | `SET time_zone = '+00:00'` | `SET TIME ZONE 'UTC'` |
| A region such as `America/New_York` | `SET time_zone = 'America/New_York'` | `SET TIME ZONE 'America/New_York'` |
| A fixed offset or abbreviation such as `+05:30` or `CEST` | `SET time_zone = '+05:30'` | `SET TIME ZONE INTERVAL '+05:30' HOUR TO MINUTE` |

A region zone on MySQL or MariaDB needs the server's time zone tables. Without them the connection fails with a `ConnectionException` naming the zone; see [Session Time Zone](/docs/packages/database-mysql/#session-time-zone). UTC and fixed offsets need no tables.

> **Behaviour change:** connections used to run in the server's own zone. On a server that isn't on UTC (for example a MySQL server whose `time_zone` is `SYSTEM` on a machine set to New York):
> - MySQL `TIMESTAMP` values Marko wrote were converted from the server's zone on write, so they now read back shifted by its offset.
> - Rows filled by `DEFAULT CURRENT_TIMESTAMP` or `NOW()` (on MySQL `DATETIME` and PostgreSQL `TIMESTAMP` columns) hold the server's local time. Marko reads them in `database.timezone`.
>
> See [Upgrading from a non-UTC server](#upgrading-from-a-non-utc-server) below. A server already running on UTC (the default in most managed databases and Docker images) needs no change.

#### Upgrading from a non-UTC server

First find the zone the server ran in (`SELECT @@GLOBAL.time_zone, @@system_time_zone` on MySQL, `SHOW TimeZone` on PostgreSQL). Stop the writers, then convert the affected columns. The examples assume a server in `America/New_York` and the default UTC `database.timezone`; use your own zones.

**MySQL `TIMESTAMP` columns Marko wrote** (the `jobs` and `failed_jobs` tables of [`marko/queue-database`](/docs/packages/queue-database/), and entity columns declared `type: 'timestamp'`). The server read each UTC string Marko sent as New York time, so the stored instant is off by the offset. Run in a UTC session (any connection Marko opens, or `SET time_zone = '+00:00'` first):

```sql title="MySQL / MariaDB"
SET time_zone = '+00:00';
UPDATE jobs SET
    available_at = CONVERT_TZ(available_at, '+00:00', 'America/New_York'),
    created_at = CONVERT_TZ(created_at, '+00:00', 'America/New_York'),
    reserved_at = CONVERT_TZ(reserved_at, '+00:00', 'America/New_York');
UPDATE failed_jobs SET failed_at = CONVERT_TZ(failed_at, '+00:00', 'America/New_York');
```

A `TIMESTAMP` the database filled itself (`DEFAULT CURRENT_TIMESTAMP` with no value from Marko) already holds the right instant and needs no change.

**`DATETIME` (MySQL) and `TIMESTAMP` (PostgreSQL) columns the database filled** with `DEFAULT CURRENT_TIMESTAMP` or `NOW()` hold New York wall-clock times:

```sql title="MySQL / MariaDB"
UPDATE comments SET created_at = CONVERT_TZ(created_at, 'America/New_York', '+00:00');
```

```sql title="PostgreSQL"
UPDATE comments SET created_at = created_at AT TIME ZONE 'America/New_York' AT TIME ZONE 'UTC';
```

Values Marko wrote to `DATETIME` or PostgreSQL `TIMESTAMP` columns (through `DateTimeCast` or `DatabaseTimezoneConfig`) are already in `database.timezone`; leave them alone. Named zones in `CONVERT_TZ()` need the time zone tables (it returns `NULL` without them); a fixed offset such as `'-05:00'` works without tables but ignores daylight saving time.

### Casts

A cast converts a value object to and from its column. Implement `CastInterface` and point `#[Cast]` at it:

```php title="app/billing/Cast/MoneyCast.php"
<?php

declare(strict_types=1);

namespace App\Billing\Cast;

use App\Billing\Money;
use Marko\Database\Entity\Cast\CastInterface;
use Marko\Database\Entity\PropertyMetadata;

class MoneyCast implements CastInterface
{
    public function toPhp(
        mixed $value,
        PropertyMetadata $meta,
    ): Money {
        return Money::fromCents((int) $value);
    }

    public function toDatabase(
        mixed $value,
        PropertyMetadata $meta,
    ): int {
        return $value->cents;
    }
}
```

```php title="app/billing/Entity/Invoice.php"
use App\Billing\Cast\MoneyCast;
use App\Billing\Money;
use Marko\Database\Attributes\Cast;
use Marko\Database\Attributes\Column;

#[Column(type: 'integer')]
#[Cast(MoneyCast::class)]
public Money $total;
```

- **Container-resolved.** Casts are built through the container, so they can take constructor dependencies and can be replaced with a Preference. The class must implement `CastInterface`, or metadata parsing throws `EntityException`.
- **Column type.** A cast property uses the `#[Column(type:)]` you declare, otherwise the type inferred from the PHP type (`varchar` for value objects). A cast may also back a `type: 'json'` column with a non-array property.
- **Dirty checking.** A cast property is dirty when its database representation changes, so assigning an equal value object --- or mutating a mutable one in place --- is handled correctly. To decide equality yourself, implement `EquatableCastInterface::equals(mixed $a, mixed $b, PropertyMetadata $meta): bool`.
- **Criteria.** `findBy(['total' => Money::fromCents(500)])` converts the value through the cast before binding it.

### Automatic Timestamps

`#[Timestamps]` on an entity fills its creation and update times:

```php title="app/blog/Entity/Comment.php"
use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Attributes\Timestamps;
use Marko\Database\Entity\Entity;

#[Table('comments')]
#[Timestamps]
class Comment extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(type: 'text')]
    public string $body;

    #[Column(type: 'timestamp')]
    public ?DateTimeImmutable $createdAt = null;

    #[Column(type: 'timestamp')]
    public ?DateTimeImmutable $updatedAt = null;
}
```

- **Insert** (`save()` on a new entity and `insertBatch()`) sets both properties. A value you set yourself is kept.
- **Update** sets `updatedAt` only when something else changed. Saving an unchanged entity writes nothing, and an `updatedAt` you changed yourself is kept.
- **Property names** default to `createdAt` and `updatedAt`. Rename them, or pass `null` to manage one yourself: `#[Timestamps(createdAt: 'publishedAt', updatedAt: null)]`. Each named property must be a `DateTimeImmutable` `#[Column]`, or metadata parsing throws `EntityException`. Passing `null` for both, or using `#[Timestamps]` on a `#[Table(extends:)]` extender, also throws.
- The time comes from the repository's injected `Psr\Clock\ClockInterface` ([`marko/clock`](/docs/packages/clock/)), converted to UTC. The container passes the bound clock; a repository you construct by hand without one uses `SystemClock`. For a one-off rule, override the protected `Repository::now()` instead.

To pin timestamps in a test, pass a [`FakeClock`](/docs/packages/testing/#fakeclock) as the repository's `clock` argument:

```php
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Testing\Fake\FakeClock;

it('stamps posts with the current time', function (): void {
    $clock = new FakeClock('2026-10-05 12:00:00 UTC');
    $metadataFactory = new EntityMetadataFactory();
    $posts = new PostRepository(
        $connection,
        $metadataFactory,
        new EntityHydrator($metadataFactory),
        clock: $clock,
    );

    $post = new Post();
    $posts->save($post);

    expect($post->createdAt)->toEqual($clock->now());
});
```

### Encrypted Columns

`#[Encrypted]` stores a property encrypted with [`marko/encryption`](/docs/packages/encryption/) and decrypts it on hydration:

```php
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Encrypted;

#[Column]
#[Encrypted]
public ?string $refreshToken = null;
```

`marko/database` does not require `marko/encryption`. Install a driver first:

```bash
composer require marko/encryption-openssl
```

- **Any PHP type.** The property is converted by its normal cast first (an `array` becomes JSON, an enum its backing value, a datetime its database-timezone string), then the string is encrypted. Reads decrypt first, then convert. `#[Encrypted]` cannot be combined with `#[Cast]` on the same property (`EntityException`).
- **Schema.** The column is always `text`, whatever the PHP type. Declaring any other `type:` throws `EntityException`.
- **Loud setup errors.** `#[Encrypted]` without `marko/encryption` installed, or without an `EncryptorInterface` binding, throws `EntityException` when the entity metadata is parsed --- not on the first save.
- **Not queryable.** Ciphertext changes on every write, so encrypted columns cannot be searched, indexed or unique. `findBy()`/`findOneBy()`/`existsBy()` on an encrypted property throws `EntityException`, as does marking an encrypted column as a primary key, `unique: true`, or including it in an `#[Index]` (thrown when the entity metadata is parsed).
- **Bound to its column.** Each value is encrypted with `table.column` as associated data, so ciphertext copied into another encrypted column or table fails to decrypt. Renaming the table or column changes the associated data, so existing values stop decrypting: decrypt them under the old name and re-encrypt them under the new one as part of the rename. Values written before this binding existed carry empty associated data and still decrypt while `encryption.aad_fallback` is `true`; re-save them, then turn the fallback off. See [Binding Ciphertext with Associated Data](/docs/packages/encryption/#binding-ciphertext-with-associated-data).
- **`NULL` is not encrypted.** A nullable encrypted column reveals whether a value is set.
- **Decryption failures** (wrong key, a row written before encryption was enabled) throw `EntityException` naming the entity, property and column.

## Table Extension

Any module can add columns to another module's entity table without touching the original entity class. Declare a plain `Entity` subclass with `#[Table(extends: ParentEntity::class)]` — the framework merges its columns/indexes/foreign-keys into the parent's table schema and hydrates the extender from the same row as a *companion* on the parent entity.

```php title="vendor/marko/auth/src/Entity/User.php"
#[Table(name: 'users')]
class User extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $email = '';
}
```

```php title="app/billing/Entity/UserBilling.php"
#[Table(extends: User::class)]
class UserBilling extends Entity
{
    #[Column]
    public ?string $stripeCustomerId = null;

    #[Column]
    public ?string $plan = null;
}
```

### Reading and writing companions

```php
// Attach a companion before saving
$user = new User();
$user->email = 'a@b.com';
$user->attachCompanion(new UserBilling(
    stripeCustomerId: 'cus_abc',
    plan: 'pro',
));
$userRepo->save($user); // single INSERT with parent + extender columns

// Read back — companions hydrate from the same SELECT
$loaded = $userRepo->find(1);
$billing = $loaded->companion(UserBilling::class); // typed via @template
echo $billing?->plan;

// Update — both parent and companion fields in a single UPDATE
$loaded->email = 'new@b.com';
$loaded->companion(UserBilling::class)->plan = 'enterprise';
$userRepo->save($loaded);
```

### Rules

- Specify exactly one of `name:` or `extends:` on `#[Table]`.
- An extender may not redeclare the parent's primary key, may not set `autoIncrement` on any column, and may not declare its own `name:`.
- The parent itself may not be an extender — chained extension is not supported in v1.
- Two extenders may not add the same column name or index name to a table. This fails loudly at registration with both class-strings in the error.
- Extenders have no primary key of their own and cannot have a standalone `Repository`. Use the parent's `Repository`.
- `insertBatch()` does not support entities with companions attached in v1.

### Schema merging

`SchemaRegistry::registerEntities()` is two-pass: it parses all entity classes, separates parents from extenders, then for each parent merges every linked extender's columns, indexes, and foreign keys into the parent's `Table` value object. `migrate:diff` sees the merged table — no extra code or configuration to make schema migrations aware of extender columns.

### Rolling-deploy safety

If an extender's columns are not yet present in the database (the deploy that adds the module has shipped but its migration hasn't run yet), hydration silently skips that extender. No exception, no companion attached. Once the migration runs, hydration begins populating the companion automatically.

### Entity discovery cache

To link extenders to their parents, the package's boot callback needs every `#[Table]` entity class. Without a cache it scans the `src/Entity` directories of `vendor/`, `modules/` and `app/` on every request. `marko/database` declares `EntityCacheContributor` as a [discovery cache contributor](/docs/packages/core/#adding-a-section-to-the-discovery-cache), so `marko discovery:cache` stores the entity list under the `entities` section and a production boot reads it instead of scanning. An entity added after the cache was compiled is picked up by the next `marko discovery:cache` --- see [Deploying to production](/docs/packages/core/#deploying-to-production).

## Data Mapper Pattern

Entities are plain PHP objects. They don't save themselves or know about the database. Repositories handle all persistence.

```php title="app/blog/Repository/PostRepository.php"
<?php

declare(strict_types=1);

namespace App\Blog\Repository;

use App\Blog\Entity\Post;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Repository\Repository;

class PostRepository extends Repository
{
    protected const ENTITY_CLASS = Post::class;

    public function findBySlug(string $slug): ?Post
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function findPublished(): EntityCollection
    {
        return $this->query()
            ->where('status', '=', 'published')
            ->orderBy('created_at', 'desc')
            ->getEntities();
    }
}
```

### Entity Not Found

`findOrFail()` throws `EntityNotFoundException` when no row matches. It extends `RepositoryException`, so existing `catch (RepositoryException $e)` blocks keep working, and it carries `$e->entityClass` and `$e->id` for logging.

It also implements `Marko\Core\Exceptions\HttpExceptionInterface`: if it escapes a controller, the routing pipeline renders a **`404`** with the body `{"message": "Not found."}`. The entity class and ID are never sent to the client. See [Errors and HTTP Exceptions](/docs/packages/routing/#errors-and-http-exceptions).

```php
#[Get('/posts/{id}')]
public function show(int $id): Response
{
    $post = $this->postRepository->findOrFail($id); // 404 if missing

    return Response::json(['title' => $post->title]);
}
```

### Why Data Mapper?

- **Testability**: Entities are plain objects, easy to construct in tests
- **Separation**: Business logic stays in entities, persistence in repositories
- **Flexibility**: Switch databases without changing entity code
- **Clarity**: No hidden magic, explicit saves via repository

### Custom Queries with `query()`

The base `Repository` provides three ways to query, each suited to a different use case:

| Method                              | When to use                                                                 |
|-------------------------------------|-----------------------------------------------------------------------------|
| `findBy(array $criteria)`           | Simple equality matches on columns                                          |
| `matching(QuerySpecification ...)`  | Reusable, composable query fragments shared across repositories             |
| `query()`                           | One-off custom queries --- joins, raw conditions, ordering, limits, offsets |

`query()` returns a `RepositoryQueryBuilder` pre-configured with the repository's table name. It implements the full `QueryBuilderInterface` and adds entity hydration.

```php
public function findPublished(int $limit = 10): EntityCollection
{
    return $this->query()
        ->where('status', '=', 'published')
        ->whereNotNull('published_at')
        ->orderBy('published_at', 'desc')
        ->limit($limit)
        ->getEntities();
}
```

#### Returning entities vs arrays

| Method            | Returns                                 |
|-------------------|-----------------------------------------|
| `getEntities()`   | `EntityCollection<TEntity>` --- hydrated, supports eager loading |
| `firstEntity()`   | `?TEntity` --- hydrated, or `null` if no match |
| `get()`           | `array<array<string, mixed>>` --- raw rows |
| `first()`         | `?array<string, mixed>` --- raw row, or `null` |
| `count()`         | `int`                                   |

Use `getEntities()` / `firstEntity()` for typed domain objects. Drop to `get()` / `first()` only for reports or aggregates where building entities adds no value.

#### Available filters

`where`, `whereIn`, `whereNotIn`, `whereNull`, `whereNotNull`, `whereColumn`, `whereExists`, `whereNotExists`, `orWhere`, `whereRaw`, `join`, `leftJoin`, `rightJoin`, `orderBy`, `orderByRaw`, `limit`, `offset`, `select`, `selectRaw`. On a repository query, `whereHas` and `whereDoesntHave` filter on relationships (see [Filtering on relationships](#filtering-on-relationships)). All return `static` for chaining. The escape hatch is `raw(string $sql, array $bindings = [])` for queries the builder can't express.

`whereNotIn()` with an empty list matches every row. `whereIn()` with an empty list matches none.

#### Column comparisons, aliases and subqueries

`whereColumn()` compares two columns. Both sides are validated identifiers, never bindings, and the operator must be `=`, `!=`, `<>`, `<`, `>`, `<=` or `>=`:

```php
$rows = $this->query()->whereColumn('updated_at', '>', 'created_at')->get();
```

`table()` takes an optional alias. Qualify columns with the alias (`alias.column`) wherever a column is accepted. The alias applies to SELECT queries, aggregates, `update()` and `delete()`. On MySQL, `delete()` with an alias needs MySQL 8.0.16 or later.

`whereExists()` and `whereNotExists()` take a subquery, either a builder or a callable. The callable receives a fresh builder on the same connection and configures it in place; its return value is ignored. Alias the subquery's table when it reads the same table as the outer query:

```php
use Marko\Database\Query\QueryBuilderInterface;

// Users with at least one published post
$rows = $this->query()
    ->whereExists(fn (QueryBuilderInterface $posts) => $posts
        ->table('posts', 'p')
        ->whereColumn('p.user_id', '=', 'users.id')
        ->where('p.status', '=', 'published'))
    ->get();
```

Where conditions compile in a fixed order, not in call order: `where`/`orWhere`, `whereIn`/`whereNotIn`, `whereNull`, `whereNotNull`, `whereColumn`, the JSON wheres, then `whereExists`/`whereNotExists`, and `whereRaw` last. Bindings follow the same order, so a subquery's bindings come after every other where binding and before the `whereRaw` bindings.

#### Raw expressions

Use `selectRaw` and `whereRaw` when the structured builder methods cannot express the SQL you need. Both accept a raw expression string and an optional array of positional `?` bindings. A denylist rejects expressions containing `;`, `--`, `/*`, `*/`, or backticks, plus `#` on MySQL and MariaDB, where it starts a comment. PostgreSQL allows `#` because `#`, `#>` and `#>>` are operators there.

The denylist is a tripwire, not a sanitizer. It cannot see quotes, so `whereRaw("name = '$name'")` is still injectable. Always pass values as `?` bindings, in raw fragments and in `raw()`, `query()` and `execute()` alike:

```php
// Injectable: the value becomes part of the SQL
$rows = $this->query()->whereRaw("name = '$name'")->get();

// Safe: the value travels separately as a binding
$rows = $this->query()->whereRaw('LOWER(name) = LOWER(?)', [$name])->get();
```

```php
// Compute a derived column inline
$rows = $this->query()
    ->select('id', 'title')
    ->selectRaw('COALESCE(published_at, created_at) AS display_date')
    ->get();

// Filter on an expression that where() cannot express
$rows = $this->query()
    ->whereRaw('COALESCE(price, base_price) > ?', [100])
    ->orderBy('title')
    ->get();

// Both can be combined freely with structured methods
$rows = $this->query()
    ->select('status')
    ->selectRaw('COUNT(*) AS total')
    ->whereRaw('EXTRACT(YEAR FROM created_at) = ?', [2024])
    ->groupBy('status')
    ->get();
```

`whereRaw` conditions are AND-combined with all other `where*` conditions and are also honoured by aggregate methods (`count`, `min`, `max`, `sum`, `avg`).

#### Aggregate functions

```php
$count  = $this->query()->where('status', '=', 'published')->count();
$count  = $this->query()->count('id');         // COUNT(id)
$total  = $this->query()->sum('amount');
$avg    = $this->query()->avg('score');
$min    = $this->query()->min('price');
$max    = $this->query()->max('price');
```

All aggregates return `int|float`. `count()` accepts an optional column name; omitting it produces `COUNT(*)`.

#### GROUP BY and HAVING

```php
$this->query()
    ->select('status', 'COUNT(*) as total')
    ->groupBy('status')
    ->having('COUNT(*) > ?', [5])
    ->get();
```

#### DISTINCT and UNION

```php
// DISTINCT rows
$rows = $this->query()->select('country')->distinct()->get();

// UNION (deduplicates) and UNION ALL (keeps duplicates)
$active   = $this->query()->where('status', '=', 'active');
$featured = $this->query()->where('featured', '=', 1);

$results = $active->union($featured)->get();
$results = $active->unionAll($featured)->get();
```

`union()` and `unionAll()` throw `UnionShapeMismatchException` if the two builders have different column counts.

#### Column aliasing

Use standard SQL `AS` syntax inside `select()`:

```php
$this->query()
    ->select('users.name as author_name', 'COUNT(*) as post_count')
    ->join('posts', 'posts.user_id', '=', 'users.id')
    ->groupBy('users.id')
    ->get();
```

#### Eager loading

Chain `->with('comments', 'author')` before `getEntities()` to load relationships in a single round trip:

```php
return $this->query()
    ->where('status', '=', 'published')
    ->with('author', 'comments.author')
    ->getEntities();
```

Dot-notation loads nested relationships.

#### Filtering on relationships

`whereHas()` keeps rows that have at least one related row; `whereDoesntHave()` keeps rows that have none. The join comes from the relationship attributes (`#[HasOne]`, `#[HasMany]`, `#[BelongsTo]`, `#[BelongsToMany]`), so you never restate foreign keys:

```php
use Marko\Database\Query\EntityQueryBuilderInterface;

// Posts with at least one comment
$posts = $this->query()->whereHas('comments')->getEntities();

// Posts whose author is not banned or suspended
$posts = $this->query()
    ->whereHas('author', fn (EntityQueryBuilderInterface $q) => $q->whereNotIn('status', ['banned', 'suspended']))
    ->getEntities();

// Posts without any approved comment
$posts = $this->query()
    ->whereDoesntHave('comments', fn (EntityQueryBuilderInterface $q) => $q->where('approved', '=', true))
    ->getEntities();

// Nested: posts with a comment whose author is verified
$posts = $this->query()
    ->whereHas('comments.author', fn (EntityQueryBuilderInterface $q) => $q->where('verified', '=', true))
    ->getEntities();
```

Each call compiles to a correlated `EXISTS` (or `NOT EXISTS`) subquery, so a has-many relationship never duplicates the outer rows. A `#[BelongsToMany]` relationship goes through its pivot table.

The constraint receives a query scoped to the related entity: unqualified columns refer to the related table, and it can call `whereHas()` again. Its return value is ignored, so arrow functions work. With a dotted path, the constraint applies to the last relationship.

Related tables get generated aliases (`marko_rel_1`, `marko_rel_2`, ...), so a self-referential relationship (a category and its child categories in the same table) works without extra setup. Don't reference those aliases yourself.

Every segment of a dotted path is validated. An unknown relationship throws `RepositoryException`, and its message lists the relationships the entity does declare. Query specifications receive an `EntityQueryBuilderInterface`, so a spec can call `whereHas()` too.

#### Query builder requirement

`query()` depends on `QueryBuilderFactoryInterface` being injected into the repository. When a driver package (`marko/database-mysql`, `marko/database-pgsql`) is installed, the container wires this automatically. If you construct a repository manually without providing a factory, `query()` throws `RepositoryException::queryBuilderNotConfigured`.

`whereHas()` and `whereDoesntHave()` need the `RelationshipExistsCompiler` that `query()` and `matching()` pass to the `RepositoryQueryBuilder`. A `RepositoryQueryBuilder` you construct yourself without one throws `RepositoryException` when either method is called.

#### Upgrading query builder implementations

`QueryBuilderInterface` gained `whereNotIn()`, `whereColumn()`, `whereExists()` and `whereNotExists()`, and `table()` gained an optional `?string $alias = null` parameter. `EntityQueryBuilderInterface` gained `whereHas()` and `whereDoesntHave()`. A class that implements either interface itself (a custom driver, decorator or test fake) must add them, or PHP refuses to load it. Callers of `table()` need no changes.

`RepositoryException::unknownRelationship()` now takes the entity class, the relationship name, the list of declared relationship names and a description of the call, so its message can list the relationships that exist.

## Relationships

Define relationships between entities using property attributes. Marko loads related entities explicitly — there is no lazy loading.

### HasOne

A user has one profile. The `foreignKey` is the property name on the **related** entity pointing back to this entity.

```php title="app/blog/Entity/User.php"
<?php

declare(strict_types=1);

namespace App\Blog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\HasOne;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('users')]
class User extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 255)]
    public string $name;

    #[HasOne(entityClass: Profile::class, foreignKey: 'userId')]
    public ?Profile $profile = null;
}
```

### HasMany

A post has many comments. The `foreignKey` is the property name on the **related** entity pointing back to this entity.

```php title="app/blog/Entity/Post.php"
<?php

declare(strict_types=1);

namespace App\Blog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\HasMany;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;

#[Table('posts')]
class Post extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 255)]
    public string $title;

    #[HasMany(entityClass: Comment::class, foreignKey: 'postId')]
    public EntityCollection $comments;
}
```

### BelongsTo

A comment belongs to a post. The `foreignKey` is the property name on **this** entity pointing to the related entity.

```php title="app/blog/Entity/Comment.php"
<?php

declare(strict_types=1);

namespace App\Blog\Entity;

use Marko\Database\Attributes\BelongsTo;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('comments')]
class Comment extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(name: 'post_id')]
    public int $postId;

    #[Column(type: 'text')]
    public string $body;

    #[BelongsTo(entityClass: Post::class, foreignKey: 'postId')]
    public ?Post $post = null;
}
```

### BelongsToMany

A post belongs to many tags through a pivot entity. The `foreignKey` is the pivot property pointing to **this** entity; `relatedKey` is the pivot property pointing to the related entity.

```php title="app/blog/Entity/Post.php"
#[BelongsToMany(
    entityClass: Tag::class,
    pivotClass: PostTag::class,
    foreignKey: 'postId',
    relatedKey: 'tagId',
)]
public EntityCollection $tags;
```

### Eager Loading

Use `with()` on the repository to load relationships without N+1 queries. Pass dot-notation strings for nested relationships.

```php
// Load posts with their comments
$posts = $postRepository->with('comments')->findAll();

// Load posts with comments and each comment's author
$posts = $postRepository->with('comments.author')->findAll();

// Multiple relationships
$posts = $postRepository->with('comments', 'tags')->findAll();
```

`with()` returns a cloned repository instance — the original is unchanged. Relationships are loaded in a single batch query per relationship level.

## EntityCollection

`findAll()` and `findBy()` return an `EntityCollection` instead of a plain array. `EntityCollection` is iterable, countable, and provides collection methods.

```php
use Marko\Database\Entity\EntityCollection;

$posts = $postRepository->findAll();

// Iterate
foreach ($posts as $post) { ... }

// Count
$posts->count();
$posts->isEmpty();

// Access
$posts->first();
$posts->last();

// Transform
$posts->filter(fn (Post $p): bool => $p->published);
$posts->map(fn (Post $p): string => $p->title);
$posts->each(fn (Post $p): void => ...);
$posts->pluck('title');          // array of property values

// Sort and group
$posts->sortBy('createdAt', descending: true);
$posts->groupBy('status');       // array<string, EntityCollection>
$posts->chunk(10);               // array<int, EntityCollection>

// Search
$posts->contains(fn (Post $p): bool => $p->id === 5);

// Convert
$posts->toArray();
```

## Query Specifications

`QuerySpecification` is an interface for encapsulating reusable query logic. Use `matching()` on the repository to apply one or more specifications.

```php title="app/blog/Query/PublishedSpec.php"
<?php

declare(strict_types=1);

namespace App\Blog\Query;

use Marko\Database\Query\EntityQueryBuilderInterface;
use Marko\Database\Query\QuerySpecification;

class PublishedSpec implements QuerySpecification
{
    public function apply(EntityQueryBuilderInterface $queryBuilder): void
    {
        $queryBuilder->where('status', '=', 'published');
    }
}
```

```php title="app/blog/Query/RecentSpec.php"
<?php

declare(strict_types=1);

namespace App\Blog\Query;

use Marko\Database\Query\EntityQueryBuilderInterface;
use Marko\Database\Query\QuerySpecification;

readonly class RecentSpec implements QuerySpecification
{
    public function __construct(
        private int $limit = 10,
    ) {}

    public function apply(EntityQueryBuilderInterface $queryBuilder): void
    {
        $queryBuilder->orderBy('created_at', 'desc')->limit($this->limit);
    }
}
```

Compose multiple specifications in a single `matching()` call:

```php
use App\Blog\Query\PublishedSpec;
use App\Blog\Query\RecentSpec;

$posts = $postRepository->matching(
    new PublishedSpec(),
    new RecentSpec(limit: 5),
);
```

### Specs with eager loading

`QuerySpecification::apply()` receives an `EntityQueryBuilderInterface`, which extends `QueryBuilderInterface` with `with()`. Specs can declare their own eager-loading needs:

```php title="app/blog/Query/PublishedWithAuthorSpec.php"
<?php

declare(strict_types=1);

namespace App\Blog\Query;

use Marko\Database\Query\EntityQueryBuilderInterface;
use Marko\Database\Query\QuerySpecification;

class PublishedWithAuthorSpec implements QuerySpecification
{
    public function apply(EntityQueryBuilderInterface $queryBuilder): void
    {
        $queryBuilder
            ->where('status', '=', 'published')
            ->with('author', 'tags');
    }
}
```

The caller does not need to know which relationships the spec requires --- they are encapsulated inside it.

## Transactions

The database driver (`marko/database-mysql` or `marko/database-pgsql`) registers `ConnectionInterface` as a shared instance: one connection, and one PDO handle, per request. Under a long-running worker such as `marko/roadrunner`, the same connection is reused for every request the worker serves. Every repository, the query builder, `marko/queue-database` and any service that injects `ConnectionInterface` all use that one connection. As a result, a transaction covers every write made through any of them.

Inject `TransactionInterface` to run a unit of work atomically. It resolves to the same shared connection:

```php title="app/billing/Service/CheckoutService.php"
<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Entity\Invoice;
use App\Billing\Entity\Payment;
use App\Billing\Repository\InvoiceRepository;
use App\Billing\Repository\PaymentRepository;
use Marko\Database\Connection\TransactionInterface;

class CheckoutService
{
    public function __construct(
        private TransactionInterface $transaction,
        private InvoiceRepository $invoices,
        private PaymentRepository $payments,
    ) {}

    public function checkout(Invoice $invoice, Payment $payment): void
    {
        $this->transaction->transaction(function () use ($invoice, $payment): void {
            $this->invoices->save($invoice);
            $this->payments->save($payment);
        });
    }
}
```

`transaction()` commits when the callback returns and rolls back, then rethrows, when it throws. Both saves above are committed together or not at all. Use `beginTransaction()`, `commit()` and `rollback()` when you need to manage the boundaries yourself. Pass `attempts` to run the transaction again when it loses a deadlock or serialization conflict: see [Concurrency Errors and Retries](#concurrency-errors-and-retries).

The entity hydrator is shared the same way. An entity loaded by one repository and saved by another is still recognised as an existing entity, and only its changed columns are written.

Under a long-running worker, the connection's `reset()` (from `ResettableInterface`) runs between requests. It rolls back any transaction a failed request left open, every nested level included, and drops its pending callbacks, so the next request never inherits it.

### Nested Transactions

Transactions nest, so a service that wraps its work in `transaction()` can be called from another service that does the same. The outermost call opens a real transaction (`BEGIN`). Each nested call opens a savepoint (`SAVEPOINT marko_sp_1`, `marko_sp_2`, ...). An inner `commit()` releases its savepoint, and only the outermost `commit()` makes the data durable. An inner `rollback()` returns to its savepoint, which undoes only the inner work:

```php title="app/billing/Service/CheckoutService.php"
public function checkout(Invoice $invoice, Payment $payment): void
{
    $this->transaction->transaction(function () use ($invoice, $payment): void {
        $this->invoices->save($invoice);

        try {
            // LoyaltyService::award() runs its own transaction(); here it becomes a savepoint.
            $this->loyalty->award($invoice);
        } catch (LoyaltyException) {
            // Only the loyalty writes are rolled back; the invoice is still saved.
        }

        $this->payments->save($payment);
    });
}
```

An exception that escapes the outer callback rolls back everything, inner work included. `transactionLevel()` reports the depth: `0` outside a transaction, `1` inside the outermost one, `2` inside the first savepoint. `commit()` or `rollback()` with no open transaction throws `TransactionException`.

### After-Commit Callbacks

Side effects such as pushing a job, sending mail, invalidating a cache or broadcasting must not happen until the data they refer to is committed. A worker could otherwise pick up the job before the row is visible, or after it was rolled back. Register them with `afterCommit()`:

```php title="app/billing/Service/CheckoutService.php"
$this->transaction->transaction(function () use ($invoice): void {
    $this->invoices->save($invoice);

    $this->transaction->afterCommit(
        fn () => $this->queue->push(new SendReceipt($invoice->id)),
    );
});
```

- Inside a transaction, the callback is queued and runs once the **outermost** transaction commits, after `COMMIT` has returned.
- Outside a transaction, it runs immediately, so code that registers callbacks works whether or not a caller opened a transaction.
- A callback registered inside a level that rolls back never runs. This includes a savepoint that is rolled back while the outer transaction later commits.
- An exception thrown by a callback propagates to the code that called the outermost `commit()` or `transaction()`, and the remaining callbacks do not run. **The data is already committed when this happens**, so handle the failure in the callback (retry, log, or queue it) rather than treating it as a rollback.

`afterRollback()` is the counterpart. Its callback runs when the level it was registered in rolls back, either directly or because an enclosing level rolls back. Outside a transaction there is nothing to roll back, so the callback is discarded. Pending callbacks are dropped without running when the connection is reset or disconnected.

:::note
A test that wraps each case in a transaction that is rolled back afterwards (`RefreshDatabase` in `marko/testing`, or `DatabaseTestHelper`) never commits, so after-commit callbacks registered inside it never run on their own. Call `RefreshDatabase::runAfterCommitCallbacks()` to run the queued callbacks without committing (see [Database Tests](/docs/packages/testing/#after-commit-callbacks)), or use `TruncateDatabase` and let a real transaction commit. Drivers support this through `PendingAfterCommitInterface`, which the PostgreSQL, MySQL and read/write connections implement.
:::

### Row Locks

Read-modify-write sequences, such as counters, inventory or state machines, need the rows they read to stay put until they write. Lock them from the query builder inside a transaction:

```php title="app/inventory/Service/StockService.php"
public function reserve(int $productId, int $quantity): void
{
    $this->transaction->transaction(function () use ($productId, $quantity): void {
        $stock = $this->stockRepository->query()
            ->where('product_id', '=', $productId)
            ->lockForUpdate()
            ->firstEntity();

        if ($stock === null || $stock->available < $quantity) {
            throw StockException::insufficient($productId, $quantity);
        }

        $stock->available -= $quantity;
        $this->stockRepository->save($stock);
    });
}
```

| Method | PostgreSQL | MySQL | MariaDB |
|--------|------------|-------|---------|
| `lockForUpdate()` | `FOR UPDATE` | `FOR UPDATE` | `FOR UPDATE` |
| `sharedLock()` | `FOR SHARE` | `LOCK IN SHARE MODE` (`FOR SHARE` when combined with a modifier) | `LOCK IN SHARE MODE` |
| `skipLocked()` | `SKIP LOCKED` | `SKIP LOCKED` | `SKIP LOCKED` |
| `noWait()` | `NOWAIT` | `NOWAIT` | `NOWAIT` |

So `sharedLock()->skipLocked()` is `FOR SHARE SKIP LOCKED` on PostgreSQL and MySQL and `LOCK IN SHARE MODE SKIP LOCKED` on MariaDB. `marko/database-mysql` detects which of the two servers it is talking to (see [MySQL vs MariaDB](/docs/packages/database-mysql/#mysql-vs-mariadb)).

`lockForUpdate()` blocks other transactions from updating, deleting or locking the rows. `sharedLock()` lets other transactions read and share-lock them but not change them. Add `skipLocked()` to return only the rows nobody else holds, which is useful for work queues. Add `noWait()` to fail immediately with a `LockTimeoutException` instead of waiting (see [Concurrency Errors and Retries](#concurrency-errors-and-retries)).

These misuses throw `LockException`:

- **Outside a transaction.** A locked `get()` or `first()` with no open transaction (or on a connection that does not implement `TransactionInterface`) throws. The lock would be released as soon as the `SELECT` finished, which is almost always a bug.
- **A modifier without a lock.** `skipLocked()` or `noWait()` without `lockForUpdate()` / `sharedLock()` throws. So does combining `skipLocked()` with `noWait()`.
- **Aggregates and unions.** A lock cannot be combined with `count()`, `min()`, `max()`, `sum()`, `avg()` or `union()` / `unionAll()`. Lock the rows with `get()` and aggregate them afterwards.

Locks apply to the rows the query itself selects. Relationships loaded with `with()` are fetched by separate, unlocked queries. On PostgreSQL, `FOR UPDATE` also cannot be combined with `DISTINCT`, `GROUP BY` or `HAVING`, and the database rejects the query.

## Upsert

`upsert()` inserts rows, or updates the existing row when one already has the same values in the conflict columns, in a single statement. Use it instead of "select, then insert or update", which races.

```php
$affected = $this->queryBuilderFactory->create()
    ->table('subscribers')
    ->upsert(
        rows: [
            ['email' => 'ada@example.com', 'name' => 'Ada', 'visits' => 1],
            ['email' => 'alan@example.com', 'name' => 'Alan', 'visits' => 1],
        ],
        uniqueBy: ['email'],
        update: ['name', 'visits'],
    );
```

- Every row must have the same columns in the same order. Each `$uniqueBy` and `$update` column must be one of them.
- `$update` defaults to `null`, which updates every inserted column except the `$uniqueBy` ones. Pass a list to update only those columns. Pass `[]` to insert new rows and leave existing ones untouched.
- The return value is the affected-row count as the driver reports it. MySQL counts an updated row as 2 and an unchanged one as 0, so don't compare counts across drivers.
- `UpsertException` is thrown for empty rows, an empty `$uniqueBy`, rows with different columns, or a `$uniqueBy`/`$update` column that isn't in the rows.

Conflict detection differs by driver:

- **PostgreSQL** compiles to `INSERT ... ON CONFLICT (unique columns) DO UPDATE SET col = EXCLUDED.col` (`DO NOTHING` for an empty update list). It needs a unique index or constraint on exactly the `$uniqueBy` columns. It rejects a batch that contains the same conflict key twice.
- **MySQL / MariaDB** compiles to `INSERT ... ON DUPLICATE KEY UPDATE col = VALUES(col)`. MySQL resolves a conflict against **any** unique index or primary key the row violates, so `$uniqueBy` only shapes the default update list. An empty update list becomes a no-op assignment, never `INSERT IGNORE`, so other errors still surface.

### Repository Upsert

`Repository::upsert(array $entities, array $uniqueBy, ?array $update = null): int` upserts entities through the query builder. It requires a configured query builder factory, which the container provides. `$uniqueBy` and `$update` name entity **properties**, never columns. The conflict properties are always given explicitly and are never inferred:

```php
$postRepository->upsert($posts, uniqueBy: ['slug']);
```

- With `$update = null`, every property except the `$uniqueBy` ones, the primary key and the `#[Timestamps]` created-at property is updated.
- `#[Timestamps]` are applied first: created-at is filled when unset, and updated-at is set to now.
- The batch rules of `insertBatch()` apply: the same entity class, no companions, and identical column sets. An auto-increment key must be either set on every entity or on none.
- Upsert does not fire lifecycle events, set generated ids or register entities for dirty tracking, because it can't tell which rows were inserted and which were updated. Load the entities again with `findBy()` when you need them.

:::caution
Depend on `ConnectionInterface` or `TransactionInterface`, never on `MySqlConnection` or `PgSqlConnection` directly. Only the interfaces are shared. Requesting a concrete connection class builds a new, separate connection that is outside every transaction. It also bypasses `marko/database-readwrite` when that package is enabled.
:::

## Query and Constraint Exceptions

When the database rejects a statement, the driver turns the `PDOException` into a typed exception from `Marko\Database\Exceptions`. This applies to repositories, the query builder, `ConnectionInterface::query()`/`execute()` and prepared statements alike. Unique and foreign key violations are normal events (double submits, races, deleting a row that is still referenced), and you can handle them without knowing which driver is installed:

| Exception | Raised when | PostgreSQL | MySQL / MariaDB | HTTP |
|-----------|-------------|------------|-----------------|------|
| `UniqueConstraintViolationException` | A duplicate value hits a unique column or index | `23505` | `1062` | `409` |
| `ForeignKeyConstraintViolationException` | A row references a missing parent, or a referenced row is deleted or updated | `23503` | `1451`, `1452` (and legacy `1216`, `1217`) | `409` |
| `NotNullConstraintViolationException` | `NULL` (or no value) is written to a `NOT NULL` column | `23502` | `1048`, `1364` | --- |
| `CheckConstraintViolationException` | A row fails a `CHECK` constraint | `23514` | `3819` (MySQL), `4025` (MariaDB) | --- |
| `DeadlockException`, `SerializationFailureException`, `LockTimeoutException` | A transaction conflict or lock timeout: see [Concurrency Errors and Retries](#concurrency-errors-and-retries) | `40P01`, `40001`, `55P03` | `1213`, `1020`, `1205`, `3572` | --- |
| `QueryException` | Any other driver error | anything else | anything else | --- |

PostgreSQL is matched on the SQLSTATE and MySQL on the server error number, because MySQL reports every integrity violation as SQLSTATE `23000`.

The hierarchy is:

```
DatabaseException
└── QueryException                      sql(), bindings(), sqlState()
    ├── ConstraintViolationException    constraintName(), table(), column()
    │   ├── UniqueConstraintViolationException
    │   ├── ForeignKeyConstraintViolationException
    │   ├── NotNullConstraintViolationException
    │   └── CheckConstraintViolationException
    ├── TransactionConflictException    isRetryable() (abstract)
    │   ├── DeadlockException
    │   └── SerializationFailureException
    └── LockTimeoutException
```

The original `PDOException` is always available from `getPrevious()`. `constraintName()`, `table()` and `column()` are parsed from the driver message and return `null` when the driver does not report them. `table()` is the table that owns the constraint. For a foreign key violation that is the referencing (child) table, whether the statement inserted the child or deleted the parent. For a unique violation on PostgreSQL, `column()` is the indexed column list as the server reports it, for example `email` or `tenant_id, email`.

### Handle a Duplicate Email

Catch `UniqueConstraintViolationException` and turn it into a validation error:

```php title="app/account/Service/RegistrationService.php"
<?php

declare(strict_types=1);

namespace App\Account\Service;

use App\Account\Entity\User;
use App\Account\Repository\UserRepository;
use Marko\Database\Exceptions\UniqueConstraintViolationException;
use Marko\Validation\Exceptions\ValidationException;
use Marko\Validation\Validation\ValidationErrors;

class RegistrationService
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    /**
     * @throws ValidationException
     */
    public function register(string $email): User
    {
        $user = new User();
        $user->email = $email;

        try {
            $this->userRepository->save($user);
        } catch (UniqueConstraintViolationException $e) {
            if ($e->constraintName() !== 'users_email_unique') {
                throw $e;
            }

            throw ValidationException::withErrors(
                new ValidationErrors(['email' => ['This email address is already registered.']]),
            );
        }

        return $user;
    }
}
```

Checking with `existsBy(['email' => $email])` first gives a friendlier path for the common case. Keep the `catch` anyway: two requests can both pass the check before either one inserts, and only the database constraint catches that race.

:::caution
PostgreSQL aborts the current transaction after any error. If the `save()` above runs inside `transaction()`, every later statement in that transaction fails with SQLSTATE `25P02` until it is rolled back. Catch the violation outside the transaction (let `transaction()` roll back and rethrow), or roll back before you continue. MySQL does not abort the transaction, but keep the same shape so the code works on both drivers.
:::

### HTTP Status

`UniqueConstraintViolationException` and `ForeignKeyConstraintViolationException` implement `Marko\Core\Exceptions\HttpExceptionInterface`. If one escapes a controller, the routing pipeline renders a **`409 Conflict`** with the body `{"message": "Conflict."}`. The constraint name, SQL and bound values are never sent to the client. Not-null and check violations usually point at a missing validation rule, so they are not mapped and surface as a `500`. See [Errors and HTTP Exceptions](/docs/packages/routing/#errors-and-http-exceptions).

`marko/database` depends only on `marko/core` for this interface. It does not require `marko/routing` or an errors package.

### Messages and Redaction

Exception messages are built from the parsed constraint, table and column, never copied from the driver. PostgreSQL's `DETAIL:` line (`Key (email)=(ada@example.com) already exists`) and MySQL's `Duplicate entry 'ada@example.com'` both contain row data. For example:

```
Unique constraint 'users_email_unique' violated on table 'users'
```

The exception context holds the SQL with its placeholders. It never holds the bound values. A plain `QueryException` uses the first line of the driver message, with any string binding the driver echoes replaced by `[redacted]`. The raw values stay available to your own code through `bindings()`, and the untouched driver message through `getPrevious()`. Be careful what you log from either one.

### Upgrading From `catch (PDOException)`

Before this change the raw `PDOException` escaped from `query()`, `execute()` and the repositories. Code that catches `PDOException` around those calls no longer matches. Catch `QueryException` (or a constraint subclass) instead, and read `sqlState()` or `getPrevious()` where you used to inspect the PDO error. Retry loops that matched SQLSTATE `40001` / `40P01` or MySQL `1213` should catch `TransactionConflictException`, or pass `attempts` to `transaction()` (see below).

## Concurrency Errors and Retries

Under concurrency, the database sometimes aborts a transaction that did nothing wrong: two transactions deadlocked, or a `REPEATABLE READ` / `SERIALIZABLE` transaction could not be serialized against a concurrent one. Running the transaction again is the expected fix. A statement can also fail because it could not get a lock in time. Each case has its own exception, so you never have to match SQLSTATE strings:

| Exception | Raised when | PostgreSQL | MySQL / MariaDB | Retryable |
|-----------|-------------|------------|-----------------|-----------|
| `DeadlockException` | The database broke a deadlock by aborting this transaction | `40P01` | `1213` (SQLSTATE `40001`) | yes |
| `SerializationFailureException` | A `REPEATABLE READ` / `SERIALIZABLE` transaction conflicted with a concurrent one, on a statement or at `COMMIT` | `40001` | `1020` (MariaDB with `innodb_snapshot_isolation=ON`, SQLSTATE `HY000`). MySQL InnoDB reports most of these conflicts as `1213` deadlocks | yes |
| `LockTimeoutException` | A lock was not granted: `noWait()` hit a locked row, or the lock wait timeout expired | `55P03` (`NOWAIT`, `lock_timeout`) | `3572` (`NOWAIT`), `1205` (`innodb_lock_wait_timeout`) | caller decides |

`DeadlockException` and `SerializationFailureException` extend `TransactionConflictException`, whose `isRetryable()` returns `true`. Catch the base class to handle both. On MySQL a deadlock reports SQLSTATE `40001`, so `DeadlockException::sqlState()` returns `40001` there and `40P01` on PostgreSQL.

`LockTimeoutException` extends `QueryException` directly and is never retried for you. Whether to wait and try again, skip the row (`skipLocked()`), or tell the user the record is busy depends on the use case. On PostgreSQL the error aborts the transaction. On MySQL only the statement fails and the transaction stays open.

A failed `COMMIT` is translated too, so a serialization failure PostgreSQL detects at `COMMIT` arrives as `SerializationFailureException` with `sql()` returning `COMMIT`.

### Retrying a Transaction

Pass `attempts` to `transaction()` to retry the whole transaction on a `TransactionConflictException`:

```php title="app/inventory/Service/StockService.php"
public function reserve(int $productId, int $quantity): void
{
    $this->transaction->transaction(function () use ($productId, $quantity): void {
        $stock = $this->stockRepository->query()
            ->where('product_id', '=', $productId)
            ->lockForUpdate()
            ->firstEntity();

        // ...
        $this->stockRepository->save($stock);
    }, attempts: 3);
}
```

- **Opt-in.** `attempts` defaults to `1`, which runs the transaction once and never retries. A value below `1` throws `TransactionException`.
- **Only conflicts are retried.** A `DeadlockException` or `SerializationFailureException` raised by the callback or by `COMMIT` rolls the attempt back and starts the next one from `BEGIN`. Any other exception is rethrown at once. After the last attempt, the last conflict is rethrown.
- **Backoff between attempts.** Before each retry the connection waits, so transactions that conflicted with each other don't collide again at the same instant. See [Backoff](#backoff) below.
- **Callbacks.** The `afterCommit()` callbacks a failed attempt registered are discarded, and its `afterRollback()` callbacks run. Only the attempt that commits runs its after-commit callbacks. The callback itself runs once per attempt, so keep side effects that must happen once in `afterCommit()`.
- **After commit, no retry.** An exception thrown by an after-commit callback is never retried, even when it is a conflict from the callback's own query, because the data is already committed.
- **Only the outermost level retries.** A nested `transaction()` (a savepoint) ignores `attempts` and `backoff`, and never waits: the conflict propagates to the outermost `transaction()`, which owns the retry. Retrying only the savepoint would not help: the outer transaction still holds the locks and snapshot that caused the conflict, and on a deadlock MySQL has already rolled the whole transaction back. Put `attempts` on the outermost call.

#### Backoff

The `backoff` argument sets the wait between attempts. It applies only when `attempts` is above `1`. The connection never waits after the last attempt.

| `backoff` | Wait before the next attempt |
|-----------|------------------------------|
| `null` (default) | Exponential backoff with full jitter: a random delay between `0` and `min(500, 10 * 2 ** ($attempt - 1))` milliseconds, so up to 10 ms, then 20 ms, 40 ms, and so on, capped at 500 ms |
| `int` | That many milliseconds after every failed attempt. `0` retries at once |
| `Closure(int $attempt, TransactionConflictException $conflict): int` | The milliseconds the closure returns |

`$attempt` is the number of the attempt that just failed, starting at `1`. A negative `int` throws `TransactionException` before the transaction begins, and a closure that returns a negative number (or anything other than an `int`) throws `TransactionException` instead of retrying:

```php title="app/inventory/Service/StockService.php"
use Marko\Database\Exceptions\DeadlockException;
use Marko\Database\Exceptions\TransactionConflictException;

// A fixed 50 ms between attempts
$this->transaction->transaction($reserve, attempts: 3, backoff: 50);

// Retry deadlocks at once, back off on serialization failures
$this->transaction->transaction(
    $reserve,
    attempts: 5,
    backoff: fn (int $attempt, TransactionConflictException $conflict): int => $conflict instanceof DeadlockException
        ? 0
        : 25 * $attempt,
);
```

The wait blocks the current process: the connection sleeps through `Marko\Core\Sleep\SleeperInterface`, which `marko/core` binds to `Marko\Core\Sleep\UsleepSleeper`. Bind your own implementation to change how it waits. In tests, give the connection a `TransactionBackoff` built with `FakeSleeper` from `marko/testing` to assert the delays without sleeping:

```php title="tests/Unit/StockServiceTest.php"
use Marko\Database\Connection\TransactionBackoff;
use Marko\Testing\Fake\FakeSleeper;

$sleeper = new FakeSleeper();
$connection = new MySqlConnection($config, transactionBackoff: new TransactionBackoff($sleeper));

// ... run a transaction that conflicts twice with attempts: 3, backoff: 50

$sleeper->assertSlept(50, 50);
```

Pass a seeded `Random\Randomizer` as the second `TransactionBackoff` argument to make the default jittered delays repeatable.

:::note
Under `RefreshDatabase` or `DatabaseTestHelper`, each test runs inside a transaction that is rolled back afterwards, so every `transaction()` your code calls is nested and never retries. Test retry behaviour with `TruncateDatabase` or against a connection outside that wrapper.
:::

### No HTTP Status

These exceptions do not implement `HttpExceptionInterface`. A conflict that is still failing after the retries, or a lock you could not get, is a server-side failure, so it surfaces as a `500` like any other `QueryException`. If you want a `503 Service Unavailable` with `Retry-After` instead, catch the exception in your controller or middleware and build that response.

### Upgrading `TransactionInterface` Implementations

`TransactionInterface::transaction()` is now `transaction(callable $callback, int $attempts = 1, int|Closure|null $backoff = null): mixed`. A class that implements the interface itself (a custom driver, decorator or test fake) must add the new parameters, or PHP refuses to load it. Decorators should pass `$attempts` and `$backoff` on to the connection they wrap, as `ReadWriteConnection` does.

## Bulk Insert

`Repository::insertBatch(array $entities): void` inserts multiple entities in a single multi-row `INSERT` statement, run through `transaction()` (a savepoint when a transaction is already open). It fires `EntityCreating` and `EntityCreated` events for each entity.

```php
use App\Blog\Entity\Post;

$posts = [];
for ($i = 1; $i <= 1000; $i++) {
    $post = new Post();
    $post->title = "Post {$i}";
    $post->slug  = "post-{$i}";
    $posts[] = $post;
}

$postRepository->insertBatch($posts);
```

**Caveats:**

- Relationships are **not** auto-persisted. Persist related entities separately before calling `insertBatch()`.
- `EntityCreating` / `EntityCreated` events fire synchronously for every entity in the batch. For high-throughput imports, mark observers async via `marko/queue` or drop to the raw query builder to avoid the per-row overhead.
- A constraint violation on any row rolls back the whole batch and rethrows the typed exception (for example `UniqueConstraintViolationException`, with the `PDOException` as `getPrevious()`). It is not wrapped in `BatchInsertException`, so the same `catch` works for `save()` and `insertBatch()`. See [Query and Constraint Exceptions](#query-and-constraint-exceptions).
- All entities must be of the same type and have identical column sets. `BatchInsertException` is thrown for empty input, mixed types, mismatched columns, a batch that mixes set and unset [generated keys](#database-generated-keys), when the number of rows returned by `RETURNING` does not equal the number of inserted entities, or (MySQL) when `@@auto_increment_increment` cannot be read as a positive integer.
- Keys follow the same rules as `save()`: an unset key that is neither `autoIncrement` nor `generated`, or an unset generated key on a connection without `RETURNING`, throws a `RepositoryException` before anything is inserted.

**ID assignment after batch insert:**

- **Connections that support `RETURNING`** (`supportsReturning()` is `true`: PostgreSQL, MariaDB 10.5+ and SQLite) --- the `INSERT` ends with `RETURNING <primary key>`, and keys are matched to entities by the row order `RETURNING` returns. SQLite is the exception: it documents that order as unspecified, so its `AUTOINCREMENT` keys are sorted ascending (insert order) before matching. Neither server documents that a multi-row `INSERT ... RETURNING` returns rows in `VALUES` order, but both do, and integration tests cover it on every server version CI runs (PostgreSQL 17, MariaDB 11.8 and 10.11), including a sequence or `auto_increment` step of 5. The row count is checked (`BatchInsertException`).
- **MySQL** --- `@@auto_increment_increment` is read once per batch, before the `INSERT`, and each entity gets `LAST_INSERT_ID()` plus its offset times that increment. See [Generated Primary Keys](/docs/packages/database-mysql/#generated-primary-keys) for the `innodb_autoinc_lock_mode` conditions.

If you need a per-row guarantee rather than reliance on `RETURNING` order, call `save()` on each entity inside `transaction()` (N round trips instead of one).

## Seeders

Seeders populate development/test databases with sample data. They're discovered via the `#[Seeder]` attribute.

Each seeder runs inside a database transaction. If a seeder fails partway through, all its changes are automatically rolled back — preventing partial data that would require manual cleanup.

```php title="app/blog/Seed/PostSeeder.php"
<?php

declare(strict_types=1);

namespace App\Blog\Seed;

use App\Blog\Entity\Post;
use App\Blog\Repository\PostRepositoryInterface;
use Marko\Database\Seed\Seeder;
use Marko\Database\Seed\SeederInterface;

#[Seeder(name: 'posts', order: 10)]
readonly class PostSeeder implements SeederInterface
{
    public function __construct(
        private PostRepositoryInterface $postRepository,
    ) {}

    public function run(): void
    {
        $post = new Post();
        $post->title = 'Hello World';
        $post->slug = 'hello-world';
        $post->content = 'Welcome to my blog!';
        $post->createdAt = date('Y-m-d H:i:s');

        $this->postRepository->save($post);
    }
}
```

> **Seeders use `new Post()`.** A seeder writes a handful of known rows, so plain construction is the clearest way to say exactly what lands in the database. For test data, see [Entity Factories](#entity-factories).

> **IDE Note:** PhpStorm may report seeder classes as "unused" since they're discovered via attributes rather than direct instantiation. The `@noinspection PhpUnused` annotation suppresses this false positive.

Place seeders in your module's `Seed/` directory. The `order` parameter controls execution sequence — use spaced numbers (10, 20, 30) rather than sequential (1, 2, 3) to allow other modules to insert seeders between existing ones without renumbering.

## Entity Factories

`Marko\Database\Testing\EntityFactory` builds entities for tests. It stays explicit: `definition()` returns a constructed entity using `new` and property assignment, not an array of attributes, so IDE navigation, static analysis and renames keep working. There is no Faker dependency; call Faker inside `definition()` yourself if you want random data.

```php title="tests/Factory/PostFactory.php"
<?php

declare(strict_types=1);

namespace Tests\Factory;

use App\Blog\Entity\Post;
use App\Blog\Entity\PostStatus;
use App\Blog\Repository\PostRepository;
use Marko\Database\Testing\EntityFactory;

/**
 * @extends EntityFactory<Post>
 */
class PostFactory extends EntityFactory
{
    protected const string REPOSITORY = PostRepository::class;

    private int $number = 0;

    protected function definition(): Post
    {
        $number = ++$this->number;

        $post = new Post();
        $post->title = "Post $number";
        $post->slug = "post-$number";
        $post->status = PostStatus::Draft;

        return $post;
    }
}
```

```php
$posts = new PostFactory($container);

$draft = $posts->make();                                                 // built, not saved
$live = $posts->create(fn (Post $post) => $post->status = PostStatus::Live);
$three = $posts->createMany(3);                                          // list<Post>

$mixed = $posts
    ->sequence(
        fn (Post $post) => $post->status = PostStatus::Draft,
        fn (Post $post) => $post->status = PostStatus::Live,
    )
    ->makeMany(4);                                                       // Draft, Live, Draft, Live
```

| Method | Does |
|--------|------|
| `make(callable ...$states)` | Calls `definition()`, applies the next `sequence()` state, then each state closure in order. Nothing is saved |
| `create(callable ...$states)` | `make()`, then `save()` through the repository named by `REPOSITORY`, resolved from the container. `EntityCreating` and `EntityCreated` fire as in production |
| `makeMany(int $count, callable ...$states)` / `createMany(...)` | The same, `$count` times, returning a list |
| `sequence(callable ...$states)` | A copy of the factory that applies the states in turn, one per entity, starting again after the last |

`make()` works without a container. `create()` throws an `EntityFactoryException` when the factory has no container, or when `REPOSITORY` does not name a class implementing `RepositoryInterface`.

### Factories or `new`?

Both are explicit; pick by how much of the entity the test is about.

- **Use `new`** when the test is about the values themselves: a unit test of an entity or service, a seeder, or a test where every property matters. Writing them out keeps the intent visible.
- **Use a factory** when a test needs valid, saved entities as background and only one or two properties matter. The factory holds the defaults that make an entity valid, so each test states only what it is testing: `$posts->create(fn (Post $p) => $p->status = PostStatus::Live)`.
- **Prefer a state closure to a new factory method.** Name a state with a plain function or a static method when several tests share it, rather than adding methods that hide what they set.

For isolating database tests (`RefreshDatabase`, `TruncateDatabase`), see [Database Tests](/docs/packages/testing/#database-tests) in `marko/testing`.

## CLI Commands

| Command | Description |
|---------|-------------|
| `marko db:status` | Show migration status |
| `marko db:diff` | Preview changes between entities and database |
| `marko db:migrate` | Apply migrations; in development, also generate them from entity changes |
| `marko db:rollback` | Revert the last migration batch (`--step=N` for more); see [Environment Behaviour](#environment-behaviour) |
| `marko db:reset` | Rollback all migrations; see [Environment Behaviour](#environment-behaviour) |
| `marko db:rebuild` | Reset + re-run all migrations; see [Environment Behaviour](#environment-behaviour) |
| `marko db:seed` | Run seeders (`--class=name` for one); see [Environment Behaviour](#environment-behaviour) |

`db:rollback` and `db:reset` read migration names from the `migrations` table, but only run files that a scan of `database/migrations/` finds. Before any migration is reverted, each name is checked: one containing `/`, `\`, `..` or a NUL byte throws `MigrationException::invalidMigrationName()`, and one with no matching file throws `MigrationException::migrationNotFound()`. Either error stops the command before anything is rolled back.

### Environment Behaviour

The commands read the environment from core's [`AppEnvironment`](/docs/packages/core/#application-environment) (`MARKO_ENV`, then `APP_ENV`). When neither is set the environment is `production`, so a deployment that forgets to set it fails safe.

| Environment | `db:migrate` | `db:rollback`, `db:reset`, `db:rebuild`, `db:seed` |
|-------------|--------------|----------------------------------------------------|
| `development`, `dev`, `local` | Applies pending files, then generates and applies a migration for any entity change | Allowed |
| `testing`, `test` | Applies pending files only, and warns about drift | Allowed |
| `production`, `prod`, or unset | Applies pending files only, and warns about drift | Refused with exit code 1, even with `--force` |
| Anything else (`staging`, `qa`, `preview`, a typo, ...) | Applies pending files only, and warns about drift | Refused with exit code 1 unless you pass `--force`; with `--force`, asks for confirmation when a terminal is attached |

Outside development, the drift warning is informational. If the drift check can't probe an expression default (see [Column Defaults](#column-defaults)), `db:migrate` prints `Warning: The drift check could not compare an expression default with the database, so it was skipped.` on STDERR, followed by the database error and how to fix it, such as granting the user `CREATE TEMPORARY TABLES` on MySQL or `TEMPORARY` on PostgreSQL. It then exits with the status of the migrations it applied.

Destructive commands need evidence that the database is disposable, not merely the absence of the word "production". Only development and testing names count as disposable, so a staging database (which often holds a production snapshot or QA's data) or a misspelled environment name is protected by default:

```
Error: db:rebuild is refused in the 'staging' environment without --force.
This command drops every table and re-runs all migrations. It runs without --force only in development (development, dev, local) and testing (testing, test).
Re-run with --force if the 'staging' database may be changed.
```

With `--force`, the command asks before it runs when someone can answer:

```
db:rebuild drops every table and re-runs all migrations in the 'staging' environment. Continue? [y/N]
```

Answering anything other than `y` or `yes` cancels the command with exit code 0. When nobody can answer (CI, a deploy script, piped input, or `--no-interaction`), `--force` alone lets the command run, so a CI job that rebuilds or seeds a staging or preview database passes both flags:

```bash
APP_ENV=staging marko db:rebuild --force --no-interaction
APP_ENV=staging marko db:seed --force --no-interaction
```

Production has no override. `SeederRunner` applies the same policy when you call it from your own code: `runAll()` and `runByName()` throw `SeederException` outside development and testing unless you pass `force: true`, and always throw in production.

#### Using the policy in your own commands

The `db:*` commands get this policy from `Marko\Database\Command\DestructiveCommandGuard`. Inject it into your own destructive command and call `check()` before doing anything. It returns `null` when the command may go ahead. Otherwise it returns the exit code to stop with: `1` when the environment refuses the command, or `0` when the person declines the confirmation. It writes the error or cancellation message itself. Declare `force` as a flag on the command so `--force` never consumes the next argument, and mark the command `destructive: true` so the [MCP `run_console_command` tool](/docs/packages/mcp/#run_console_command-safety) and other callers refuse it unless explicitly allowed (`#[Command(name: 'app:wipe', flags: ['force'], destructive: true)]`). Every shipped command that uses the guard carries the marker, and a repository test keeps it that way.

```php
public function check(
    string $command,
    string $effect,
    Input $input,
    Output $output,
    bool $allowInProduction = false,
    bool $confirmInDevelopment = false,
): ?int;
```

Some destructive changes are needed in production, where a rebuild never is. Two opt-ins cover them, passed per call so the `db:*` commands keep the defaults:

| Argument | Effect |
|----------|--------|
| `allowInProduction: true` | Production (or no environment set) is treated like staging. The command is refused without `--force`. With `--force`, it asks for confirmation when someone can answer and runs when nobody can. |
| `confirmInDevelopment: true` | Development and testing still run the command without `--force`, but ask for confirmation first when someone can answer. |

`admin-auth:permissions:sync --prune` passes both (see [marko/admin-auth](/docs/packages/admin-auth/#syncing-permissions-to-the-database)):

```php
$refusal = $this->destructiveCommandGuard->check(
    'admin-auth:permissions:sync --prune',
    'deletes 2 unregistered permission(s) and their role assignments',
    $input,
    $output,
    allowInProduction: true,
    confirmInDevelopment: true,
);

if ($refusal !== null) {
    return $refusal;
}
```

Generation runs only in development: staging is stricter than "not production" and never writes migration files on its own. When `db:migrate` skips generation because of the environment and the entities differ from the database, it prints the SQL it would have generated:

```
Warning: Entity schema differs from database.
Migrations are not generated in the 'production' environment. Differences:
  CREATE INDEX "shows_live_idx" ON "shows" ("status") WHERE status = 'live'
Run db:migrate in development to generate a migration, then commit and deploy it.
```

### db:migrate Options

| Option | Effect |
|--------|--------|
| `--generate` | Generate migrations from the entity diff even outside development |
| `--no-generate` | Never generate; only apply committed migration files |
| `--force` | Generate destructive changes without asking |
| `--verbose`, `-v` | Show the SQL statements |

`--generate` and `--no-generate` cannot be combined.

Generated migration files are named `{YmdHis}_{operation}_{table}.php`, with the timestamp read from the injected `ClockInterface`. When one run generates several files, each one is a second later than the previous, so they apply in dependency order.

Each statement is written as a single-quoted PHP string, `$this->execute($connection, '...');`, with quotes and backslashes escaped, so no table, column or index name read from the database can end the string and add PHP to the file. A name that holds a control character (a newline, tab, NUL or similar) is never a real schema name, so generation refuses it with a `MigrationException` naming the identifier and writes no files. Rename the object in the database and run `db:migrate` again.

### Destructive Changes

The entity is the source of truth for the tables it owns, so a column, index or foreign key the entity no longer declares is dropped. Before generating a migration that drops anything, `db:migrate` lists each destructive statement and asks for confirmation:

```
This migration would remove existing database objects:
  ALTER TABLE "shows" DROP COLUMN "legacy_rating"
  DROP INDEX "shows_old_idx"

Generate a migration with these changes? [y/N]
```

Answering anything other than `y` or `yes` cancels generation. When nobody can answer (CI, a deploy script, piped input, or `--no-interaction`), the command exits with code 1 and generates nothing unless you pass `--force`. The question is asked through core's [`ConfirmationPrompterInterface`](/docs/packages/core/#asking-for-confirmation); in tests, pass a [`FakeConfirmationPrompter`](/docs/packages/testing/#fakeconfirmationprompter) to `MigrateCommand`. Tables no entity owns (such as a table an application creates in a hand-written migration) are never touched. Package tables like `sessions`, `jobs` and `failed_jobs` belong to entities that [`marko/session-database`](/docs/packages/session-database/) and [`marko/queue-database`](/docs/packages/queue-database/) ship, so `db:migrate` creates and diffs them like your own.

### Column Changes

Changing a column's type, nullability or default on an existing entity generates a migration that changes the column in place. On PostgreSQL, the change goes into one `ALTER TABLE`:

```sql
ALTER TABLE "posts" ALTER COLUMN "views" TYPE BIGINT USING "views"::BIGINT, ALTER COLUMN "views" SET DEFAULT 0
```

A PostgreSQL type change always casts explicitly with `USING "column"::type`, so conversions PostgreSQL won't make on its own (`VARCHAR` to `INTEGER`, `TEXT` to `JSONB`, `INTEGER` to `BOOLEAN`) work in both the up and the down migration. An auto-increment key whose type changes (`integer` to `bigint`, for example) gets one `DO` block that changes its sequence too, so the ids can use the wider range. See [Type Changes](/docs/packages/database-pgsql/#type-changes).

MySQL restates the whole column with `MODIFY COLUMN`, and keeps what the entity can't declare (precision, `UNSIGNED`, collation, `ON UPDATE`) unless the entity changes the type. See [Column Modifications](/docs/packages/database-mysql/#column-modifications). The down migration puts back the column's previous definition, so `db:rollback` reverses the change.

A few things to know:

- **An undeclared default or length is kept.** If the entity declares no `default`, or no `length`, the column keeps the one the database already has. A migration never drops a default or resizes a `VARCHAR` just because the entity leaves it out. Both drivers apply the same rule, `Column::resolveAgainst()`, which mirrors the tolerances the diff uses to decide that a column is unchanged.
- **A column with nothing left to change gets no statement.** When every difference is one the diff accepts (an undeclared length or default, or uniqueness, which the index diff handles), neither the up nor the down migration touches the column.
- **Primary key changes to an existing column are refused, and on PostgreSQL so are auto-increment changes.** Making an existing column part of the key or taking it out can't be done by changing the column, so generating SQL for it throws a `MigrationException` naming the column, on both drivers. Write that change in a migration by hand. Adding a new key column is different, see [Primary Keys on Existing Tables](#primary-keys-on-existing-tables).
- **`SET NOT NULL` needs data that satisfies it.** Making a column required fails if existing rows hold `NULL`. Fill those rows in first.
- **A type change needs data that converts.** Changing `VARCHAR` to `INTEGER` on a table holding `'abc'` fails inside the migration's transaction. Clean up those rows first.

### Primary Keys on Existing Tables

When an entity declares a primary key column that a table without a primary key doesn't have yet, the migration adds the column and the key in one `ALTER TABLE`. That is how a surrogate `id` gets added to a pivot table that had none:

```sql
-- MySQL / MariaDB
ALTER TABLE `admin_user_roles` ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)
-- PostgreSQL
ALTER TABLE "admin_user_roles" ADD COLUMN "id" SERIAL, ADD PRIMARY KEY ("id")
```

The columns of a composite key all go into the same statement, followed by one `ADD PRIMARY KEY (a, b)`. One statement means a failure never leaves a key column without its key. The down migration drops the added columns, which drops the key with them.

Existing rows need a distinct value in the new key column:

- **Auto-increment** numbers the existing rows (1, 2, ...).
- **A per-row expression default**, such as `gen_random_uuid()` on PostgreSQL or `UUID()` on MariaDB, gives each row its own value. MySQL 8 with binary logging on (its default) refuses any `ADD COLUMN` whose default is non-deterministic, key or not, with error 1674. On MySQL, add a UUID key to a table with rows by hand.
- **No default** works on an empty table only. With rows, MySQL fills the column with the type's implicit default (`0`, `''`), and PostgreSQL with `NULL`, so adding the key fails and the table is left unchanged.

These primary key changes still need a migration written by hand, and generating SQL for them throws a `MigrationException`:

- **Adding key columns to a table that already has a primary key.** A table has one primary key, and the diff never replaces it, even when the same change drops the current key's columns. The exception names the table, the current key columns and the new ones.
- **Dropping some, but not all, of the columns of a composite key.** PostgreSQL would drop the whole key and MySQL would shrink it, so the diff refuses either way. Dropping every key column is fine: the table is then left without a primary key.
- **Making an existing column part of the key, or taking it out.** See [Column Changes](#column-changes).

### Unique Columns on Existing Tables

`#[Column(unique: true)]` is applied through the index diff, on both drivers. A new table or a new column gets the unique index inline (`UNIQUE` in `CREATE TABLE` or `ADD COLUMN`). On a column that already exists:

- **Adding `unique: true`** creates a unique index named `<table>_<column>_unique` (shortened when longer than 63 bytes, see [Index and Foreign Key Names](#index-and-foreign-key-names)):

  ```sql
  CREATE UNIQUE INDEX "users_email_unique" ON "users" ("email")
  ```

  The down migration drops it. The migration fails if the column already holds duplicate values, so remove them first.
- **Removing `unique: true`** drops the column's unique index (on PostgreSQL, the unique constraint an inline `UNIQUE` created, with `DROP CONSTRAINT`). The down migration puts it back.
- **The index is matched by its column, not its name.** Any single-column, non-partial unique index on the column counts, whatever created it (`email` from MySQL's inline `UNIQUE`, `users_email_key` from PostgreSQL's), so an existing unique column diffs as empty and is never renamed or rebuilt.
- **A foreign key column keeps an index.** When a foreign key column stops being unique and no other index starts with it, the diff adds a plain `<table>_<column>_index` before dropping the unique one, because MySQL refuses to drop the last index a foreign key uses.

A column whose only difference is uniqueness is never modified, so the column diff and the index diff never both act on it.

When the diff reports a change to a table but the SQL generator produces no statement for it in either direction, migration generation stops with a `MigrationException` naming the table and the reported changes, instead of writing an empty `alter_*` migration. It means the entity and the driver describe the column differently; report it.

### Index and Foreign Key Names

Index and constraint names are limited to 63 bytes, the lower of the two drivers' limits: PostgreSQL silently truncates a longer name to 63 bytes, and MySQL rejects names over 64 characters. The limit is in bytes, so a multibyte character counts more than once.

Marko derives three names from the table and column:

| Name | Used for |
|---|---|
| `fk_<table>_<column>` | The foreign key of every `references:` column |
| `<table>_<column>_unique` | The unique index added when an existing column becomes `unique: true` |
| `<table>_<column>_index` | The plain index kept on a foreign key column whose unique index is dropped |

A derived name that fits in 63 bytes is used as is. A longer one keeps its prefix and suffix, cuts the middle, and adds the 8-character crc32b hash of the full name:

```
customer_subscription_events_external_billing_reference_id_unique   (65 bytes)
customer_subscription_events_external_billing_r_5e024629_unique      (63 bytes)
```

The shortened name is the same on every run and on both drivers, and two long names that start the same still get different hashes. `ignore_indexes` and `unmanagedIndexes` patterns see the shortened name, so match against that.

Names you declare with `#[Index]` are never shortened, since renaming your identifier behind your back would hide what the database holds. A declared name over 63 bytes throws `EntityException` when the schema is built (`db:diff`, `db:migrate`), naming the entity, the index and its length in bytes. Give the index a shorter name. If PostgreSQL already holds the truncated form of a name that is now rejected, the next migration after the rename drops the truncated index and creates the new one.

### Partial Indexes

Add `where:` to `#[Index]` to create a partial index. The predicate is raw SQL, copied into the `CREATE INDEX` statement:

```php title="app/catalog/Entity/Show.php"
#[Table('shows')]
#[Index('shows_live_idx', ['status'], where: "status = 'live'")]
class Show extends Entity
{
    // ...
}
```

On PostgreSQL this generates `CREATE INDEX "shows_live_idx" ON "shows" ("status") WHERE status = 'live'`, and introspection reads the predicate back, so the next diff is empty. MySQL has no partial indexes: generating SQL for an index with `where:` throws a `MigrationException` naming the index.

Indexes are matched by name. Changing an existing index's `where:` (or columns) does not alter it; give the changed index a new name so the old one is dropped and the new one created.

### Hand-Made Indexes

Indexes the entity cannot express (expression, GIN/GiST, covering indexes) belong in a hand-written migration. Tell the diff to leave them alone, per table:

```php title="app/catalog/Entity/Show.php"
#[Table('shows', unmanagedIndexes: ['shows_search_gin_idx'])]
class Show extends Entity
{
    // ...
}
```

or project-wide, in `config/database.php`:

```php title="config/database.php"
return [
    // ...driver, host, port, database, username, password
    'migrations' => [
        'ignore_indexes' => ['shows_search_gin_idx', '*_trgm_idx'],
    ],
];
```

Both lists accept exact names and `fnmatch()` patterns, matched against the index name the database holds (for a derived name over 63 bytes, its [shortened form](#index-and-foreign-key-names)). A listed index is never dropped. An entity extender may declare `unmanagedIndexes` too; its list merges into the parent table's. `ignore_indexes` must be a list of strings, or `ConfigurationException` is thrown.

### Development Workflow

```bash
# 1. Define/modify your entity
# 2. Preview what will change
marko db:diff

# 3. Generate migration and apply it
marko db:migrate

# 4. If mistake, rollback (development and testing; elsewhere needs --force, never production)
marko db:rollback
```

### Production Workflow

```bash
# Deploy code (includes migration files)
# Apply existing migrations only
marko db:migrate
```

In production, `db:migrate` only applies existing migration files — it never generates new ones unless you pass `--generate`.

## Switching Database Drivers

Since entities are the single source of truth, switching between database systems is a config change — each driver's `SqlGenerator` translates entity attributes to native SQL automatically.

### Example: MySQL to PostgreSQL

1. Delete the migration files in `database/migrations/` — they contain MySQL-specific SQL:

```bash
rm database/migrations/*.php
```

2. Swap drivers:

```bash
composer remove marko/database-mysql
composer require marko/database-pgsql
```

3. Update your database config:

```php title="config/database.php"
return [
    'driver' => 'pgsql',
    'host' => '127.0.0.1',
    'port' => 5432,
    'database' => 'myapp',
    'username' => 'postgres',
    'password' => '',
];
```

4. Create the database and run migrations:

```bash
createdb myapp
marko db:migrate
marko db:seed
```

`db:migrate` diffs entity attributes against the empty database, generates new migration files with PostgreSQL-native SQL (e.g., `SERIAL` instead of `AUTO_INCREMENT`, `BOOLEAN` instead of `TINYINT(1)`), and applies them. Your entity code and application logic remain unchanged.

## Framework Comparison

| Feature | Laravel | Doctrine | Marko |
|---------|---------|----------|-------|
| Schema definition | Separate migration files | XML/YAML or attributes | Entity attributes (single source of truth) |
| Migration generation | Manual | `doctrine:schema:update` | `db:migrate` auto-generates |
| Entity persistence | Active Record (Eloquent) | Data Mapper | Data Mapper |
| Schema location | `database/migrations/` | Mapping files or entity | Entity only |

## Benefits of Entity as Single Source of Truth

1. **No schema drift** — Entity changes automatically sync to database
2. **Refactoring updates both** — Rename a property, schema updates automatically
3. **IDE support** — Full autocomplete and type checking for schema
4. **No context switching** — Everything about your model in one place
5. **Reduced cognitive load** — One file to understand, not entity + migration + mapping

## Wire-compatible database variants

Some databases speak an existing wire protocol (PostgreSQL or MySQL) but require different SQL dialect logic. CockroachDB, for example, accepts PostgreSQL connections but has its own DDL, introspection queries, and query-builder behaviour. A variant package can reuse the parent driver's connection and override only the four dialect interfaces.

### The 6-binding split

Every driver package binds six interfaces. They fall into two categories:

| Interface | Category | Role |
|-----------|----------|------|
| `ConnectionInterface` | **Wire** | PDO connection, DSN format, PostgreSQL/MySQL protocol; exposes `driverName(): string` (e.g. `'mysql'`, `'pgsql'`) so dialect-aware code can branch without a live connection, `supportsReturning(): bool` so the repository knows whether it can read generated keys back with `INSERT ... RETURNING` (a driver may ask the server once to answer it, as the MySQL driver does to tell MariaDB 10.5+ from MySQL), and `quoteIdentifier(string $identifier): string`, which the repository, `DataMigration` and `DatabaseTestHelper` quote every table and column name through (see [Reserved Words and Mixed Case](#reserved-words-and-mixed-case)) |
| `ConnectionFactoryInterface` | **Wire** | Creates `ConnectionInterface` instances from a `DatabaseConfig` |
| `SqlGeneratorInterface` | Dialect | DDL generation for schema diffs |
| `IntrospectorInterface` | Dialect | Reading existing schema from `information_schema` etc. Implement `ExpressionDefaultMatcherInterface` on it too so the diff can settle expression defaults the database rewrites (see [Column Defaults](#column-defaults)) |
| `QueryBuilderInterface` | Dialect | SELECT/INSERT/UPDATE/DELETE SQL generation |
| `QueryBuilderFactoryInterface` | Dialect | Constructs query builder instances |

A wire-compatible variant inherits the parent's `ConnectionInterface` and `ConnectionFactoryInterface` bindings unchanged and overrides the four dialect interfaces.

### Building a query builder

`Marko\Database\Query\SqlQueryBuilder` is an abstract class that holds the dialect-neutral query compilation. The MySQL, PostgreSQL and SQLite builders all extend it, so a new driver or variant can too. A subclass supplies only the dialect parts: `quoteIdentifier()`, `insertAndReturnKey()`, the JSON compilers (`compileJsonPath()`, `compileJsonContains()`, `compileJsonPathExists()`), `buildUpsertConflictClause()` and `compileLockClause()`. It may also override `hashStartsComment()`, `offsetOnlyLimitClause()`, `wrapUnionPart()` and `buildLimitOffsetClause()` where the dialect differs.

### Implementing `ConnectionInterface`

A class that implements `ConnectionInterface` itself (a new driver, a decorator or a test fake) must implement every method, including `quoteIdentifier()`, or PHP refuses to load it. Keep one quoting rule per driver and use it in the connection, the SQL generator and the query builder, as `MySqlIdentifier` and `PgSqlIdentifier` do. `quoteIdentifier()` must:

- wrap the name in the dialect's identifier delimiter and double any delimiter inside it, so no name can break out
- quote each part of a `table.column` name separately
- work without a live connection, like `driverName()`

`supportsReturning()` may connect: the repository calls it just before it runs the `INSERT`, so a driver whose answer depends on the server can ask the server once and keep the answer.

A decorator delegates to the connection it wraps, as `ReadWriteConnection` delegates to its write connection.

`quoteIdentifier()` is the only place a package's raw SQL gets its quoting rule. The search driver, the database queue and failed-job store, the session handler, both notification packages, the migrations table, `TruncateDatabase` and the admin-auth repositories all call it on the connection they were given, so a new driver or decorator only has to answer it once for all of them.

### CockroachDB example

The following shows the complete wiring for a hypothetical third-party `acme/database-cockroachdb` package. The `acme/` vendor and class names are illustrative — the `marko/` namespace is reserved for core Marko packages, so variant packages must ship under their own vendor namespace.

**`composer.json`** — require the parent pgsql package (which transitively requires `marko/database`):

```json title="composer.json"
{
    "name": "acme/database-cockroachdb",
    "description": "CockroachDB variant for Marko (PostgreSQL wire protocol)",
    "type": "marko-module",
    "require": {
        "marko/database-pgsql": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "Acme\\Database\\CockroachDb\\": "src/"
        }
    }
}
```

**`module.php`** — no static `bindings` for the dialect interfaces; a `boot` closure rebinds them after `marko/database-pgsql` has registered its own static bindings:

```php title="module.php"
<?php

declare(strict_types=1);

use Acme\Database\CockroachDb\Diff\CockroachDbGenerator;
use Acme\Database\CockroachDb\Introspection\CockroachDbIntrospector;
use Acme\Database\CockroachDb\Query\CockroachDbQueryBuilder;
use Acme\Database\CockroachDb\Query\CockroachDbQueryBuilderFactory;
use Marko\Core\Container\Container;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;

// ConnectionInterface is intentionally omitted: CockroachDB speaks the
// PostgreSQL wire protocol, so PgSqlConnection from marko/database-pgsql
// connects and authenticates without modification.

return [
    'boot' => function (Container $container): void {
        $container->bind(SqlGeneratorInterface::class, CockroachDbGenerator::class);
        $container->bind(IntrospectorInterface::class, CockroachDbIntrospector::class);
        $container->bind(QueryBuilderInterface::class, CockroachDbQueryBuilder::class);
        $container->bind(QueryBuilderFactoryInterface::class, CockroachDbQueryBuilderFactory::class);
    },
];
```

Because `acme/database-cockroachdb` requires `marko/database-pgsql` in its `composer.json`, Marko automatically boots the variant after the parent — no `sequence` configuration is needed.

For the underlying `boot` callback mechanism, see [Overriding another module's bindings](/docs/concepts/dependency-injection/#overriding-another-modules-bindings).

## Available Drivers

- [marko/database-pgsql](/docs/packages/database-pgsql/) — PostgreSQL driver
- [marko/database-mysql](/docs/packages/database-mysql/) — MySQL driver
- [marko/database-sqlite](/docs/packages/database-sqlite/) — SQLite driver

## Read/Write Splitting

To route reads to replicas and writes to a primary, see [marko/database-readwrite](/docs/packages/database-readwrite/). It wraps any existing driver connection using the decorator pattern — no changes to application code are required.

A read that must never see replica lag runs inside `onPrimary()` from `Marko\Database\Connection\PrimaryReadInterface`, which connections that route reads to replicas implement. See [Reads That Must Hit the Primary](/docs/packages/database-readwrite/#reads-that-must-hit-the-primary).
