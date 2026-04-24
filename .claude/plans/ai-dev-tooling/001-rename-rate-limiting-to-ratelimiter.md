# Task 001: Rename rate-limiting → ratelimiter

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Rename the `marko/rate-limiting` package to `marko/ratelimiter` to conform to the hyphen-only-for-siblings naming convention. This is the first of two rename tasks that establish the convention before new packages are created.

## Context
- Directory: `packages/rate-limiting/` → `packages/ratelimiter/`
- Composer name: `marko/rate-limiting` → `marko/ratelimiter`
- Namespace: `Marko\RateLimiting\*` → `Marko\RateLimiter\*`
- Patterns to follow: Standard PSR-4 rename; keep all class names intact unless they contained the namespace token.

## Requirements (Test Descriptions)
- [x] `it has package at packages/ratelimiter/ with correct directory structure`
- [x] `it declares composer.json name as marko/ratelimiter`
- [x] `it uses PSR-4 namespace Marko\\RateLimiter\\ pointing to src/`
- [x] `it has no remaining references to marko/rate-limiting in its own composer.json`
- [x] `it has no remaining Marko\\RateLimiting namespace declarations in src/ or tests/`
- [x] `it passes its existing Pest test suite after rename`

## Acceptance Criteria
- Directory moved, composer.json updated, namespace updated
- Package's own tests pass (`./vendor/bin/pest packages/ratelimiter/tests/ --parallel`)
- No breakage to other packages yet (those references handled in task 002)

## Implementation Notes
(Filled in by programmer during implementation)
