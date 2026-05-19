# Task 005: Cross-Driver Consistency Tests

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Add a Pest test that runs the same fixture input through `MySqlQueryBuilder` and `PgSqlQueryBuilder` for both `selectRaw` and `whereRaw`, then asserts:
1. The SQL skeleton matches the documented shape (modulo identifier quoting which is naturally driver-specific: `` ` `` vs `"`).
2. The final bindings array is identical across both drivers — SELECT-raw bindings come first, then WHERE-clause bindings (regular `where()` + `whereRaw` in call order).

This is the lockdown against silent driver drift, per the plan's "Risks & Mitigations".

## Context

### Test file location

**`tests/Integration/QueryBuilderRawConsistencyTest.php`** at the marko monorepo root (not inside `packages/database/tests/` — that package does not depend on either driver, so cross-driver tests cannot live there without inverting the dependency direction).

The monorepo `phpunit.xml` defines a "Monorepo" testsuite at `<directory>tests</directory>`, so any file under the top-level `tests/` directory is auto-discovered by `composer test`. Create the `tests/Integration/` subdirectory if it does not already exist.

The monorepo `composer.json` already requires both `marko/database-mysql` and `marko/database-pgsql` at `self.version`, so both driver classes are autoloadable from the root.

### Recording stub `ConnectionInterface`

To inspect the bindings array (which is `private` on both builders), the test uses a stub `ConnectionInterface` that records the `(sql, bindings)` pair from each `query()`/`execute()` call. Pattern:

```php
function createRecordingConnection(): object
{
    return new class implements ConnectionInterface
    {
        public ?string $lastSql = null;
        /** @var array<int, mixed> */
        public array $lastBindings = [];

        public function query(string $sql, array $bindings = []): array
        {
            $this->lastSql = $sql;
            $this->lastBindings = $bindings;
            return []; // empty result — we only care about the captured pair
        }

        public function execute(string $sql, array $bindings = []): int
        {
            $this->lastSql = $sql;
            $this->lastBindings = $bindings;
            return 0;
        }

        // ... other ConnectionInterface methods: stub as needed (lastInsertId, connect, etc.)
    };
}
```

(Read `packages/database/src/Connection/ConnectionInterface.php` first to enumerate every method that must be implemented — missing methods = PHP fatal at test load.)

### Fixture pattern

```php
function applyFixture(QueryBuilderInterface $builder): void
{
    $builder
        ->table('products')
        ->select('id')
        ->selectRaw('COALESCE(?, ?) AS resolved', ['a', 'b'])
        ->where('active', '=', true)
        ->whereRaw('price > ?', [100]);
}
// Expected bindings (both drivers): ['a', 'b', true, 100]
```

After applying the fixture and calling `->get()`, read `$connection->lastBindings` and assert against `['a', 'b', true, 100]` for BOTH drivers.

### SQL skeleton assertion

The emitted SQL differs between drivers in identifier quoting only:
- MySql: `` SELECT `id`, COALESCE(?, ?) AS resolved FROM `products` WHERE `active` = ? AND price > ? ``
- PgSql: `SELECT "id", COALESCE(?, ?) AS resolved FROM "products" WHERE "active" = ? AND price > ?`

Strategy: normalize both SQLs by stripping `` ` `` and `"` characters before comparing. Or assert on substrings (e.g. `str_contains($sql, 'COALESCE(?, ?) AS resolved')`, `str_contains($sql, 'price > ?')`, `str_contains($sql, 'AND')`).

### Do NOT touch `RepositoryQueryBuilder` in this task
That's task 004's territory. This test exercises the two driver builders directly.

## Requirements (Test Descriptions)

- [ ] `MySqlQueryBuilder and PgSqlQueryBuilder produce identical bindings array for the selectRaw + where + whereRaw fixture`
- [ ] `the bindings array order is exactly [select-raw-bindings..., where-bindings..., where-raw-bindings...] in both drivers`
- [ ] `both drivers include the raw select expression in the SELECT list, after the regular columns`
- [ ] `both drivers AND-combine the raw where expression with the regular where condition`
- [ ] `both drivers throw InvalidColumnException for each denylist input (semicolon, --, /*, */, backtick) — parameterized via Pest's it(...)->with([...])`
- [ ] `the emitted SQL contains the same SELECT-list ordering across both drivers (raw expression appended after regular columns)`
- [ ] `the emitted SQL contains the same WHERE-clause ordering across both drivers (regular where first, then whereRaw, AND-combined)`

## Acceptance Criteria
- All requirements have passing tests.
- Test file lives at `tests/Integration/QueryBuilderRawConsistencyTest.php`.
- Test does NOT depend on a real database connection — uses the recording stub above.
- Denylist test is parameterized (`it(...)->with([...])` pattern) to avoid copy-paste across 5 patterns × 2 methods × 2 drivers.
- `composer test` is fully green in the monorepo root.
- The test is in the default suite (NOT tagged `integration-destructive`, since it uses stub connections — `composer test` excludes only `integration-destructive` per the root `composer.json`).
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
