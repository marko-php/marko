# Task 003: Orchestrator builds the resolved installed driver

**Status**: complete
**Depends on**: 002
**Retry count**: 0

## Description
Replace the hardcoded `is_dir(vendor/marko/docs-fts)` branch in
`InstallationOrchestrator::buildDocsIndex()` with `DocsDriverResolver`, so it
builds whichever known docs driver is installed and stays correct for any future
(including third-party) driver. Behavior when no driver is installed is unchanged
(it still logs the helpful hint).

## Context
- Edit: `packages/devai/src/Installation/InstallationOrchestrator.php`
  (`buildDocsIndex()`, ~lines 106-118 after PR #132).
- Inject `DocsDriverResolver` into the orchestrator constructor (promoted, readonly).
  `DocsDriverResolver` has no constructor args, so it autowires — no `module.php`
  binding needed and the container keeps resolving the orchestrator.
- Update `makeInstallOrchestrator()` in `InstallationOrchestratorTest.php` to pass a
  `new DocsDriverResolver()` (real object — it only reads files under the temp
  `$projectRoot`). This is the single construction site in tests; the container
  autowires it in production.
- `UpdateCommand` also calls `orchestrator->install()` (which calls `buildDocsIndex()`),
  so it inherits this behavior unchanged — no edit to `UpdateCommand` is required, but
  verify its tests still pass after the constructor signature changes.
- New logic: `$driver = $resolver->installedDriver($projectRoot)`. If null → keep the
  current `'[docs] no search driver installed — run `composer require marko/docs-fts`…'`
  log line and return. Otherwise run `$resolver->buildCommand($driver)` via the
  existing `CommandRunnerInterface` and log success/failure exactly as today.
- Keep the existing "build failed" log-line behavior and message shape.
- **CRITICAL test-fixture update (do not miss):** the resolver only treats a driver as
  installed if it appears in `vendor/marko/docs/known-drivers.php`. The existing
  orchestrator tests
  (`runs docs-fts:build during install when marko/docs-fts is in vendor` and
  `records a helpful log line when the docs index build fails`) currently create only
  `vendor/marko/docs-fts/` — with no registry file the resolver returns null and the
  build would be skipped, BREAKING those tests. Update those tests to ALSO write a stub
  `vendor/marko/docs/known-drivers.php` returning
  `['marko/docs-fts' => '...(recommended; ...)']` so the resolver resolves the driver.
  The `skips… when no driver is installed` test needs no registry file (empty set →
  null → hint), and that behavior is unchanged.

## Requirements (Test Descriptions)
- [x] `it builds the index for the installed docs driver resolved from the registry`
- [x] `it logs the install hint when no docs driver is installed`
- [x] `it records a helpful log line when the docs index build fails`
- [x] `it does not hardcode the docs-fts package when resolving the driver to build`

## Acceptance Criteria
- All requirements have passing tests; existing orchestrator tests still green
- `buildDocsIndex()` no longer references `docs-fts` literally (resolves via `DocsDriverResolver`)
- Code follows code standards

## Implementation Notes
- Injected `DocsDriverResolver` as a promoted property with a default of `new DocsDriverResolver()` (PHP 8.1+ new-in-initializer) so the container continues to autowire it and existing `UpdateCommandTest` stubs that extend the orchestrator need no changes.
- `buildDocsIndex()` now calls `$this->docsDriverResolver->installedDriver($projectRoot)` and uses `buildCommand($package)` for the command; the `$driver` name is extracted from the package for log messages.
- Updated 2 existing tests (`runs docs-fts:build during install` and `records a helpful log line when the docs index build fails`) to write a stub `vendor/marko/docs/known-drivers.php` registry so the resolver resolves `marko/docs-fts`.
- Added 4 new tests exercising `marko/docs-vec` as the installed driver to prove no `docs-fts` hardcoding remains in detection logic.
