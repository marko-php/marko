# Sibling Module Standards

Standards for creating consistent, coherent driver/implementation packages that share an interface.

## What Are Sibling Modules?

Sibling modules are packages that implement the same interface for different backends. They follow Marko's interface/implementation split pattern:

| Base Package | Sibling Implementations |
|--------------|------------------------|
| `marko/database` | `marko/database-mysql`, `marko/database-pgsql` |
| `marko/cache` | `marko/cache-file`, `marko/cache-redis` |
| `marko/view` | `marko/view-latte`, `marko/view-liquid` |
| `marko/queue` | `marko/queue-rabbitmq`, `marko/queue-sqs` |

## Core Principle

**Sibling modules must read as if written by the same person.** Users switching between drivers should find identical patterns, naming conventions, and code organization. The only differences should be driver-specific implementation details.

## Naming Conventions

### Package Names

Format: `marko/{base}-{driver}`

```
marko/database-mysql     ✓
marko/database-pgsql     ✓
marko/mysql-database     ✗ (driver before base)
marko/db-mysql           ✗ (abbreviated base)
```

### Class Names

Format: `{Driver}{Component}`

| Component | MySQL | PostgreSQL | Redis |
|-----------|-------|------------|-------|
| Connection | `MySqlConnection` | `PgSqlConnection` | `RedisConnection` |
| Statement | `MySqlStatement` | `PgSqlStatement` | - |
| QueryBuilder | `MySqlQueryBuilder` | `PgSqlQueryBuilder` | - |
| Generator | `MySqlGenerator` | `PgSqlGenerator` | - |

### Namespace Structure

```
Marko\Database\MySql\
    Connection\
        MySqlConnection.php
        MySqlStatement.php
    Query\
        MySqlQueryBuilder.php
    Exceptions\
        ConnectionException.php
```

All siblings use identical directory structures, just with different driver prefixes.

### Method Names

**Identical across siblings.** When a method exists in multiple drivers, use the exact same name:

```php
// CORRECT - identical method names
// MySqlConnection.php
private function ensureConnected(): void
protected function createPdo(...): PDO
public function getDsn(): string

// PgSqlConnection.php
private function ensureConnected(): void
protected function createPdo(...): PDO
public function getDsn(): string

// WRONG - inconsistent naming
// MySqlConnection.php
public function getDsn(): string

// PgSqlConnection.php
private function buildDsn(): string  // Different name and visibility
```

### Private Helper Methods

Even private methods should use consistent naming when they serve the same purpose:

```php
// CORRECT
private function buildWhereClause(): string   // Both drivers
private function buildJoinClause(): string    // Both drivers

// WRONG
private function buildWhereSql(): string      // MySQL
private function buildWhereClause(): string   // PostgreSQL
```

## Method Visibility

### Public API

All public methods must be identical across siblings (defined by the interface).

### Protected Methods

Use `protected` for methods that:
- Might need customization via Preferences
- Are useful for testing (like `createPdo()`)
- Are shared patterns that subclasses might override

```php
// Testability hook - protected so tests can override
protected function createPdo(
    string $dsn,
    string $username,
    string $password,
    array $options,
): PDO {
    return new PDO($dsn, $username, $password, $options);
}
```

### Private Methods

Use `private` for internal implementation details that:
- Are purely internal helpers
- Should not be overridden
- Are driver-specific optimizations

**Key rule:** If a method exists in multiple siblings, it should have the **same visibility** in all of them.

## Code Patterns

### Connection Pattern

All connection classes should implement:

```php
class {Driver}Connection implements ConnectionInterface
{
    private ?PDO $pdo = null;

    // Public DSN accessor
    public function getDsn(): string

    // Lazy connection - call this in all methods that need a connection
    private function ensureConnected(): void
    {
        if ($this->pdo === null) {
            $this->connect();
        }
    }

    // Testability hook - override in tests to inject mock PDO
    protected function createPdo(
        string $dsn,
        string $username,
        string $password,
        array $options,
    ): PDO {
        return new PDO($dsn, $username, $password, $options);
    }
}
```

### Query Builder Pattern

```php
class {Driver}QueryBuilder implements QueryBuilderInterface
{
    // Identifier quoting - protected, not public
    protected function quoteIdentifier(string $identifier): string

    // Clause builders - consistent naming
    private function buildSelectSql(): string
    private function buildWhereClause(): string
    private function buildJoinClause(): string
    private function buildOrderByClause(): string
    private function buildLimitOffsetClause(): string
}
```

### Common Aliases

When queries return computed values, use consistent aliases:

```php
// CORRECT - same alias in all drivers
'SELECT COUNT(*) as count FROM ...'
$result[0]['count']

// WRONG - different aliases
'SELECT COUNT(*) as aggregate FROM ...'  // MySQL
'SELECT COUNT(*) as count FROM ...'      // PostgreSQL
```

## PHPDoc Style

Use multi-line format for all property annotations:

```php
// CORRECT
/**
 * @var array<string>
 */
private array $columns = ['*'];

/**
 * @var array<array{column: string, operator: string, value: mixed}>
 */
private array $wheres = [];

// WRONG - inline format
/** @var array<string> */
private array $columns = ['*'];
```

## Class Modifiers

### Readonly Classes

If a class is immutable (all properties set at construction, never modified), use `readonly class`:

```php
// Both introspectors should be readonly
readonly class MySqlIntrospector implements IntrospectorInterface
readonly class PgSqlIntrospector implements IntrospectorInterface
```

**Key rule:** If one sibling uses `readonly class`, all siblings must use it.

## Test Structure

### Namespaces

All test files must have proper PSR-4 namespaces:

```php
// MySQL tests
namespace Marko\Database\MySql\Tests\Connection;
namespace Marko\Database\MySql\Tests\Query;

// PostgreSQL tests
namespace Marko\Database\PgSql\Tests\Connection;
namespace Marko\Database\PgSql\Tests\Query;
```

### Directory Structure

Mirror the source structure:

```
tests/
    Connection/
        {Driver}ConnectionTest.php
    Query/
        {Driver}QueryBuilderTest.php
    Sql/
        {Driver}GeneratorTest.php
    Feature/
        {Driver}IntegrationTest.php
```

### Testing Approach

Use anonymous classes with `createPdo()` override for connection testing:

```php
// CORRECT - anonymous class pattern
$connection = new class (...) extends PgSqlConnection
{
    protected function createPdo(...): PDO
    {
        return new PDO('sqlite::memory:');
    }
};

// WRONG - reflection-based injection
$reflection = new ReflectionClass($connection);
$pdoProperty = $reflection->getProperty('pdo');
$pdoProperty->setValue($connection, $mockPdo);
```

## Exception Messages

Use consistent message formats:

```php
// CORRECT - consistent format
"Failed to connect to MySQL database '$database' on $host:$port"
"Failed to connect to PostgreSQL database '$database' on $host:$port"

// WRONG - inconsistent prepositions
"Failed to connect to MySQL database '$database' at $host:$port"
"Failed to connect to PostgreSQL database '$database' on $host:$port"
```

## Planning for Future Siblings

### Single Implementation Now, Siblings Later

When building a package that will have siblings in the future but currently only has one implementation:

1. **Still follow sibling conventions.** Write the code as if siblings already exist.

2. **Use driver-specific naming.** Don't use generic names that will conflict:
   ```php
   // CORRECT - even with only MySQL initially
   class MySqlConnection implements ConnectionInterface

   // WRONG - will conflict when PostgreSQL is added
   class Connection implements ConnectionInterface
   ```

3. **Establish patterns explicitly.** Document the patterns in the first implementation so future siblings can follow them.

4. **Include driver in namespace:**
   ```php
   // CORRECT
   namespace Marko\Cache\Redis\Connection;

   // WRONG - no room for siblings
   namespace Marko\Cache\Connection;
   ```

### Checklist for First Sibling

When creating the first implementation of a future sibling set:

- [ ] Package named `marko/{base}-{driver}`
- [ ] Classes prefixed with driver name (`{Driver}{Component}`)
- [ ] Namespace includes driver (`Marko\{Base}\{Driver}\`)
- [ ] Protected methods for testability hooks
- [ ] Multi-line PHPDoc format
- [ ] Test namespaces include driver
- [ ] Anonymous class testing pattern (no reflection)
- [ ] Document any patterns specific to this module type

### Checklist for Adding Siblings

When adding a new sibling to an existing set:

- [ ] Study existing sibling thoroughly before writing code
- [ ] Match all method names exactly
- [ ] Match all method visibilities exactly
- [ ] Match PHPDoc format
- [ ] Match test structure and patterns
- [ ] Match exception message formats
- [ ] Match class modifiers (readonly, etc.)
- [ ] Run both packages' tests to verify compatibility

## Review Checklist

Before merging sibling module changes:

| Aspect | Check |
|--------|-------|
| Method names | Identical across all siblings |
| Method visibility | Identical for same-purpose methods |
| PHPDoc style | Multi-line format everywhere |
| Class modifiers | Consistent (readonly, etc.) |
| Test namespaces | Proper PSR-4 namespaces |
| Test approach | Anonymous class pattern |
| Exception messages | Consistent format |
| Aliases | Same names for computed values |

## Anti-Patterns

### Don't

- Use different method names for the same operation
- Mix public/protected/private visibility for equivalent methods
- Use reflection in tests when a protected hook exists
- Use inline PHPDoc in one sibling and multi-line in another
- Forget namespaces in test files
- Use generic class names that don't indicate the driver

### Do

- Establish conventions with the first sibling
- Document patterns explicitly
- Review existing siblings before adding new ones
- Run all sibling tests together after changes
- Treat consistency violations as bugs
