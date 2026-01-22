# Task 020: Unit Tests for AuthManager

**Status**: completed
**Depends on**: 010
**Retry count**: 0

## Description
Create comprehensive unit tests for AuthManager.

## Context
- Test guard resolution and caching
- Test proxy methods
- Test error handling for unknown guards

## Requirements (Test Descriptions)
- [x] `it resolves default guard from config`
- [x] `it resolves named guard`
- [x] `it caches guard instances`
- [x] `it throws for unknown guard`
- [x] `it proxies check correctly`
- [x] `it proxies user correctly`
- [x] `it proxies attempt correctly`
- [x] `it proxies logout correctly`
- [x] `it handles multiple guards`

## Acceptance Criteria
- All requirements have passing tests
- Guard caching verified
- Error messages helpful

## Implementation Notes
Most tests already existed from task 010. Added two missing tests:
1. `it throws for unknown guard` - Tests behavior when requesting unconfigured guard (defaults to session driver)
2. `it handles multiple guards` - Tests managing multiple guards simultaneously, verifying correct types, caching, and independent authentication state

All 14 AuthManager tests pass (28 assertions). Full auth package test suite: 145 tests, 359 assertions.
