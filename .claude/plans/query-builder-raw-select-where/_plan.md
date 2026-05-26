# Plan: QueryBuilderInterface — `selectRaw` and `whereRaw`

## Created
2026-05-19

## Status
completed

## Objective
Add `selectRaw(string $expression, array $bindings = []): static` and `whereRaw(string $expression, array $bindings = []): static` to `Marko\Database\Query\QueryBuilderInterface`. Implement in `MySqlQueryBuilder`, `PgSqlQueryBuilder`, and the delegating `RepositoryQueryBuilder`. Sibling-module rule: identical method names, visibilities, and patterns across the two drivers.

## Related Issues
none

## Origin
Filed as a follow-up dependency of `markommerce/scope`'s `scope-composite-overrides` plan, which deferred `ScopedSelect` and `ScopedWhere` query specifications because the methods they'd compose against do not exist on `QueryBuilderInterface` today (only the escape-hatch `raw()` is exposed). Shipping `selectRaw` and `whereRaw` adds the symmetric SELECT and WHERE primitives needed by those specs. (`orderByRaw` — also needed by the markommerce scope work — is being added in a separate, already-open marko PR; see "Related work" below.)

## Discovery Notes

### Current state (verified against source)

- `QueryBuilderInterface` defines `select(string ...$columns)`, `where(column, operator, value)`, `whereIn`, `whereNull`, `whereNotNull`, `whereJsonContains/Exists/Missing`, `orWhere`, `having(expression, bindings)` (already a raw form for HAVING), `orderBy`, and the escape-hatch `raw(sql, bindings)` — no `selectRaw`, no `whereRaw`, **and no `orderByRaw`**.
- `EntityQueryBuilderInterface` extends `QueryBuilderInterface` — additions to the parent propagate automatically.
- Two driver implementations: `MySqlQueryBuilder` (881 lines) and `PgSqlQueryBuilder` (886 lines). Both `implements QueryBuilderInterface` directly. Neither contains an `orderByRaw` method.
- `RepositoryQueryBuilder` is 394 lines, a delegating wrapper around an internal `$queryBuilder`. Adding the methods there is a straight passthrough mirroring the existing `having()` delegation at lines 170–175.
- Existing prior art for raw-with-bindings: `having(string $expression, array $bindings = [])` in both drivers. Its inline denylist rejects `;`, `--`, `/*`, `*/` (NO backtick). This plan deliberately uses a slightly stricter denylist on the new methods (adds backtick) but does NOT touch `having()`.
- Test layout: each driver has a `tests/Query/{Driver}QueryBuilderTest.php` plus topical files. The interface itself has `packages/database/tests/Query/QueryBuilderInterfaceTest.php` (reflection-only contract tests — no inline stub-impl pattern). The repository wrapper has `RepositoryQueryBuilderEnhancedTest.php`.
- The `feature/scope` branch in marko already merged some work; this plan branches off `develop` per the marko PR conventions.
- **The interface has ~15 anonymous-class stub implementations across `packages/database/tests/`** (full list in task 001). Adding methods to the interface forces every stub to be updated; otherwise PHP fatal errors at test load time. Plus one `EntityQueryBuilderInterface` stub in `SpecEagerLoadCompositionTest.php`.
- **No per-package CHANGELOG files exist.** The marko monorepo has a single root `CHANGELOG.md` populated by `bin/release.sh` from PR titles/labels. CHANGELOG updates in this plan target the root file under `## [Unreleased]`.
- **Sibling drift in `buildSelectSql()`**: `PgSqlQueryBuilder::buildSelectSql()` resets `$this->bindings = []` at its top (line 662); `MySqlQueryBuilder::buildSelectSql()` does NOT (the caller — `get()` etc. — resets). The `selectRaw` bindings insertion point therefore differs between the two drivers.
- **Aggregate paths bypass `buildSelectSql()`**: `count()`, `min()`, `max()`, `sum()`, `avg()` all call `runAggregate()` which builds its own SELECT and shares only `buildWhereClause()`. So `selectRaw` is meaningless during aggregates, and `whereRaw` must be wired into the aggregate WHERE path explicitly (see task 002/003 requirements).

### Assumptions resolved up-front

1. **API shape**: `selectRaw($expression, $bindings = [])` and `whereRaw($expression, $bindings = [])` — both return `static` for chaining. Bindings are positional `?` placeholders, mirroring `having()`. **No** `?$alias` parameter on `selectRaw` — the caller embeds `AS alias` in the expression if needed (Laravel/Doctrine convention; keeps the surface minimal).
2. **YAGNI on `orWhereRaw` and `havingRaw`**: this plan ships only `selectRaw` + `whereRaw`. `orWhereRaw` and `havingRaw` (or rather, `having` already takes a raw expression) can be added when a real consumer needs them.
3. **Combined select**: `select()` + `selectRaw()` together — raw selects are appended to the regular column list in compile order. The default `['*']` column list is preserved when only `selectRaw()` is called (caller must explicitly `select(...)` if they don't want `*`). Documented and tested.
4. **Validation**: same denylist as `having()` — block `;`, `--`, `/*`, `*/`. **Add backtick blocking too** as a defensive measure (raw expressions should not contain user-supplied identifier-quoting). Single rule across both new raw methods. The plan explicitly does NOT touch `having()`'s existing denylist (which is missing the backtick rule); unifying the two is deferred.
5. **Bindings flow**: `whereRaw`'s bindings are appended to the WHERE-clause bindings list in compile order. `selectRaw`'s bindings are prepended to the bindings stream at SELECT-list compile time (SELECT comes before WHERE in the SQL). Per-driver insertion point differs because of `buildSelectSql()` sibling drift — tasks 002 and 003 each document the exact insertion site.
6. **Aggregate methods**: `selectRaw` is documented as ignored when `count()`/`min()`/`max()`/`sum()`/`avg()` is called (those replace the SELECT list entirely). `whereRaw` IS honored by aggregate paths — `runAggregate()` is updated to include raw-where conditions alongside the regular WHERE clause.
7. **No `EntityQueryBuilderInterface` change**: the parent interface gets the methods; `EntityQueryBuilderInterface` inherits them. No separate change needed.
8. **Stub-impl cascade**: adding two methods to `QueryBuilderInterface` breaks every anonymous-class stub that implements it (~15 files in `packages/database/tests/`). Task 001 is responsible for updating all of them with no-op implementations that return `$this`.

## Scope

### In Scope
- `selectRaw(string $expression, array $bindings = []): static` on `QueryBuilderInterface`.
- `whereRaw(string $expression, array $bindings = []): static` on `QueryBuilderInterface`.
- `MySqlQueryBuilder` implementations (SQL compilation + tests).
- `PgSqlQueryBuilder` implementations (mirror — sibling rule).
- `RepositoryQueryBuilder` passthrough delegations (+ tests in `RepositoryQueryBuilderEnhancedTest.php`).
- Reflection-based contract tests in `packages/database/tests/Query/QueryBuilderInterfaceTest.php` asserting both methods are declared, return `static`, and take the expected params with expected names and defaults. (The file is reflection-only — there is no inline stub-impl pattern to mirror; behavior tests for the denylist live in tasks 002/003.)
- Updating every existing anonymous-class stub of `QueryBuilderInterface` (and the one `EntityQueryBuilderInterface` stub) with no-op `selectRaw` / `whereRaw` methods so the test suite still loads (see task 001 for the full file list).
- CHANGELOG entry in the root monorepo `/CHANGELOG.md` under `## [Unreleased]` (per-package CHANGELOGs do not exist in this monorepo).

### Out of Scope
- `orWhereRaw`, `andWhereRaw`, `havingRaw`. (HAVING is already raw-by-default via the existing `having()` signature.)
- `selectRaw` with a dedicated `?string $alias` parameter — embed `AS alias` in the expression.
- Updating Marko's docs site (`docs/src/content/docs/`) — handled by the post-implementation `doc-updater` agent (per `.claude/pipeline.md`).
- Adding parsing or validation of the SQL inside the raw expression beyond the denylist — caller is responsible.
- Any change to `EntityQueryBuilderInterface` (inherits automatically).
- Any consumer wiring in markommerce — that's the follow-up `markommerce/scope` work (filing a `ScopedSelect`/`ScopedWhere` plan there will happen separately after this lands).

## Success Criteria
- [ ] `QueryBuilderInterface::selectRaw` and `::whereRaw` are declared with the expected signatures and PHPDoc (including `@throws InvalidColumnException`).
- [ ] Every existing stub implementation of `QueryBuilderInterface` (and the one `EntityQueryBuilderInterface` stub) in `packages/database/tests/` has no-op `selectRaw` / `whereRaw` methods so the test suite loads.
- [ ] `MySqlQueryBuilder::selectRaw` and `::whereRaw` compile correctly into the emitted SQL with bindings in the right order.
- [ ] `PgSqlQueryBuilder::selectRaw` and `::whereRaw` ditto — identical observable behavior to MySql; per-driver internal insertion point for `selectRaw` bindings differs because of pre-existing `buildSelectSql()` sibling drift.
- [ ] `RepositoryQueryBuilder::selectRaw` and `::whereRaw` delegate cleanly.
- [ ] All denylist patterns (`;`, `--`, `/*`, `*/`, backtick) throw `InvalidColumnException` on both methods in both drivers.
- [ ] `select() + selectRaw()` combined emits both column sets in order with correct binding placement; `selectRaw()` alone (no prior `select()`) emits `SELECT *, <raw>`.
- [ ] `whereRaw` is honored by aggregate methods (`count`, `min`, `max`, `sum`, `avg`); `selectRaw` is documented as ignored by aggregates.
- [ ] `composer test` is fully green in the marko monorepo (this is the only way to confirm the stub cascade was caught and bindings/SQL flow works end-to-end).
- [ ] `./vendor/bin/phpcs`, `./vendor/bin/php-cs-fixer fix --dry-run`, `./vendor/bin/phpstan analyse` all clean.
- [ ] The root monorepo `/CHANGELOG.md` has a `## [Unreleased]` entry mentioning `selectRaw` and `whereRaw` in the project's actual changelog style (`### New Features`).

## Task Overview

| Task | Description | Depends on | Status |
|------|-------------|------------|--------|
| 001 | Add `selectRaw` and `whereRaw` declarations + PHPDoc to `QueryBuilderInterface`; update all ~15 anonymous-class stub implementations in `packages/database/tests/` + the one `EntityQueryBuilderInterface` stub with no-op methods; add reflection-based contract tests to `QueryBuilderInterfaceTest.php` asserting signatures | — | completed |
| 002 | Implement `selectRaw` and `whereRaw` in `MySqlQueryBuilder` — compile-time integration with select-list and where-clause builders, bindings ordering, denylist enforcement (new private helper, no refactor), aggregate WHERE path also honors `whereRaw`; tests in `MySqlQueryBuilderTest.php` | 001 | completed |
| 003 | Implement `selectRaw` and `whereRaw` in `PgSqlQueryBuilder` — mirror MySql impl per sibling rule (modulo the pre-existing `buildSelectSql()` reset drift, which forces a different bindings insertion point); tests in `PgSqlQueryBuilderTest.php` | 001 | completed |
| 004 | Implement passthrough `selectRaw` and `whereRaw` in `RepositoryQueryBuilder` (mirror the existing `having()` delegation at lines 170–175); tests in `RepositoryQueryBuilderEnhancedTest.php` covering basic delegation and use from within a `QuerySpecification` | 001 | completed |
| 005 | Cross-driver consistency: a test that runs the same input through `MySqlQueryBuilder` and `PgSqlQueryBuilder` via a recording stub `ConnectionInterface`, asserts the captured SQL skeleton + bindings array match between the drivers. Lives at the monorepo top-level `tests/Integration/` to avoid creating a circular dep on the driver packages from `packages/database` | 002, 003 | completed |
| 006 | One CHANGELOG entry in the root `/CHANGELOG.md` under `## [Unreleased]` (in the existing `### New Features` style) mentioning `selectRaw` and `whereRaw` on `QueryBuilderInterface`. No per-package CHANGELOG files; no README updates (handled by `doc-updater` per `.claude/pipeline.md`) | 005 | completed |

Parallel batches:
```
Batch 1: 001
Batch 2: 002, 003, 004   (all ←001)
Batch 3: 005              (←002, 003)
Batch 4: 006              (←005)
```

## Architecture Notes

### Method signatures (final)

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

### Binding order

- SELECT bindings come BEFORE WHERE bindings in the compiled SQL (per SQL standard).
- `selectRaw($expr, $bindings)` appends `$bindings` to a `private array $rawSelectBindings = []` member; the compile step appends them to `$this->bindings` BEFORE `buildWhereClause()` runs.
- `whereRaw($expr, $bindings)` appends to a `private array $rawWheres` list which `buildWhereClause()` consumes after the regular `where*` state, in call order, AND-combined.
- The cross-driver test (task 005) asserts both binding-order invariants with a concrete fixture (e.g. `select` + `selectRaw('… ?, ?', ['a','b'])` + `where('x','=',true)` + `whereRaw('y > ?', [100])` should produce bindings `['a','b',true,100]` in BOTH drivers).

### Per-driver `selectRaw` bindings insertion site (sibling drift)

- **MySql**: `buildSelectSql()` does NOT reset `$this->bindings`. Caller (`get()` line 380, etc.) resets first. Insert `$this->rawSelectBindings` into `$this->bindings` at the top of `buildSelectSql()`, before `buildWhereClause()` is called.
- **PgSql**: `buildSelectSql()` resets `$this->bindings = []` at line 662. Insert `$this->rawSelectBindings` into `$this->bindings` AFTER that reset, before `buildWhereClause()` is called.
- Both drivers must NOT mutate `$this->rawSelectBindings` during compile (so repeated `get()` calls produce identical bindings).

### Denylist (security)

Identical across `selectRaw` and `whereRaw` (NEW private helper per driver — there is no pre-existing helper to refactor):
- `;` (statement chaining)
- `--` (line comment)
- `/*` and `*/` (block comment)
- `` ` `` (backtick — MySQL identifier quote; raw expressions should use the driver's own quoting, not user-supplied backticks)

The existing `having()` denylist does NOT block backticks today (verified via Read). This plan **does NOT change `having()`** — touching it would be scope creep. Future cleanup could unify the denylist into a shared `RawExpressionValidator`; deferred.

### `RepositoryQueryBuilder` passthrough pattern

Mirror the existing `having()` delegation at `packages/database/src/Repository/RepositoryQueryBuilder.php` lines 170–175:

```php
public function selectRaw(
    string $expression,
    array $bindings = [],
): static {
    $this->queryBuilder->selectRaw($expression, $bindings);
    return $this;
}

public function whereRaw(
    string $expression,
    array $bindings = [],
): static {
    $this->queryBuilder->whereRaw($expression, $bindings);
    return $this;
}
```

### Sibling-module rule

`MySqlQueryBuilder` and `PgSqlQueryBuilder` must declare the methods with **identical** public-API parameter names, visibilities, and parameter ordering. The new state-field names (`$rawSelects`, `$rawSelectBindings`, `$rawWheres`) and the new private denylist helper (`assertNoDangerousPatterns`) MUST also match across both classes line-for-line.

Observable behavior must be identical: same emitted SQL skeleton (modulo identifier quoting `` ` `` vs `"`) and same final bindings array for the same input. Task 005 enforces this.

**The one legitimate internal divergence**: the `selectRaw` bindings insertion site differs between the two drivers because of pre-existing sibling drift in `buildSelectSql()` — PgSql resets `$this->bindings` at the top of that method while MySql relies on the caller. Both task 002 and task 003 document the per-driver site explicitly. This is not a new drift introduced by this plan and is captured in `## Deferred` for a future cleanup.

Tests in 002 and 003 should be near-mirror images.

## Risks & Mitigations

- **Risk**: bindings get ordered wrong relative to SELECT/WHERE positions → silent SQL errors at runtime. **Mitigation**: task 005 is a dedicated cross-driver test with a fixture that uses both `selectRaw` and `whereRaw` together AND has a `where()` call between them, asserting the final bindings array order against a documented expectation.
- **Risk**: per-driver `buildSelectSql()` reset drift causes `selectRaw` bindings to land at different positions. **Mitigation**: the architecture note above documents the exact insertion site per driver; both task 002 and task 003 require explicit tests that assert the final bindings array shape.
- **Risk**: denylist drift (`having()` doesn't block backticks today; future maintainer adds them inconsistently). **Mitigation**: explicit "do NOT touch `having()`" note in this plan + a TODO captured in `## Deferred` below for a future denylist-unification plan.
- **Risk**: `RepositoryQueryBuilder` passthrough silently swallows the return value if `$this->queryBuilder->...` returns a new instance rather than `static`. **Mitigation**: existing `having()` delegation (lines 170–175) already follows the discard-and-`return $this` pattern; both drivers return `static`. Task 004's test asserts fluent chaining works through the wrapper.
- **Risk**: adding methods to `QueryBuilderInterface` breaks ~15 stub implementations in the existing test suite. **Mitigation**: task 001 explicitly lists every affected file and requires updating each one. The success criterion "`composer test` is fully green" cannot pass without all stubs being updated.
- **Risk**: aggregate methods silently ignore `whereRaw` conditions, producing wrong counts. **Mitigation**: tasks 002 and 003 explicitly require `runAggregate()` (or the equivalent path) to honor `whereRaw`. Tests assert `count()` with a `whereRaw` filter returns the filtered count, not the full-table count.
- **Risk**: docs page on `marko.build` for the database package becomes out of sync. **Mitigation**: marko's `post-implementation` pipeline runs `doc-updater` which handles this. No manual docs work in this plan.

## Related work (resolved — not blocking)

`orderByRaw` is being added in a **separate PR** that is already open against marko and awaiting maintainer review. That PR is the canonical home for the `orderByRaw` addition (interface + both drivers + repository delegation). This plan deliberately scopes itself to `selectRaw` + `whereRaw` only and does NOT add `orderByRaw`.

Merge-order interaction:
- If the `orderByRaw` PR merges FIRST: when this plan lands, the new private `assertNoDangerousPatterns()` helper introduced here will be a duplicate of `orderByRaw`'s inline denylist. A small follow-up cleanup can unify them. Not a blocker for either PR.
- If this plan merges FIRST: the `orderByRaw` PR can either inline its own denylist (matching its existing style) or share `assertNoDangerousPatterns()` once rebased.
- Either order is safe — the PRs are functionally orthogonal.

## Deferred (for a future plan)
- `orWhereRaw`, `andWhereRaw` — add when a real consumer needs OR-combined raw conditions.
- `havingRaw` — `having()` is already raw; rename + alias may be cleaner symmetry, but not urgent.
- Unify the raw-expression denylist into a single `RawExpressionValidator` class shared by `selectRaw`, `whereRaw`, `orderByRaw`, and `having`. After both this PR and the separate `orderByRaw` PR land, the denylist will be duplicated in three places. Worth a small cleanup plan then.
- Fix the `buildSelectSql()` sibling drift in `MySql` vs `PgSql` (one resets `$this->bindings`, the other doesn't) — pre-existing, not caused by this plan.
- Driver-specific raw-expression sanitizers (Postgres dollar-quoted strings, etc.) — out of scope; raw means raw.
