# Task 017: Roll out known-drivers pattern — marko/view

**Status**: pending
**Depends on**: 001, 005, 007
**Retry count**: 0

## Description
Apply the single-driver variant of the pilot pattern to `marko/view`. **Only one driver exists on disk: `marko/view-latte`.** Despite earlier plan notes mentioning `marko/view-twig`, that package does not exist in this monorepo and is OUT OF SCOPE for this plan. When a second driver (twig or otherwise) lands later, the entry is added to `known-drivers.php` and the validation tests automatically gain teeth.

This task refactors the existing `NoDriverException` (currently has a `DRIVER_PACKAGES` const listing only `marko/view-latte`) to read from `known-drivers.php`.

## Context
- Interface: `Marko\View\ViewInterface` (verified at `packages/view/src/ViewInterface.php`)
- Driver (single, currently): `marko/view-latte`
- **Pre-existing state (verified at plan creation):**
  - `view-latte/composer.json` does NOT have a `conflict` block (no sibling to conflict with yet)
  - `view/src/Exceptions/NoDriverException.php` has `private const array DRIVER_PACKAGES = ['marko/view-latte']` — single entry
  - Existing test `packages/view/tests/Exceptions/NoDriverExceptionTest.php` asserts against the const (must be updated)
- Skeleton's composer.json does NOT yet have a `suggest` block — it will be created in task 025

**Description text for known-drivers.php:**
- `marko/view-latte` → `'Latte template engine driver (compile-time safety, n:attribute syntax)'`

This description must match what task 025 writes into skeleton's `suggest` block exactly (literal-equality CI check enforced by `KnownDriversValidator::assertSkeletonSuggestContainsAll`). Task 025 must use the same string.

## Sub-steps
1. Create `packages/view/known-drivers.php` with the single `marko/view-latte` entry
2. Refactor `packages/view/src/Exceptions/NoDriverException.php` to read from known-drivers.php (remove `DRIVER_PACKAGES` const; add `noDriverInstalled()` factory using the same shape as task 003)
3. Update `packages/view/tests/Exceptions/NoDriverExceptionTest.php` to match the new shape (remove `DRIVER_PACKAGES` assertion, add assertions for docs URL inclusion)
4. Add `packages/view/tests/KnownDriversValidationTest.php` using `KnownDriversValidator` (vacuous conflict assertion since only one driver — same as single-driver tasks 018-024)
5. Add `marko/testing` to `packages/view/composer.json` `require-dev` (needed for the validation test)

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing marko/view-latte`
- [ ] `view NoDriverException reads from known-drivers.php and includes a docs URL`
- [ ] `view NoDriverException no longer exposes a DRIVER_PACKAGES const`
- [ ] `validation test passes (vacuous conflict assertion with one driver)`
- [ ] `existing NoDriverExceptionTest is updated to match new shape`

## Acceptance Criteria
- `packages/view/known-drivers.php` exists with one entry (`marko/view-latte`)
- `DRIVER_PACKAGES` const removed from view's `NoDriverException`
- Existing `NoDriverExceptionTest` updated; all assertions match new output format
- New validation test passes
- All existing view, view-latte tests still pass
- `marko/testing` added as `require-dev` in `packages/view/composer.json` if not already present
- Code follows code standards
