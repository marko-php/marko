# Task 003: Fix Pest Expectations Autoloading

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Custom Pest expectations (toHaveDispatched, toHaveSent, toHavePushed, toHaveLogged, toHaveAttempted, toBeAuthenticated) are not loading for package-level tests. Root cause: Composer autoload_files loads `Expectations.php` (line 48) BEFORE Pest's `Functions.php` (line 49), so `function_exists('expect')` returns false and expectations never register. The root `tests/Pest.php` re-requires the file after Pest initializes (fixing root tests), but `packages/testing/tests/Pest.php` is empty.

## Context
- Related files:
  - `packages/testing/src/Pest/Expectations.php` — defines 6 expectations guarded by `if (function_exists('expect'))`
  - `packages/testing/tests/Pest.php` — currently empty (just comments)
  - `tests/Pest.php` — has `require_once` for Expectations.php (works because loaded after Pest init)
  - `vendor/composer/autoload_files.php` — loads Expectations.php at line 48, Pest Functions at line 49
- Failing tests: `packages/testing/tests/Unit/Pest/ExpectationsTest.php` (5 failures), `packages/testing/tests/Unit/Fake/FakeGuardTest.php` (4 failures)
- Fix: Add `require_once` for Expectations.php in `packages/testing/tests/Pest.php`, matching what `tests/Pest.php` already does. This ensures expectations register after Pest initializes regardless of Composer load order.

## Requirements (Test Descriptions)
- [x] `it registers toHaveDispatched expectation for FakeEventDispatcher` — passes after fix
- [x] `it registers toHaveSent expectation for FakeMailer` — passes after fix
- [x] `it registers toHaveLogged expectation for FakeLogger` — passes after fix
- [x] `it registers toHavePushed expectation for FakeQueue` — passes after fix
- [x] `it provides negated expectations (not->toHaveDispatched, etc.)` — passes after fix
- [x] `it throws clear error when expectation used on wrong type` — passes after fix
- [x] `it provides toHaveAttempted expectation` — passes after fix
- [x] `it provides toBeAuthenticated expectation` — passes after fix
- [x] `it rejects non-FakeGuard for toHaveAttempted` — passes after fix
- [x] `it rejects non-FakeGuard for toBeAuthenticated` — passes after fix

## Implementation Notes
- The task description suggested adding `require_once` to `packages/testing/tests/Pest.php`, but that file is never loaded by Pest's `BootFiles` bootstrapper (which only loads from the root `tests/` directory).
- The actual fix: changed `require_once` to `require` in `tests/Pest.php`. Composer loads `Expectations.php` via `require_once` (line 48 of autoload_files.php) BEFORE Pest's `Functions.php` (line 49), so the `if (function_exists('expect'))` guard fails and nothing is registered. When Pest then loads `tests/Pest.php` (after Pest is initialized), `require_once` was a no-op since the file was already included. Changing to `require` forces re-execution, and `expect()` is now available, so all 6 expectations register successfully.

## Acceptance Criteria
- All 10 Pest expectation tests pass (6 in ExpectationsTest + 4 in FakeGuardTest)
- Root-level tests that use expectations still pass
- No changes to the Expectations.php source file itself
