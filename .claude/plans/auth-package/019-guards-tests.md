# Task 019: Unit Tests for Guards

**Status**: completed
**Depends on**: 008, 009
**Retry count**: 0

## Description
Create comprehensive unit tests for SessionGuard and TokenGuard.

## Context
- Test authentication flow for each guard type
- Test edge cases and error conditions
- Use mock user providers
- Note: Some tests may already exist from task 008 and 009

## Requirements (Test Descriptions)
- [x] `SessionGuard stores user ID correctly`
- [x] `SessionGuard retrieves user from session`
- [x] `SessionGuard handles missing session`
- [x] `SessionGuard regenerates session on login`
- [x] `TokenGuard extracts bearer token`
- [x] `TokenGuard handles missing header`
- [x] `TokenGuard handles invalid token`
- [x] `both guards return correct check status`
- [x] `both guards handle logout correctly`

## Acceptance Criteria
- All requirements have passing tests
- Both guard types thoroughly tested
- Mocks used appropriately

## Implementation Notes
Most tests already existed from tasks 008 and 009. Added the following tests to TokenGuardTest.php:
- `it returns null when Authorization header is missing` - tests missing header case
- `it returns null when headers array is empty` - tests empty headers array
- `it handles logout as no-op for stateless token auth` - dedicated test for TokenGuard logout behavior

All 27 guard tests pass (42 assertions).
