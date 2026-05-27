# Task 016: Roll out known-drivers pattern — marko/view

**Status**: pending
**Depends on**: 001, 004, 006
**Retry count**: 0

## Description
Apply the rollout pattern to `marko/view`. After PR #92, **both `marko/view-latte` and `marko/view-twig` exist in the monorepo** with no Composer `conflict` between them (Marko uses runtime `BindingConflictException` for double-binding detection). Both drivers belong in `known-drivers.php`.

This task refactors the existing `NoDriverException` (currently has a `DRIVER_PACKAGES` const) to read from `known-drivers.php`.

## Context
- Interface: `Marko\View\ViewInterface`
- Drivers: `marko/view-twig`, `marko/view-latte` (both present in monorepo after PR #92)
- Recommended-first ordering: `view-twig` (broader ecosystem familiarity — established when view-twig was introduced in PR #88)
- **Pre-existing state (verified):**
  - Neither driver has a `conflict` block (PR #92 removed them)
  - `view/src/Exceptions/NoDriverException.php` has `private const array DRIVER_PACKAGES = ['marko/view-latte', 'marko/view-twig']`
  - Existing test `packages/view/tests/Exceptions/NoDriverExceptionTest.php` asserts against the const (must be updated)
- Skeleton's composer.json — task 024 populates the `suggest` block; this task ships known-drivers.php content that task 024 must mirror verbatim

**Description text for known-drivers.php:**
- `marko/view-twig` → `'Twig template engine driver (recommended for broader ecosystem familiarity)'`
- `marko/view-latte` → `'Latte template engine driver (compile-time safety, n:attribute syntax)'`

These descriptions must match what task 024 writes into skeleton's `suggest` block exactly (literal-equality CI check).

## Sub-steps
1. Create `packages/view/known-drivers.php` with both entries (Twig first)
2. Refactor `packages/view/src/Exceptions/NoDriverException.php` to read from known-drivers.php (remove `DRIVER_PACKAGES` const; add docs URL derivation as in task 003)
3. Update `packages/view/tests/Exceptions/NoDriverExceptionTest.php` to match the new shape
4. Add `packages/view/tests/KnownDriversValidationTest.php` using `KnownDriversValidator`
5. Add `marko/testing` to `packages/view/composer.json` `require-dev` if not already present

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing both view drivers`
- [ ] `it lists marko/view-twig first as the recommended driver`
- [ ] `view NoDriverException reads from known-drivers.php and includes docs URLs`
- [ ] `view NoDriverException no longer exposes a DRIVER_PACKAGES const`
- [ ] `validation test confirms skeleton suggest matches known-drivers.php (after task 024 runs; skip behavior holds before)`
- [ ] `existing NoDriverExceptionTest is updated to match new shape`

## Acceptance Criteria
- `packages/view/known-drivers.php` exists with both entries, view-twig first
- `DRIVER_PACKAGES` const removed from view's `NoDriverException`
- Existing `NoDriverExceptionTest` updated; all assertions match new output format
- New validation test passes
- All existing view, view-latte, view-twig tests still pass
- Description text in known-drivers.php matches task 024's skeleton suggest entries verbatim
- `marko/testing` in `packages/view/composer.json` `require-dev`
- Code follows code standards
