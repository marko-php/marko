# Task 002: MySqlQueryBuilder — Implement `selectRaw` and `whereRaw`

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Implement `selectRaw` and `whereRaw` in `MySqlQueryBuilder`. Both methods append to internal state (raw-select expressions list, raw-where expressions list with their bindings), and the SQL-compilation step weaves them into the emitted SQL with correct binding order. A NEW private denylist helper rejects `;`, `--`, `/*`, `*/`, and backticks. (There is no pre-existing `orderByRaw` to refactor — that method does not exist in the codebase. The new helper is genuinely new.)

The aggregate code path (`runAggregate`, used by `count`/`min`/`max`/`sum`/`avg`) must also honor `whereRaw` conditions, since aggregates build their own SELECT and bypass `buildSelectSql()`. `selectRaw` is documented as ignored by aggregates (their SELECT list is fully determined by the aggregate function).

## Context

### Files to edit
- `packages/database-mysql/src/Query/MySqlQueryBuilder.php` (read first — 881 lines, `class MySqlQueryBuilder implements QueryBuilderInterface`).
- `packages/database-mysql/tests/Query/MySqlQueryBuilderTest.php` (read first — uses SQLite-backed PDO via `createPdo()` override for real query-result assertions).

### Verified file landmarks (read these before coding)
- `having()` at line 269 — uses inline denylist `;`, `--`, `/*`, `*/` (no backtick). DO NOT touch this method.
- `orderBy()` at line 341 — followed directly by `limit()` at 358. There is NO `orderByRaw`. The plan's earlier draft incorrectly claimed otherwise.
- `get()` at line 374 — resets `$this->bindings = []` at line 380, THEN calls `buildSelectSql()`. This is important for the `selectRaw` bindings insertion site (see below).
- `runAggregate()` at line 533 — resets `$this->bindings = []`, builds its own SELECT, then concatenates `buildWhereClause()`. This is the path that must be updated to honor `whereRaw`.
- `buildSelectSql()` at line 665 — does NOT reset `$this->bindings`. Calls `buildJoinClause()`, `buildWhereClause()`, `buildGroupByClause()`, `buildHavingClause()`, `buildOrderByClause()`, `buildLimitOffsetClause()` in that order.
- `buildWhereClause()` at line 740 — iterates `$this->wheres`, `$this->whereIns`, `$this->whereNulls`, `$this->whereNotNulls`, `$this->whereJsonContains`, `$this->whereJsonPaths` and appends to `$this->bindings`. THIS is where the new `$this->rawWheres` iteration must be added (in call order, after the other where types, AND-combined with the running condition list).
- `compileSubquery()` at line 151 — saves bindings, resets, calls `buildSelectSql()`, captures bindings, merges into the outer bindings, restores. `selectRaw`/`whereRaw` bindings flow through this correctly as long as `buildSelectSql()`/`buildWhereClause()` handle them.

### New state fields on the builder

```php
/** @var list<string> */
private array $rawSelects = [];

/** @var list<mixed> */
private array $rawSelectBindings = [];

/** @var list<array{expression: string, bindings: array}> */
private array $rawWheres = [];
```

### Method implementations

```php
public function selectRaw(string $expression, array $bindings = []): static
{
    $this->assertNoDangerousPatterns($expression);
    $this->rawSelects[] = $expression;
    array_push($this->rawSelectBindings, ...$bindings);
    return $this;
}

public function whereRaw(string $expression, array $bindings = []): static
{
    $this->assertNoDangerousPatterns($expression);
    $this->rawWheres[] = ['expression' => $expression, 'bindings' => $bindings];
    return $this;
}
```

### New private denylist helper

```php
/**
 * @throws InvalidColumnException
 */
private function assertNoDangerousPatterns(string $expression): void
{
    if (
        str_contains($expression, ';')
        || str_contains($expression, '--')
        || str_contains($expression, '/*')
        || str_contains($expression, '*/')
        || str_contains($expression, '`')
    ) {
        throw InvalidColumnException::invalidColumn($expression);
    }
}
```

(This is a NEW private method. There is no pre-existing inline denylist outside of `having()` to refactor. `having()` is intentionally left alone — see `_plan.md` Risks & Mitigations.)

### SELECT compilation — exact insertion site (MySql-specific)

`MySqlQueryBuilder::buildSelectSql()` (line 665) does NOT reset `$this->bindings`. The caller (`get()` at line 380, `compileSubquery()` at 154–155) handles the reset. Therefore:

- At the top of `buildSelectSql()` (after the `$quotedColumns` array is built but BEFORE `$sql .= $this->buildWhereClause()`), append `$this->rawSelects` items to the SELECT-list rendering.
- ALSO at the top of `buildSelectSql()` (before `buildWhereClause()` runs), append `$this->rawSelectBindings` to `$this->bindings`. This places SELECT bindings before WHERE bindings in the final array.

Concretely the rendering changes around line 667–682:

```php
$quotedColumns = array_map(function (string $col): string {
    if ($col === '*') {
        return '*';
    }
    return $this->compileColumnExpression($col);
}, $this->columns);

// NEW: append raw select expressions verbatim
$selectParts = array_merge($quotedColumns, $this->rawSelects);

// NEW: append raw-select bindings to the bindings stream BEFORE WHERE compiles
foreach ($this->rawSelectBindings as $binding) {
    $this->bindings[] = $binding;
}

$keyword = $this->distinct ? 'SELECT DISTINCT' : 'SELECT';

$sql = sprintf(
    '%s %s FROM %s',
    $keyword,
    implode(', ', $selectParts),
    $this->quoteIdentifier($this->table),
);
```

### WHERE compilation — extend `buildWhereClause()`

After the existing `whereJsonPaths` loop (around line 820) and BEFORE the empty-conditions early-return, add a new loop iterating `$this->rawWheres`. Each item appends `$item['expression']` as a condition (prefixed with `AND ` if `$conditions` is non-empty) and appends `$item['bindings']` to `$this->bindings`.

### Aggregate path — extend `runAggregate()`

`runAggregate()` (line 533) currently does:
```php
$this->bindings = [];
$sql = sprintf('SELECT %s FROM %s', $aggregateExpr, $this->quoteIdentifier($this->table));
$sql .= $this->buildWhereClause();
```

Since `buildWhereClause()` is the shared method, and the change above adds `rawWheres` handling INSIDE `buildWhereClause()`, aggregates automatically honor `whereRaw`. **Verify** that no extra change is needed in `runAggregate()` itself — but add an explicit test (see below) to lock this in. (`selectRaw` is correctly ignored because `runAggregate()` builds its own SELECT and never reads `$this->rawSelects`.)

### Repeated `get()` calls must produce identical bindings
Critical: `$this->rawSelectBindings` and `$this->rawWheres` MUST NOT be mutated during compile. The compile step reads them and appends to `$this->bindings` (which IS reset between calls). Re-running `->get()` on the same builder twice must produce identical SQL and bindings.

## Requirements (Test Descriptions)

### selectRaw — SELECT compilation
- [ ] `selectRaw appends the expression to the SELECT list after regular columns`
- [ ] `selectRaw alone (no prior select call) emits "SELECT *, <expression>" preserving the default *`
- [ ] `selectRaw can be called multiple times; expressions appear in call order in the SELECT list`
- [ ] `selectRaw together with select() emits select() columns first then selectRaw expressions`

### selectRaw — bindings position
- [ ] `selectRaw with bindings places the bindings BEFORE WHERE bindings in the compiled bindings array (assert against a captured stub-connection bindings array)`
- [ ] `selectRaw bindings from multiple calls concatenate in call order`
- [ ] `running ->get() twice on the same builder produces identical SQL and bindings (no mutation of internal raw state)`

### selectRaw — denylist
- [ ] `selectRaw throws InvalidColumnException when the expression contains a semicolon`
- [ ] `selectRaw throws InvalidColumnException when the expression contains a -- comment marker`
- [ ] `selectRaw throws InvalidColumnException when the expression contains a /* or */ block-comment marker`
- [ ] `selectRaw throws InvalidColumnException when the expression contains a backtick`
- [ ] `selectRaw returns the builder for fluent chaining`

### whereRaw — WHERE compilation
- [ ] `whereRaw appends the expression to the WHERE clause AND-combined with other conditions`
- [ ] `whereRaw used alone (no prior where) emits "WHERE <expression>" with no leading AND`
- [ ] `whereRaw can be called multiple times; expressions appear in call order, AND-combined`
- [ ] `whereRaw together with where() emits the regular where condition first then the raw expression, AND-combined`

### whereRaw — bindings position
- [ ] `whereRaw with bindings places its bindings in the WHERE position of the bindings array (after selectRaw bindings, after regular where bindings if both exist)`
- [ ] `whereRaw bindings from multiple calls concatenate in call order`

### whereRaw — aggregate path
- [ ] `count() honors whereRaw conditions (filtered count, not full-table count)`
- [ ] `min() / max() / sum() / avg() honor whereRaw conditions`

### whereRaw — denylist
- [ ] `whereRaw throws InvalidColumnException when the expression contains a semicolon`
- [ ] `whereRaw throws InvalidColumnException when the expression contains a -- comment marker`
- [ ] `whereRaw throws InvalidColumnException when the expression contains a /* or */ block-comment marker`
- [ ] `whereRaw throws InvalidColumnException when the expression contains a backtick`
- [ ] `whereRaw returns the builder for fluent chaining`

### Combined selectRaw + whereRaw
- [ ] `selectRaw and whereRaw used together produce bindings in [select-bindings..., where-bindings...] order in the final bindings array`
- [ ] `selectRaw and whereRaw flow correctly through compileSubquery() when this builder is used as a UNION right-hand side`

## Acceptance Criteria
- All requirements have passing tests.
- The new private `assertNoDangerousPatterns()` helper is referenced by `selectRaw()` and `whereRaw()` only — `having()` is NOT modified.
- Bindings inspection in tests uses a stub `ConnectionInterface` that records the `(sql, bindings)` from `query()`/`execute()`, OR uses real SQLite query results to verify behavior end-to-end. Either approach is acceptable; pick whichever matches the existing file's pattern.
- `composer test` for `packages/database-mysql` is green.
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
