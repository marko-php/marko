# Task 002: DocsDriverResolver (registry-driven driver detection)

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Add a service that answers "what docs-search driver state is this project in?"
by reading the existing `marko/docs` known-drivers registry and checking the
project's `vendor/` — without hardcoding `docs-fts`. This is what lets both the
orchestrator and the install command stay driver-agnostic.

## Context
- New file: `packages/devai/src/Installation/DocsDriverResolver.php`.
- Registry source: `marko/docs` ships `known-drivers.php` returning
  `array<string, string>` (package => description). Read it from the installed
  contract package at `{projectRoot}/vendor/marko/docs/known-drivers.php`. Confirmed
  real shape (do not assume otherwise): a single entry today —
  `['marko/docs-fts' => 'Full-text documentation search driver (recommended; SQLite FTS5, zero infrastructure)']`.
  In a real installed project the contract lives at `vendor/marko/docs/`, so the
  `{projectRoot}/vendor/marko/docs/known-drivers.php` path is correct; the monorepo
  path (`packages/docs/known-drivers.php`) is only used by the package's own tests
  and is irrelevant to the resolver. If the file is absent (e.g. devai installed
  without the contract resolved yet, or a non-standard layout), `require` must NOT
  be attempted — guard with `is_file()` and treat the known set as empty (degrade
  gracefully, no throw, no warning). **Do NOT change the registry shape** — it is a
  shared convention validated by
  `packages/testing/src/KnownDrivers/KnownDriversValidator.php` and parity tests
  across ~20 packages.
- A driver `marko/<name>` is "installed" when `is_dir({projectRoot}/vendor/marko/<name>)`
  (same check the orchestrator uses today for `docs-fts`).
- Methods (typed):
  - `installedDriver(string $projectRoot): ?string` — first known package whose vendor dir exists, else null.
  - `uninstalledDrivers(string $projectRoot): list<string>` — known packages whose vendor dir is absent.
  - `recommendedUninstalled(string $projectRoot): ?string` — among uninstalled, the one whose
    registry description contains the word `recommended` (case-insensitive substring match —
    the real description reads `(recommended; SQLite FTS5…)`, so match on `recommended`, NOT the
    literal `(recommended ` with a trailing space). Else the first uninstalled, else null.
  - `buildCommand(string $package): string` — derive the index-build command from the package
    name: strip the `marko/` (or any `<vendor>/`) prefix and append `:build`
    (e.g. `marko/docs-fts` → `docs-fts:build`). NOTE: this convention holds for the
    only known driver today (`marko/docs-fts` → `docs-fts:build`, which matches the
    command the orchestrator already runs). If a future driver's build command name
    diverges from its short package name, this derivation breaks — but that is out of
    scope here and acceptable while there is one known driver. Keep the derivation
    simple (no per-package overrides).
- Standards: `declare(strict_types=1)`, promotion, type decls, no `final`.

## Requirements (Test Descriptions)
- [ ] `it returns the installed driver when its vendor directory exists`
- [ ] `it returns null for installed driver when no known driver is present`
- [ ] `it lists known drivers that are not installed`
- [ ] `it picks the recommended uninstalled driver by the description convention`
- [ ] `it derives the build command from the package name`
- [ ] `it treats the known set as empty when the contract registry file is absent`

## Acceptance Criteria
- All requirements have passing tests (use a temp project root with fake `vendor/marko/*` dirs and a stub `known-drivers.php`)
- Registry file shape is read-only; no change to `marko/docs` or the shared validator
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
