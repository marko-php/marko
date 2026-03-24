# Plan: Fix All Test Failures

## Created
2026-03-24

## Status
completed

## Objective
Fix all 44 test failures across the framework: 1 release script test, 1 Latte engine test, 10 Pest expectations tests (6 in ExpectationsTest + 4 in FakeGuardTest), and 32 README content tests across 8 packages.

## Scope

### In Scope
- Fix release script test to match current script behavior
- Fix Latte engine factory: migrate deprecated `setStrictTypes()` to `setFeature()`, fix test reflection
- Fix Pest expectations autoloading so they register after Pest initializes
- Write README content for 8 packages to satisfy test expectations

### Out of Scope
- Adding new test coverage beyond fixing existing failures
- Changing package functionality
- Updating docs site content (only package READMEs)

## Success Criteria
- [ ] All 44 previously failing tests pass
- [ ] No regressions in existing passing tests
- [ ] `./vendor/bin/pest --parallel` exits cleanly
- [ ] All README content is accurate to actual package code

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Fix release script test | - | completed |
| 002 | Fix Latte engine factory test | - | completed |
| 003 | Fix Pest expectations autoloading | - | completed |
| 004 | Write amphp README | - | completed |
| 005 | Write authentication README | - | completed |
| 006 | Write testing README | 003 | completed |
| 007 | Write blog README | - | completed |
| 008 | Write database READMEs (database, database-mysql, database-pgsql) | - | completed |
| 009 | Write pubsub READMEs (pubsub, pubsub-pgsql, pubsub-redis) | - | completed |

## Architecture Notes
- Pest expectations load order: Composer autoload_files loads Expectations.php (line 48) BEFORE Pest's Functions.php (line 49). The `if (function_exists('expect'))` guard prevents registration. Fix must ensure expectations register after Pest initializes.
- READMEs must be based on actual package source code, not fabricated content.
- All packages have docs pages, but tests expect more than slim READMEs — follow what each test specifically checks for.

## Risks & Mitigations
- README content accuracy: Each task must read source code before writing README sections
- Pest expectations fix could break root-level tests: Verify both root and package tests pass after fix
