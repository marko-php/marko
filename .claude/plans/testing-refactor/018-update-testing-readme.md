# Task 018: Update marko/testing README with FakeGuard documentation

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Update the marko/testing package README to document FakeGuard, its API, assertion methods, and Pest expectations. Follow the existing README structure for other fakes.

## Context
- Related files:
  - `packages/testing/README.md` — update with FakeGuard section
  - `packages/testing/src/Fake/FakeGuard.php` — reference for API documentation
  - `packages/testing/src/Pest/Expectations.php` — reference for Pest expectation docs
- Patterns to follow:
  - Follow existing README structure for documenting fakes
  - Include: constructor parameters, assertion methods, Pest expectations, usage example
  - Keep prose minimal, let code speak

## Requirements (Test Descriptions)
- [ ] `it documents FakeGuard in the available fakes table`
- [ ] `it includes FakeGuard usage example with guard configuration`
- [ ] `it documents FakeGuard assertion methods`
- [ ] `it documents toHaveAttempted and toBeAuthenticated Pest expectations`

## Acceptance Criteria
- README accurately reflects FakeGuard API as implemented in task 001
- Follows existing README format and style
- ReadmeTest passes (if it validates README structure)
- Run: `./vendor/bin/pest packages/testing/tests/ --parallel`
