# Task 001: Integration Test for Boot-Callback Dialect Override

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add an integration test in `packages/database-pgsql/tests/` that proves a downstream module can override the 4 dialect-specific bindings (`SqlGeneratorInterface`, `IntrospectorInterface`, `QueryBuilderInterface`, `QueryBuilderFactoryInterface`) via a `boot` callback while inheriting `PgSqlConnection` for `ConnectionInterface`. This locks in the architectural guarantee that makes shipping postgres-wire-compatible variant packages (CockroachDB, YugabyteDB, etc.) viable without splitting `marko/database-pgsql`.

## Context
- **Why a new test, not just expanding `ModuleBindingsTest`:** `tests/Module/ModuleBindingsTest.php` only inspects the manifest array statically. This task needs an actual container roundtrip to prove the override resolves at runtime.
- **Existing pattern to mirror:** Look at `packages/core/tests/` (especially anything that constructs `Container` + `ModuleManifest` directly) for the lightest-weight way to build a 2-module scenario without invoking full module discovery. Avoid spinning up a real filesystem-based discovery — instantiate `ModuleManifest` objects in the test and register them via `BindingRegistry` + boot-callback invocation.
- **Fixture classes:** The variant dialect's replacement classes don't need to be real implementations — minimal stubs implementing the 4 dialect interfaces are enough. Place them under `packages/database-pgsql/tests/Fixtures/` (or wherever the existing test convention places fixtures — check `packages/queue-database/tests/Fixtures/` for precedent).
- **Two-module scenario:**
  1. Module A = the real `marko/database-pgsql` manifest (load it via `require .../module.php` and wrap in a `ModuleManifest` value object).
  2. Module B = a fixture variant module with no static `bindings`, only a `boot` closure that calls `$container->bind()` for the 4 dialect interfaces.
- **Ordering requirement (must mirror Application::boot):** The test setup MUST register all static bindings from BOTH modules through `BindingRegistry` first, THEN invoke any boot callbacks. Resolving any of the 4 dialect interfaces from the container BEFORE the variant boot runs would defeat the test's purpose. Mirror the order in `packages/core/src/Application.php` lines 151-185.
- **Singleton-cache regression guard:** The override pattern depends on the 4 dialect interfaces NOT being declared in any module's `singletons` key (because `Container::resolve()` caches singleton instances at lines 141-143 and 211-213, and a cached instance would survive a later `bind()` call). Add an assertion that the loaded `marko/database-pgsql` manifest has no `singletons` entry for any of the 4 dialect interfaces — this guards against a future change to pgsql's `module.php` silently breaking variant packages.
- **Loud-error guarantee:** The test must also confirm that if Module B used the static `bindings` key instead of a `boot` callback, `BindingConflictException` is thrown. Construct `BindingRegistry` directly with two `ModuleManifest` instances of the same `source` (both `vendor`) declaring the same interface in `bindings`, and assert the exception. This documents *why* boot is the right mechanism.

## Requirements (Test Descriptions)
- [ ] `it resolves SqlGeneratorInterface to the variant override after boot runs`
- [ ] `it resolves IntrospectorInterface to the variant override after boot runs`
- [ ] `it resolves QueryBuilderInterface to the variant override after boot runs`
- [ ] `it resolves QueryBuilderFactoryInterface to the variant override after boot runs`
- [ ] `it still resolves ConnectionInterface to PgSqlConnection because the variant did not rebind it`
- [ ] `it throws BindingConflictException when a variant declares the override via static bindings instead of boot`
- [ ] `it asserts the pgsql manifest declares no singletons for the 4 dialect interfaces (regression guard so override pattern stays safe)`

## Acceptance Criteria
- New test file lives at `packages/database-pgsql/tests/Module/DialectOverrideTest.php` (or `tests/Feature/` if that better matches existing convention — verify before placing).
- Fixture classes implementing the 4 dialect interfaces live in `packages/database-pgsql/tests/Fixtures/Variant/`.
- All 6 requirements have passing tests under `composer test`.
- `./vendor/bin/phpcs packages/database-pgsql/tests/` clean.
- `./vendor/bin/php-cs-fixer fix packages/database-pgsql/tests/ --dry-run --diff --config=.php-cs-fixer.php` clean.
- Test does not depend on real PostgreSQL being available — pure container resolution only.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
