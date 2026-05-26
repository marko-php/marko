# Task 001: QueryBuilderInterface — Add `selectRaw` and `whereRaw` Declarations + Update All Stubs

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add `selectRaw` and `whereRaw` method declarations (signatures + PHPDoc) to `Marko\Database\Query\QueryBuilderInterface`. **Critical**: update every anonymous-class stub of `QueryBuilderInterface` (and the one `EntityQueryBuilderInterface` stub) in `packages/database/tests/` with no-op implementations so the test suite still loads after the interface changes. Add reflection-based contract tests in `QueryBuilderInterfaceTest.php` asserting the new methods are declared with the expected signatures.

This task introduces the contract only. Behavior implementations land in tasks 002 (MySql), 003 (PgSql), and 004 (Repository wrapper). Behavior tests for the denylist live in tasks 002 and 003 against real driver implementations — NOT in this task, because `QueryBuilderInterfaceTest.php` is reflection-only (no inline stub-impl pattern exists in the file today).

## Context

### Files to edit

**Interface itself:**
- `packages/database/src/Query/QueryBuilderInterface.php` (read first — the file ends at the `raw()` method declaration around line 376; the interface imports `Marko\Database\Exceptions\InvalidColumnException` at line 7 — no new imports needed).

**Interface contract tests:**
- `packages/database/tests/Query/QueryBuilderInterfaceTest.php` (read first — every existing test uses `new ReflectionClass(QueryBuilderInterface::class)` and reflection assertions. NO stub-impl pattern is used. Mirror that style exactly).

**Anonymous-class stubs that implement `QueryBuilderInterface` (every one MUST be updated with no-op `selectRaw` and `whereRaw` methods that return `$this`):**
- `packages/database/tests/Repository/RepositoryTest.php` (line ~1091)
- `packages/database/tests/Repository/RepositoryMatchingTest.php` (line ~50)
- `packages/database/tests/Repository/StringPrimaryKeyTest.php` (line ~153)
- `packages/database/tests/Repository/RepositoryWithTest.php` (lines ~104, ~551, ~800, ~1039 — four separate stubs in this one file)
- `packages/database/tests/Repository/RepositoryQueryBuilderEnhancedTest.php` (line ~78 — `makeRqbStubBuilder`)
- `packages/database/tests/Entity/RelationshipLoaderNestedTest.php` (line ~345)
- `packages/database/tests/Entity/RelationshipLoaderTest.php` (lines ~246, ~482)
- `packages/database/tests/Entity/RelationshipLoaderBelongsToManyTest.php` (lines ~200, ~448)
- `packages/database/tests/Entity/RelationshipValidationTest.php` (line ~49)
- `packages/database/tests/Query/QuerySpecificationTest.php` (lines ~37, ~255)
- `packages/database/tests/Query/SpecEagerLoadCompositionTest.php` (lines ~107, ~293) — plus an `EntityQueryBuilderInterface` stub at line ~565 that also needs both methods

Use `grep -rn 'implements QueryBuilderInterface' packages/database/tests/` to verify the full list is covered before submitting. Any missed stub causes a PHP fatal error at test load time (`Class contains 2 abstract methods and must therefore be declared abstract`).

### Method signatures

```php
public function selectRaw(
    string $expression,
    array $bindings = [],
): static;

public function whereRaw(
    string $expression,
    array $bindings = [],
): static;
```

### PHPDoc verbatim

```php
/**
 * Add a raw SQL expression to the SELECT list.
 *
 * The expression is appended after any columns added via select(). Include
 * "AS alias" in the expression if you need a column alias. If no select()
 * call precedes this, the default '*' column is preserved (emitting
 * `SELECT *, <expression>`).
 *
 * Security: $expression must not contain semicolons, SQL comment markers,
 * or backticks. Never interpolate user-supplied values directly — use ?
 * placeholders and pass values via $bindings.
 *
 * Note: aggregate methods (count, min, max, sum, avg) build their own
 * SELECT list and ignore selectRaw additions.
 *
 * @param string $expression Raw SQL select expression (e.g. "COALESCE(a, b) AS resolved")
 * @param array  $bindings   Positional bindings for ? placeholders in the expression
 * @return static For fluent chaining
 * @throws InvalidColumnException When the expression contains dangerous patterns
 */
public function selectRaw(
    string $expression,
    array $bindings = [],
): static;

/**
 * Add a raw SQL WHERE condition with optional positional bindings.
 *
 * The expression is AND-combined with any other where conditions, in call
 * order, after the regular where*/whereIn/whereNull/etc. conditions.
 *
 * Aggregate methods (count, min, max, sum, avg) honor whereRaw conditions
 * the same way they honor where().
 *
 * Security: $expression must not contain semicolons, SQL comment markers,
 * or backticks. Never interpolate user-supplied values directly — use ?
 * placeholders and pass values via $bindings.
 *
 * @param string $expression Raw SQL WHERE expression (e.g. "COALESCE(price, base) > ?")
 * @param array  $bindings   Positional bindings for ? placeholders
 * @return static For fluent chaining
 * @throws InvalidColumnException When the expression contains dangerous patterns
 */
public function whereRaw(
    string $expression,
    array $bindings = [],
): static;
```

### Declaration ordering in `QueryBuilderInterface.php`

- Place `selectRaw` immediately after `select()` (around line 31, before `distinct()`). This groups SELECT-shaping methods together at the top of the interface.
- Place `whereRaw` immediately after `orWhere` (after line 134). This groups WHERE-shaping methods together.

### No `EntityQueryBuilderInterface` change required
`EntityQueryBuilderInterface extends QueryBuilderInterface` (verified — see `packages/database/src/Query/EntityQueryBuilderInterface.php`). Additions to the parent propagate automatically. But the stub implementation of `EntityQueryBuilderInterface` in `SpecEagerLoadCompositionTest.php` (line ~565) DOES need updating — see the file list above.

## Requirements (Test Descriptions)

### Reflection-based interface assertions (in `QueryBuilderInterfaceTest.php`, matching existing reflection-only style)
- [ ] `QueryBuilderInterface declares a selectRaw method`
- [ ] `QueryBuilderInterface::selectRaw takes a string expression as the first parameter named "expression"`
- [ ] `QueryBuilderInterface::selectRaw takes an array bindings as the second parameter named "bindings" with default []`
- [ ] `QueryBuilderInterface::selectRaw returns static`
- [ ] `QueryBuilderInterface declares a whereRaw method`
- [ ] `QueryBuilderInterface::whereRaw takes a string expression as the first parameter named "expression"`
- [ ] `QueryBuilderInterface::whereRaw takes an array bindings as the second parameter named "bindings" with default []`
- [ ] `QueryBuilderInterface::whereRaw returns static`

(Denylist behavior tests live in tasks 002 and 003 against real drivers — NOT here.)

### Stub-cascade smoke test
- [ ] `composer test for packages/database is fully green after the interface change` (this requirement alone catches any missed stub — a PHP fatal at load time would block all tests)

## Acceptance Criteria
- Both methods declared on the interface with the exact signatures and PHPDoc from above.
- Every stub listed in the Context section has no-op `selectRaw(string $expression, array $bindings = []): static { return $this; }` and `whereRaw(string $expression, array $bindings = []): static { return $this; }` methods. (For the `EntityQueryBuilderInterface` stub in `SpecEagerLoadCompositionTest.php`, both methods plus inheritance through the parent.)
- The reflection tests in `QueryBuilderInterfaceTest.php` pass.
- `composer test` for `packages/database` is fully green (no PHP fatals from missed stubs).
- `phpcs`, `php-cs-fixer --dry-run`, `phpstan` clean.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
