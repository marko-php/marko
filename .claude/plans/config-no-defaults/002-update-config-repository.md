# Task 002: Update ConfigRepository Implementation

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Update ConfigRepository to implement the new interface without defaults. All getters should throw ConfigNotFoundException when a key is not found.

## Context
- Related files: `packages/config/src/ConfigRepository.php`, `packages/config/src/Exceptions/ConfigNotFoundException.php`
- Must update implementation to match new interface signatures
- Update tests in `packages/config/tests/`

## Requirements (Test Descriptions)
- [ ] `it throws ConfigNotFoundException when get is called with missing key`
- [ ] `it throws ConfigNotFoundException when getString is called with missing key`
- [ ] `it throws ConfigNotFoundException when getInt is called with missing key`
- [ ] `it throws ConfigNotFoundException when getBool is called with missing key`
- [ ] `it throws ConfigNotFoundException when getFloat is called with missing key`
- [ ] `it throws ConfigNotFoundException when getArray is called with missing key`
- [ ] `it returns value when key exists for all getter types`
- [ ] `it still supports scoped config cascade for missing scope keys`

## Acceptance Criteria
- All requirements have passing tests
- No default parameters in implementation
- Existing scope cascade behavior preserved
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
