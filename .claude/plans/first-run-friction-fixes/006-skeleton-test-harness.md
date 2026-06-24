# Task 006: Skeleton ships a working root test harness

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
A fresh `marko/skeleton` app ships no root `phpunit.xml` and no app `tests/`, yet `.claude/testing.md` documents `pest --parallel` as THE command — so `pest` fatals on a fresh install. Ship a root `phpunit.xml` wired for module-based discovery and a root `tests/Pest.php` using `Marko\Testing\TestCase`. Goal: `pest` runs green on a fresh install with zero setup.

## Context
- Related files:
  - `packages/skeleton/` (ships `app/.gitkeep`, `config/.gitkeep`, `modules/.gitkeep`, `storage/.gitkeep`; requires `marko/testing` + `pestphp/pest` in `require-dev`)
  - **Note:** `packages/skeleton/modules/.gitkeep` ALREADY exists — do not re-add it; just confirm `phpunit.xml` references resolve against the existing dirs.
  - New shipped files: `packages/skeleton/phpunit.xml`, `packages/skeleton/tests/Pest.php`
  - The skeleton package's OWN dev suite lives in `packages/skeleton/tests/` (`PackageStructureTest`, `KnownDriversSuggestParityTest`) — do not confuse the shipped app harness with the package's own tests. Add structural assertions to `PackageStructureTest`.
  - `phpunit.xml`: testsuites covering `app/*/tests`, `modules/*/tests`, and root `tests/`; suffix `Test.php`; source = `app` + `modules`. Reference `acta`'s working root `phpunit.xml` as a model.
- Pattern to follow: existing marko package `phpunit.xml` files; `tests/Pest.php` should use `uses(\Marko\Testing\TestCase::class)->in(__DIR__)` once Task 002 ships the class.

## Requirements (Test Descriptions)
- [x] `it ships a root phpunit.xml in the skeleton`
- [x] `it configures testsuites that discover app and modules test directories`
- [x] `it ships a root tests/Pest.php that references Marko\Testing\TestCase`
- [x] `it ships placeholders so every directory the phpunit.xml testsuites reference exists (app, modules, tests)`
- [x] `it runs pest successfully on a freshly scaffolded skeleton with no added test files`

## Acceptance Criteria
- `pest` runs (green, zero tests is acceptable) on a fresh skeleton with no setup.
- Adding an `app/{module}/tests` test is discovered without extra config.
- All requirements have passing tests; lint clean; no coverage decrease.

## Implementation Notes
- Created `packages/skeleton/phpunit.xml` with three testsuites (App, Modules, Tests) discovering `app`, `modules`, and `tests` with `suffix="Test.php"`. Source includes `app` and `modules` directories. Bootstrap uses `vendor/autoload.php`.
- Created `packages/skeleton/tests/Pest.php` as the shipped bootstrap for generated apps; uses `Marko\Testing\TestCase` via proper `use` import (php-cs-fixer reformatted from inline FQCN to import).
- All 5 structural assertions added to `packages/skeleton/tests/PackageStructureTest.php`.
- The "runs pest successfully" test creates a temp directory simulating a fresh skeleton (app/.gitkeep, modules/.gitkeep, tests/Pest.php only) and runs pest via subprocess with the monorepo vendor bootstrap, verifying exit code 0.
- `app/`, `modules/`, and `tests/` directories already existed in the skeleton; no new placeholder files needed.
