---
title: marko/validation-database
description: Entity-backed Exists and Unique validation rules — look records up by entity class and property through marko/database.
---

Entity-backed Exists and Unique validation rules --- look records up by entity class and property through [`marko/database`](/docs/packages/database/). The package implements `RecordLookupInterface` from [`marko/validation`](/docs/packages/validation/), so the `Exists` and `Unique` rules that `RecordRules` builds can check a foreign-key id or the uniqueness of an email against the database. Rules name entity properties, never tables or columns: the lookup reads the table and every column from the entity's metadata.

## Installation

```bash
composer require marko/validation-database
```

Requires [`marko/database`](/docs/packages/database/) and one of its drivers ([`marko/database-pgsql`](/docs/packages/database-pgsql/) or [`marko/database-mysql`](/docs/packages/database-mysql/)). There is no configuration: installing the package binds `RecordLookupInterface` to `EntityRecordLookup`.

## Usage

Inject `RecordRules` and pass its rules in an array, next to string rules:

```php
use App\Blog\Entity\Channel;
use App\Blog\Entity\User;
use Marko\Validation\Contracts\ValidatorInterface;
use Marko\Validation\Rules\RecordRules;

public function __construct(
    private readonly ValidatorInterface $validator,
    private readonly RecordRules $recordRules,
) {}

public function store(array $input): void
{
    $this->validator->validateOrFail($input, [
        'channel_id' => ['required', $this->recordRules->exists(Channel::class)],
        'slug' => ['required', $this->recordRules->unique(Channel::class, 'slug')->where('tenantId', 3)],
        'email' => ['required', 'email', $this->recordRules->unique(User::class, 'email')->caseInsensitive()],
    ]);
}
```

On update, leave the record itself out with `ignore()`, so a user keeps their own email:

```php
$this->recordRules->unique(User::class, 'email')->ignore($user->id);
```

The rules API (`exists()`, `unique()`, `where()`, `ignore()`, `caseInsensitive()`) is documented on the [`marko/validation`](/docs/packages/validation/#exists-and-unique) page.

### How the Lookup Works

Each check runs one probe, with every identifier quoted by the connection:

```sql
SELECT 1 FROM "users" WHERE "email_address" = ? AND "tenant_ref" = ? AND "id" <> ? LIMIT 1
```

- **Properties, not columns.** `unique(User::class, 'email')` on a property declared `#[Column(name: 'email_address')]` queries `email_address`. The same goes for `where()` properties and the primary key that `ignore()` compares.
- **Values go through the entity's casts.** A `BackedEnum` in `where('status', Status::Active)` is compared by its backing value, and an integer string such as `"12"` from a form matches an `int` property.
- **A value a numeric column cannot hold matches nothing.** `exists()` fails for `"abc"` or `"1abc"` on an `int` property without a query, instead of relying on MySQL casting `"1abc"` to `1`. An `ignore()` id the primary key cannot hold leaves nothing out.
- **`null` in `where()` matches `NULL`** (`IS NULL`).
- The same SQL runs on PostgreSQL, MySQL and MariaDB. There is no driver-specific code.

### Case Sensitivity

Without `caseInsensitive()`, text is compared by the column's collation, and that differs between databases (see [#341](https://github.com/marko-php/marko/issues/341)):

| Database | Default comparison | `unique(User::class, 'email')` with `Ada@example.com` stored |
|----------|-------------------|---------------------------------------------------------------|
| MySQL / MariaDB | Case-insensitive (`utf8mb4_0900_ai_ci`, `utf8mb4_general_ci`, ...) | `ada@example.com` is taken |
| PostgreSQL | Exact | `ada@example.com` is free |

`caseInsensitive()` wraps both sides in `LOWER()`, so the result is the same on every database. It is the portable choice for emails and slugs. It works only on a `string` property; on any other type it throws `RecordLookupException`. On PostgreSQL, `LOWER(column)` cannot use a plain index on the column: add an expression index on `LOWER(column)` if the table is large.

### Unique Is Not a Guarantee

`Unique` is a usability check that gives the user a helpful message before saving. It cannot prevent a race: two requests can both pass validation before either one saves. Keep a unique index on the column (`#[Column(unique: true)]` or `#[Index(..., unique: true)]`), and handle the [`UniqueConstraintViolationException`](/docs/packages/database/) the repository throws when the second insert hits it.

## Errors

| Situation | Exception |
|-----------|-----------|
| The entity class does not exist | `RecordLookupException` naming the class |
| The class is not an entity | `EntityException` from the metadata factory |
| A property (in the rule or in `where()`) is not a `#[Column]` property | `RecordLookupException` listing the entity's column properties |
| A property is `#[Encrypted]` | `RecordLookupException` (encrypted values cannot be matched in SQL) |
| `caseInsensitive()` on a property that is not a `string` | `RecordLookupException` |
| `RecordRules` is resolved without this package | `NoDriverException` from `marko/validation`, naming this package |

All of them extend `MarkoException`, with a context and a suggestion.

## API Reference

### EntityRecordLookup

```php
class EntityRecordLookup implements RecordLookupInterface
{
    public function __construct(
        ConnectionInterface $connection,
        EntityMetadataFactory $entityMetadataFactory,
        EntityHydrator $entityHydrator,
    );
    public function exists(string $entityClass, string $property, mixed $value, array $where = []): bool;
    public function isTaken(
        string $entityClass,
        string $property,
        mixed $value,
        mixed $ignoreId = null,
        array $where = [],
        bool $caseInsensitive = false,
    ): bool;
}
```

### RecordLookupException

```php
class RecordLookupException extends MarkoException
{
    public static function unknownEntity(string $entityClass): self;
    public static function unknownProperty(string $entityClass, string $property, array $properties): self;
    public static function encryptedProperty(string $entityClass, string $property): self;
    public static function caseInsensitiveNeedsText(string $entityClass, string $property, string $type): self;
}
```

## Related Packages

- [`marko/validation`](/docs/packages/validation/) --- the `Exists` and `Unique` rules, `RecordRules` and `RecordLookupInterface`
- [`marko/database`](/docs/packages/database/) --- entities, metadata and connections
- [`marko/database-pgsql`](/docs/packages/database-pgsql/) and [`marko/database-mysql`](/docs/packages/database-mysql/) --- the database drivers
