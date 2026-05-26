# Task 003: PgSqlQueryBuilder — Implement `selectRaw` and `whereRaw`

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Mirror task 002 for `PgSqlQueryBuilder`. Per the sibling-modules rule (`.claude/sibling-modules.md`), method names, visibilities, parameter ordering, and internal-state-field names MUST match the MySql implementation exactly. The only legitimate driver-specific difference here is the `selectRaw` bindings insertion site — `PgSqlQueryBuilder::buildSelectSql()` resets `$this->bindings = []` at its top (line 662), whereas MySql's `buildSelectSql()` does not. This is pre-existing sibling drift, not something this plan introduces; the workaround is documented below.

The aggregate code path (`runAggregate` or equivalent) must also honor `whereRaw` conditions — same as task 002.

## Context

### Files to edit
- `packages/database-pgsql/src/Query/PgSqlQueryBuilder.php` (read first — 886 lines, sibling of `MySqlQueryBuilder`).
- `packages/database-pgsql/tests/Query/PgSqlQueryBuilderTest.php` (read first to understand the existing test pattern).

### Verified file landmarks (read these before coding)
- `having()` at line 268 — same denylist as MySql's `having()`: `;`, `--`, `/*`, `*/` (no backtick). DO NOT touch this method.
- `orderBy()` at line 340 — there is NO `orderByRaw`. The plan's earlier draft incorrectly claimed otherwise.
- `buildSelectSql()` at line 660 — **resets `$this->bindings = []` at line 662** (unlike MySql). Calls `buildJoinClause()`, `buildWhereClause()`, etc. in the same order as MySql.
- `buildHavingClause()` at line 707 — appends `havingClause['bindings']` to `$this->bindings`. Same shape as MySql.
- `buildWhereClause()` — same structure as MySql; iterates `wheres`, `whereIns`, `whereNulls`, etc. Find the equivalent insertion point for the new `rawWheres` loop.
- Use SQL identifier quoting via `"` (Postgres) — not backticks. But raw expressions are passed through verbatim so no quote-aware compilation is needed.

### Sibling-module rule

State fields, method bodies, and the new private denylist helper must match `MySqlQueryBuilder` line-for-line (same names, same shape, same visibility). Tests should be near-identical mirrors of the MySql tests — same fixture inputs, same expectations modulo Postgres identifier quoting (`"` vs `` ` ``).

### Per-driver `selectRaw` bindings insertion site (PgSql-specific)

PgSql's `buildSelectSql()` already resets `$this->bindings = []` at line 662. Therefore in PgSql:

- Insert `$this->rawSelectBindings` into `$this->bindings` AFTER the reset at line 662, BEFORE `buildWhereClause()` is called.
- ALSO append `$this->rawSelects` to the SELECT-list rendering at the same point where the regular `$this->columns` are rendered.

```php
private function buildSelectSql(): string
{
    $this->bindings = [];

    // NEW: append raw-select bindings BEFORE WHERE compiles
    foreach ($this->rawSelectBindings as $binding) {
        $this->bindings[] = $binding;
    }

    $regularColumns = $this->columns[0] === '*'
        ? ['*']
        : array_map(
            fn (string $col): string => $this->compileColumnExpression($col),
            $this->columns,
        );

    // NEW: append raw select expressions verbatim
    $selectParts = array_merge($regularColumns, $this->rawSelects);
    $columns = implode(', ', $selectParts);

    // ... rest unchanged
}
```

### Aggregate path (`runAggregate` or equivalent)

Same as task 002: identify the aggregate code path in PgSql. If it calls `buildWhereClause()`, and `buildWhereClause()` is extended to handle `rawWheres`, then aggregates automatically honor `whereRaw` with no further change. If PgSql's aggregate path is structured differently, add the equivalent handling.

### **Important**: do NOT diverge from MySql in observable behavior

The two drivers may have slightly different insertion sites internally (because of the `buildSelectSql()` reset drift), but their observable behavior — emitted SQL skeleton and final bindings array — MUST be identical for the same input. Task 005's cross-driver test enforces this. If the PgSql implementation seems to need observable divergence, that's a sign of a planning gap — flag it rather than silently diverging.

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
- The PgSql implementation is structurally a mirror of the MySql implementation — same state-field names, same method names, same visibilities, same denylist helper signature. The only internal difference is the `selectRaw` bindings insertion site (because of the pre-existing `buildSelectSql()` reset drift in PgSql).
- `having()` is NOT modified.
- `composer test` for `packages/database-pgsql` is green.
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
