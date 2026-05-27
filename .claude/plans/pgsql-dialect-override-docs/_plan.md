# Plan: PgSql Dialect Override Pattern + Docs

## Created
2026-05-26

## Status
completed

## Objective
Resolve GitHub issue #31 by proving and documenting that postgres-wire-compatible databases (CockroachDB, YugabyteDB, etc.) can ship as sibling packages that depend on `marko/database-pgsql` and override only the 4 dialect-specific bindings via a `boot` callback — without splitting packages, renaming, or changing the framework.

## Related Issues
Closes #31

## Discovery Notes

**Architectural verification:**
- `packages/database-pgsql/module.php` already exposes 5 separate bindings: `ConnectionInterface` (wire), `SqlGeneratorInterface`, `IntrospectorInterface`, `QueryBuilderInterface`, `QueryBuilderFactoryInterface`.
- `PgSqlConnection` is dialect-clean — pure PDO + `SET NAMES`, no embedded dialect SQL.

**Override mechanism (the key insight):**
- `BindingRegistry::registerBinding()` throws `BindingConflictException` when two same-priority (e.g. both vendor) modules declare a binding for the same interface via the `bindings` manifest key. So a sibling dialect package cannot use the static `bindings` key to win.
- **`Container::bind()` (packages/core/src/Container/Container.php:38-43) is a direct write** that bypasses BindingRegistry's conflict detection.
- Module `boot` callbacks run **after** all static bindings are registered (packages/core/src/Application.php:179-185).
- Therefore: a sibling module's `boot` callback can call `$container->bind(SqlGeneratorInterface::class, CockroachDbGenerator::class)` to override pgsql's dialect bindings cleanly, with zero framework changes. The architecture doc already calls out boot callbacks for env-conditional rebinding; the dialect-variant use case is the same mechanism for a different reason.

**Docs gap:**
- `concepts/dependency-injection.md` does NOT currently document `boot` callbacks at all — it covers constructor injection, static `bindings`, `singletons`, resolution order, and auto-resolution. The closest existing coverage is in `packages/core.md` ("Environment-Specific Bindings" section), which already shows the boot-callback shape but in the env-conditional framing, not the cross-module-override framing.
- `packages/database.md` has no guidance on shipping wire-compatible variants.

**Override safety preconditions (verified):**
- None of the 5 dialect-related interfaces (`ConnectionInterface`, `SqlGeneratorInterface`, `IntrospectorInterface`, `QueryBuilderInterface`, `QueryBuilderFactoryInterface`) are declared in any module's `singletons` key. This matters because `Container::resolve()` caches singleton instances at line 141-143/211-213; if any of these were promoted to singleton AND resolved before a variant's boot callback ran, the override would silently fail. The integration test must therefore (a) only resolve the interfaces AFTER the variant boot has run, and (b) future-proof by asserting that pgsql's manifest still has no `singletons` entry for the 4 dialect interfaces.

## Scope

### In Scope
- Integration test in `packages/database-pgsql/tests/` proving the boot-callback override pattern: a fixture "variant" module rebinds the 4 dialect interfaces and the container resolves to the overrides while `ConnectionInterface` remains bound to `PgSqlConnection`.
- New section in `docs/src/content/docs/concepts/dependency-injection.md`: "Overriding another module's bindings" — generic architectural pattern, applies to any sibling driver scenario.
- New section in `docs/src/content/docs/packages/database.md`: "Wire-compatible database variants" — database-specific guidance with worked CockroachDB example, links to the DI concept doc for mechanism.
- Short cross-link subsection in `docs/src/content/docs/packages/database-pgsql.md` pointing to `database.md`.

### Out of Scope
- Splitting `marko/database-pgsql` into wire + dialect sub-packages.
- Renaming any existing packages.
- Scaffolding `marko/database-cockroachdb` or any other concrete variant package.
- Framework changes (new manifest keys, container behavior changes).
- Docs touches on `packages/database-mysql.md` — no real 1.0 motivator for MySQL-wire-compatible variants; the generic DI concept doc covers it.
- Issue #4 (read/write splitting) — separate issue, separate plan.

## Success Criteria
- [ ] Integration test demonstrates a downstream module overriding the 4 dialect bindings via `boot` while inheriting `PgSqlConnection`.
- [ ] DI concept doc has a clear, copy-pastable example of the boot-override pattern.
- [ ] `packages/database.md` has a "Wire-compatible database variants" section with a worked example.
- [ ] `packages/database-pgsql.md` links to the database.md guide.
- [ ] `composer test` passes.
- [ ] `./vendor/bin/phpcs` clean on touched files.
- [ ] `npm --prefix docs run build` passes.
- [ ] PR opened against `develop` with "Closes #31" in the body.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Integration test for boot-callback dialect override | - | completed |
| 002 | DI concept doc: cross-module binding override section | - | completed |
| 003 | Database guide: wire-compatible variants section | 002 | completed |
| 004 | Database-pgsql page: cross-link to variants guide | 003 | completed |

Task 003 depends on 002 because it cross-links to the anchor `#overriding-another-modules-bindings` produced by task 002's H2. The anchor is fixed by github-slugger from the H2 title "Overriding another module's bindings" → `overriding-another-modules-bindings`.

## Architecture Notes
- The boot-callback override pattern uses **existing, intentional** framework behavior. `Container::bind()` is deliberately a direct write so boot callbacks can rebind freely; the conflict detection lives in `BindingRegistry` and only applies to the static `bindings` manifest key.
- A future enhancement could add a declarative `'overrides'` manifest key for discoverability, but it's purely additive and not needed to close #31.
- The 4 dialect interfaces being overridden are: `SqlGeneratorInterface`, `IntrospectorInterface`, `QueryBuilderInterface`, `QueryBuilderFactoryInterface`. `ConnectionInterface` is intentionally inherited.

## Risks & Mitigations
- **Risk:** The integration test could pass for the wrong reason (e.g. fixture loaded at wrong priority). **Mitigation:** Test must assert both that the override resolves AND that `ConnectionInterface` is still `PgSqlConnection`, proving the partial inheritance.
- **Risk:** Docs drift if the boot pattern is later replaced by a declarative `overrides` key. **Mitigation:** Keep the docs focused on the *behavior* (one module rebinds another's interface); a future declarative form would supplement, not replace, the description.
- **Risk:** Test fixture in `tests/` could be discovered as a real module. **Mitigation:** Place the fixture outside any path the module discoverer scans, or instantiate `ModuleManifest` + container directly in the test rather than relying on discovery.
