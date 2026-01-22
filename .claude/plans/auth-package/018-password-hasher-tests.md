# Task 018: Unit Tests for Password Hasher

**Status**: pending
**Depends on**: 004
**Retry count**: 0

## Description
Create comprehensive unit tests for PasswordHasherInterface and BcryptPasswordHasher.

## Context
- Test hashing, verification, and rehash detection
- Test configurable cost
- Ensure security best practices

## Requirements (Test Descriptions)
- [ ] `it hashes password to non-readable format`
- [ ] `it produces different hash for same password`
- [ ] `it verifies correct password`
- [ ] `it rejects incorrect password`
- [ ] `it detects rehash needed for lower cost`
- [ ] `it detects no rehash needed for same cost`
- [ ] `it uses custom cost when provided`
- [ ] `it validates minimum cost requirement`

## Acceptance Criteria
- All requirements have passing tests
- Tests cover edge cases
- Security properties verified

## Implementation Notes
(Left blank - filled in by programmer during implementation)
