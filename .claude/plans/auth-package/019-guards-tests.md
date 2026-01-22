# Task 019: Unit Tests for Guards

**Status**: pending
**Depends on**: 008, 009
**Retry count**: 0

## Description
Create comprehensive unit tests for SessionGuard and TokenGuard.

## Context
- Test authentication flow for each guard type
- Test edge cases and error conditions
- Use mock user providers

## Requirements (Test Descriptions)
- [ ] `SessionGuard stores user ID correctly`
- [ ] `SessionGuard retrieves user from session`
- [ ] `SessionGuard handles missing session`
- [ ] `SessionGuard regenerates session on login`
- [ ] `TokenGuard extracts bearer token`
- [ ] `TokenGuard handles missing header`
- [ ] `TokenGuard handles invalid token`
- [ ] `both guards return correct check status`
- [ ] `both guards handle logout correctly`

## Acceptance Criteria
- All requirements have passing tests
- Both guard types thoroughly tested
- Mocks used appropriately

## Implementation Notes
(Left blank - filled in by programmer during implementation)
