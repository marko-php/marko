# Devil's Advocate Review: query-builder-raw-select-where

## Critical (Must fix before building)

### C1. `orderByRaw` does NOT exist in the codebase — entire "refactor" premise is false
**Affected**: `_plan.md`, tasks 002, 003, 004, 006.

The plan claims `orderByRaw` exists on `QueryBuilderInterface`, in `MySqlQueryBuilder` (with denylist at ~line 384), in `PgSqlQueryBuilder` (with denylist at ~line 384), and in `RepositoryQueryBuilder` (with delegation at lines 232–242). I verified via grep across the entire marko codebase: **zero matches** for `orderByRaw`. The interface ends with `raw()` at line 376. `MySqlQueryBuilder::orderBy` is at line 341 followed by `limit()` at 358 — there is no `orderByRaw`. `RepositoryQueryBuilder` lines 193–200 contain `orderBy()` followed directly by `limit()`.

Consequences:
- Task 002's "extract denylist from `orderByRaw`" cannot be done — there's no inline denylist to extract.
- Task 003's mirror refactor is similarly impossible.
- Task 004's "mirror the existing `orderByRaw` delegation" has no template to mirror — the closest pattern is the existing `having()` delegation at lines 170–175.
- Task 006's CHANGELOG `## Changed` entries claiming `orderByRaw` was refactored are false.
- The plan's Origin/Discovery Notes claim "The scope `ScopedOrderBy` already works against `orderByRaw`" — but the markommerce scope plan (task 013) was written against an `orderByRaw` that doesn't actually exist either. That's a separate problem for the markommerce/scope plan, but our plan should not perpetuate the false premise.

**Fix applied**: Removed all `orderByRaw` references, framed the denylist helper as NEW (not a refactor), removed the "shared with orderByRaw" requirement, and updated CHANGELOG entries to drop the bogus "Changed" sections.

### C2. Per-package CHANGELOG files do not exist; the project uses a single root CHANGELOG
**Affected**: task 006.

I verified: the only CHANGELOG.md in the marko monorepo is `/home/michal/www/marko/marko/CHANGELOG.md`. No CHANGELOG exists in `packages/database/`, `packages/database-mysql/`, or `packages/database-pgsql/`. The root CHANGELOG explicitly says "Entries from `0.4.0` onward are generated automatically by `bin/release.sh` from merged PR titles and labels". This means:
- The plan's directive to add entries to three per-package CHANGELOG files is incorrect.
- Creating per-package CHANGELOGs from scratch would diverge from the project's established release model.
- The acceptance criteria for a `Unit/ChangelogTest.php` would test files that shouldn't exist.

**Fix applied**: Rewrote task 006 to update only the root monorepo `CHANGELOG.md` under its existing `## [Unreleased]` section using the project's actual format (`### New Features`, `feat(database): …` style). Removed the `ChangelogTest.php` requirement (no precedent in the repo).

### C3. Adding methods to `QueryBuilderInterface` breaks ~15 anonymous-class stubs in the test suite
**Affected**: task 001, all downstream tasks (cascade test failures).

`QueryBuilderInterface` is implemented by anonymous-class stubs in at least these test files (all in `packages/database/tests/`):
- `Repository/RepositoryTest.php` (line 1091)
- `Repository/RepositoryMatchingTest.php` (line 50)
- `Repository/StringPrimaryKeyTest.php` (line 153)
- `Repository/RepositoryWithTest.php` (lines 104, 551, 800, 1039)
- `Repository/RepositoryQueryBuilderEnhancedTest.php` (line 78)
- `Entity/RelationshipLoaderNestedTest.php` (line 345)
- `Entity/RelationshipLoaderTest.php` (lines 246, 482)
- `Entity/RelationshipLoaderBelongsToManyTest.php` (lines 200, 448)
- `Entity/RelationshipValidationTest.php` (line 49)
- `Query/QuerySpecificationTest.php` (lines 37, 255)
- `Query/SpecEagerLoadCompositionTest.php` (lines 107, 293) — plus an `EntityQueryBuilderInterface` stub at line 565

Each of these stubs implements every method of the interface (PHP fatal error if any is missing). Adding `selectRaw` and `whereRaw` to the interface in task 001 will cause every one of these tests to fail with `Class contains 2 abstract methods and must therefore be declared abstract`. The plan does not mention these stubs at all, let alone include them in task 001's work.

**Fix applied**: Added an explicit requirement to task 001 to update every stub-impl of `QueryBuilderInterface` in `packages/database/tests/` (and the `EntityQueryBuilderInterface` stub in `SpecEagerLoadCompositionTest.php`) with no-op implementations that return `$this`. Added the full list of files to the task context. Added an acceptance criterion that `composer test` for `packages/database` passes after the change (the only way to confirm all stubs are caught).

### C4. `QueryBuilderInterfaceTest.php` is reflection-only — no stub-impl pattern to mirror, and denylist behavior CANNOT be asserted at the contract level
**Affected**: task 001 (test requirements).

I read the entire `QueryBuilderInterfaceTest.php` (307 lines). Every test uses `new ReflectionClass(QueryBuilderInterface::class)` to assert method presence, parameter names/types, and return types. There is **no** anonymous-class stub used to assert runtime behavior. The plan's task 001 says "Contract tests pass against a minimal stub implementation declared inline in the test (mirroring the existing test pattern)" — but there is no such pattern to mirror in this file.

Furthermore, "denylist throws InvalidColumnException" is a behavior contract that can only be tested against an implementation. Forcing it at the interface level would either (a) invent a new stub pattern just for these two methods (inconsistent with the rest of the file), or (b) duplicate work that tasks 002/003 already do against real implementations.

**Fix applied**: Removed the "denylist via inline stub" requirements from task 001. Kept only reflection-based assertions (method presence, parameter names/types, return type, PHPDoc tag presence via reflection). Behavior tests for the denylist live in tasks 002 and 003 against real driver implementations.

### C5. Cross-driver test in `packages/database` is architecturally impossible
**Affected**: task 005.

`packages/database/composer.json` requires only `marko/core` (no driver dependency — by design, per the interface/driver split). The plan's task 005 puts the cross-driver test in `packages/database/tests/Query/RawSelectWhereConsistencyTest.php` and instantiates both `MySqlQueryBuilder` and `PgSqlQueryBuilder` directly. This requires `packages/database` to depend on `marko/database-mysql` and `marko/database-pgsql`, which inverts the dependency direction and creates a circular dependency (drivers already depend on the interface package).

The plan's claim that `QueryBuilderInterfaceTest.php` is precedent for cross-driver tests in `packages/database` is wrong — that file uses pure reflection and doesn't instantiate either driver.

**Fix applied**: Moved the cross-driver consistency test to a new top-level integration directory: `tests/Integration/QueryBuilderRawConsistencyTest.php` (the marko monorepo's top-level tests directory, accessible via the monorepo `composer test`). If that directory does not exist, the task creates it. The task references the monorepo-level `composer.json` and verifies the test is registered in the monorepo Pest configuration. Alternative fallback documented in the task: parallel symmetric tests in each driver's own test suite asserting the same fixture against its own driver, plus a comment in each cross-referencing the other (lockdown via convention rather than a single test).

### C6. Sibling drift in `buildSelectSql()` between MySql and PgSql changes where `selectRaw` bindings must be inserted
**Affected**: tasks 002, 003.

`PgSqlQueryBuilder::buildSelectSql()` starts with `$this->bindings = []` (line 662). `MySqlQueryBuilder::buildSelectSql()` does NOT reset — it expects the caller (`get()`, `insert()`, etc.) to reset. This is pre-existing sibling drift, not introduced by this plan.

Consequence: the implementation of "prepend `$rawSelectBindings` before WHERE bindings" must use different insertion sites per driver:
- **MySql**: caller (`get()` etc.) resets `$this->bindings = []`, then `buildSelectSql()` is called. Inside `buildSelectSql()`, before `buildWhereClause()` runs, append `$this->rawSelectBindings` to `$this->bindings`.
- **PgSql**: `buildSelectSql()` resets `$this->bindings = []` at line 662, then must append `$this->rawSelectBindings` BEFORE `buildWhereClause()` is invoked.

The plan's task 002/003 instructions don't acknowledge this divergence and just say "verify exact position by tracing the existing binding flow" — leaving each worker to discover this independently and risk getting it wrong.

**Fix applied**: Added explicit per-driver guidance in tasks 002 and 003 describing where the `$rawSelectBindings` insertion must occur, referencing the actual line numbers and the reset behavior in each file. Also added a test requirement that asserts `selectRaw` bindings appear before `where()` bindings in the final array (and that the bindings array is correctly ordered even after re-running the query — i.e., calling `get()` twice doesn't double-append).

## Important (Should fix before building)

### I1. `selectRaw` and `whereRaw` behavior in aggregate code paths (`count`, `min`, `max`, `sum`, `avg`) is undefined
**Affected**: tasks 002, 003.

`MySqlQueryBuilder::runAggregate()` (line 533) and the PgSql equivalent reset `$this->bindings = []` and build a single-column SELECT directly, bypassing `buildSelectSql()` entirely. If a caller does `->selectRaw(...)->whereRaw(...)->count()`:
- `selectRaw` is silently ignored (aggregate replaces the entire SELECT list).
- `whereRaw` is silently ignored (`runAggregate` uses `buildWhereClause()` which only handles regular `where*` state).

This is a foot-gun. Plan should either:
- Document the limitation in the interface PHPDoc ("raw select/where do not affect aggregate methods").
- Make `runAggregate` honor `whereRaw` (selectRaw is meaningless for an aggregate, but whereRaw is meaningful).

**Fix applied**: Updated tasks 002 and 003 to require `runAggregate()` (or whichever method builds the aggregate WHERE clause) to also include `whereRaw` conditions. Added a test requirement: "count()/min()/max() honor whereRaw conditions". Added a PHPDoc note on the interface `selectRaw` method that selectRaw is ignored when aggregate methods (`count`, `min`, etc.) are called, since those replace the SELECT list entirely.

### I2. `compileSubquery` (UNION subquery path) — verify `selectRaw` and `whereRaw` flow through
**Affected**: tasks 002, 003.

`compileSubquery` is called when this builder is used as the right-hand side of a UNION. It calls `buildSelectSql()`, captures `$this->bindings`, merges, restores. As long as `buildSelectSql()` correctly handles raw selects/wheres internally, this works — but it's worth an explicit test.

**Fix applied**: Added a test requirement in tasks 002 and 003: "selectRaw and whereRaw bindings appear correctly in a UNION subquery's binding stream".

### I3. Calling `selectRaw` or `whereRaw` multiple times — order preservation and re-call semantics
**Affected**: tasks 002, 003.

The plan defines state as `private array $rawSelects = []` and `$rawWheres = []` — append-only — but doesn't add tests that:
- Multiple `selectRaw` calls produce SELECT list in call order.
- Multiple `whereRaw` calls produce WHERE conditions in call order, AND-combined.
- Bindings for multiple `selectRaw` calls are concatenated in call order.
- Bindings for multiple `whereRaw` calls are concatenated in call order.

**Fix applied**: Added explicit test requirements for multi-call ordering to both tasks 002 and 003.

### I4. `selectRaw` with empty `$this->columns` (default `['*']`) — what does the emitted SELECT look like?
**Affected**: tasks 002, 003.

`$columns` defaults to `['*']`. If a user calls `->selectRaw('COUNT(*)')` without first calling `->select(...)`, the emitted SQL would be `SELECT *, COUNT(*) FROM ...` — almost certainly not what the user wants. Behavior should be documented and tested:
- Option A: appending `selectRaw` removes the implicit `*` if it's the only column.
- Option B: leave it alone, document that callers should pair `selectRaw` with `select()` explicitly.

The plan picks neither. Laravel/Doctrine's convention is Option B (leave the `*`). For consistency with the plan's stated "Combined select: `select()` + `selectRaw()` together — raw selects are appended to the regular column list in compile order", Option B is the implicit choice but should be explicit.

**Fix applied**: Added explicit test cases to tasks 002 and 003: "selectRaw with no prior select() emits `SELECT *, <raw>` (the default `*` is preserved)". Documented this in the plan's Architecture Notes and in the method's PHPDoc.

### I5. Repository wrapper test should also assert `selectRaw`/`whereRaw` work alongside `with()` and `matching()`
**Affected**: task 004.

`RepositoryQueryBuilder` is the wrapper used by Repositories. The most likely real-world use of `selectRaw`/`whereRaw` is inside a `QuerySpecification` that calls them via the wrapper. Task 004 covers basic delegation but not the spec composition path. Worth a smoke test.

**Fix applied**: Added a requirement to task 004: "selectRaw/whereRaw work when called from within a QuerySpecification applied via matching()".

### I6. Task 001 declaration ordering — `selectRaw` placement and parameter naming convention
**Affected**: task 001.

The plan says "place `selectRaw` immediately after the `distinct()` declaration" and "place `whereRaw` immediately after `orWhere`". Reading the interface, this is reasonable but worth noting:
- `select()` is at line 30, `distinct()` at line 43. Placing `selectRaw` after `distinct()` means readers see `select()` (line 30), then a bunch of where-methods (lines 44–134), then come back up mentally to find `selectRaw`. A clearer placement is **immediately after `select()`** so the SELECT-shaping methods sit together at the top.
- For `whereRaw` after `orWhere` (line 130–134) — that's fine.

**Fix applied**: Updated task 001 to place `selectRaw` immediately after `select()` (around line 31) rather than after `distinct()`. Kept `whereRaw` after `orWhere`.

### I7. Task 005's "stub ConnectionInterface" needs concrete shape
**Affected**: task 005.

The bindings array on `MySqlQueryBuilder` is `private`. The only way to inspect the bindings that get sent to the connection is via a stub `ConnectionInterface` that records `($sql, $bindings)` from each `query()`/`execute()` call. Task 005 hints at this but doesn't specify the stub shape, the recording mechanism, or how to assert against the captured pair.

**Fix applied**: Added concrete guidance to task 005: the stub records the last `($sql, $bindings)` pair from `query()` and exposes them via public properties; assertions read those properties after calling `->get()` on the builder.

## Minor (Nice to address)

### M1. The `having()` denylist drift is now permanent
The plan notes that `having()` doesn't block backticks today and intentionally leaves it that way. After this plan ships, the codebase will have:
- `having()` blocks `;`, `--`, `/*`, `*/` (no backtick).
- `selectRaw()` and `whereRaw()` block all 5 patterns.

This is fine for now but worth a follow-up plan to unify the denylist into a shared `RawExpressionValidator` (already in the plan's "Deferred" section — good).

### M2. PgSql's `buildSelectSql()` resets `$this->bindings` inside; MySql does not
This is pre-existing sibling drift, not caused by this plan, but worth a future cleanup plan. The two `buildSelectSql()` methods should have the same contract about who resets bindings.

### M3. The exception factory `InvalidColumnException::invalidColumn()` returns a message that says "Validating SELECT column expression" — when used for `whereRaw`, the context will read awkwardly
The exception's `context` field hardcodes "Validating SELECT column expression `$column`". For `whereRaw` this will be misleading. A clean fix is to add a dedicated factory like `InvalidColumnException::invalidRawExpression(string $expression, string $clauseType)` so the error context says "Validating WHERE raw expression" or "Validating SELECT raw expression". This is a small improvement but not blocking.

### M4. Task 006's verbatim CHANGELOG block uses an `Added`/`Changed` Keep-a-Changelog format
Even after fixing the per-package CHANGELOG issue, the entry style in the rewritten task should match the root CHANGELOG's actual style (which uses `### New Features` / `### Bug Fixes` / `### Maintenance`, not `### Added` / `### Changed`).

**Fix applied** in C2 above.

## Questions for the Team

### Q1. `orderByRaw` clearly does not exist — what is the markommerce/scope team's actual plan?
The markommerce `scope-composite-overrides` plan (task 013) emits SQL like `builder.orderByRaw(sql, strtoupper($direction))`. That call will throw `BadMethodCallException` at runtime against any current `QueryBuilderInterface` impl. Three options:
1. Add `orderByRaw` to `QueryBuilderInterface` (and to MySql/PgSql/RepositoryQueryBuilder) as part of THIS plan, since adding raw select/where without raw order-by leaves the scope team blocked.
2. File a separate plan for `orderByRaw` and accept that markommerce/scope task 013 is blocked until then.
3. Have markommerce/scope rewrite `ScopedOrderBy` to use the escape-hatch `raw()` method or some other workaround.

The user's framing of this review explicitly says "make sure additions are strictly additive — no signature changes on existing methods, no breaking behavior on `having()` / `orderByRaw()`", which implies the user believes `orderByRaw` exists. This is a Question for the team — the architecture review can flag it but not resolve it.

### Q2. Should the denylist also include `\x00` (NUL byte) and `'` (single quote)?
Several SQL-injection cheat-sheets recommend rejecting NUL bytes (terminate query early in some parsers) and unescaped single quotes (string literal escape attempts). The plan only mirrors the existing `having()` denylist. Worth a future review pass, but out of scope here.

### Q3. Should `selectRaw` validate that the expression contains at least one identifier-like token?
A defensive check ("expression must contain at least one of `[a-zA-Z_]` or `*`") would catch empty/whitespace-only strings. Currently the denylist passes those silently. Out of scope but worth considering.

### Q4. Should `selectRaw` and `whereRaw` track which bindings came from raw clauses for debugging/profiling?
A future SQL debug logger might want to highlight which bindings originated from `selectRaw` vs `where()` vs `whereRaw`. The current `$bindings` array is a flat list. Not blocking — worth a follow-up.
