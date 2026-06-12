# Task 004: F2 — Add atomic increment() to CacheInterface

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
Add an atomic `increment(string $key, int $ttl): int` to `CacheInterface` (`marko/cache`). It atomically increments the stored integer at `$key` (creating it at 1 if absent), (re)applies the TTL, and returns the new value. This is the contract the rate limiter needs to stop the non-atomic get→compare→set race; the driver implementations land in task 005.

## Context
- Related files:
  - `packages/cache/src/Contracts/CacheInterface.php` (add method to the interface)
  - `packages/cache/tests/` (interface/contract test, mirroring existing `ContractsTest`-style checks)
- Patterns to follow:
  - Existing interface methods carry `@throws InvalidKeyException` and full type declarations.
  - Keep the contract minimal and documented (PHPDoc describing atomicity + first-call TTL semantics).
- CRITICAL — contract addition breaks all implementers:
  - Adding `increment()` to `CacheInterface` forces EVERY implementer to define it. Beyond the three drivers (task 005), there are two anonymous `CacheInterface` classes in `packages/health/tests/Unit/CacheHealthCheckTest.php` (lines 12 and 88). They will fatal ("must implement increment") the moment the interface changes. Task 005 updates those test doubles; this task must document the impact in the PHPDoc/notes so the orchestrator does not treat 004 as fully green until 005 lands. Do NOT run the full `marko/health` suite as a gate on this task alone.
  - TTL semantics (document in the interface PHPDoc): the TTL is applied when the counter is FIRST created (return value 1). It is NOT reset on subsequent increments — resetting on every call turns a fixed rate-limit window into a never-closing window. Word the PHPDoc so drivers implement first-call-only TTL.

## Requirements (Test Descriptions)
- [x] `it declares an increment method on CacheInterface accepting a string key and int ttl`
- [x] `it declares increment as returning int`
- [x] `it documents increment as throwing InvalidKeyException`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added `increment(string $key, int $ttl): int` to `CacheInterface` with PHPDoc documenting atomicity and first-call-only TTL semantics.
- Created `packages/cache/tests/Unit/Contracts/CacheInterfaceTest.php` with 3 reflection-based tests verifying the method signature, return type, and @throws tag.
- KNOWN BREAKAGE: Adding `increment()` to `CacheInterface` breaks all implementers. The anonymous `CacheInterface` test doubles in `packages/health/tests/Unit/CacheHealthCheckTest.php` (lines 12 and 88) will fatal until task 005 updates them. Do NOT gate task 004 completion on the health suite — that is task 005's responsibility.
