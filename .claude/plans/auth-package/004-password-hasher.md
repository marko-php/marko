# Task 004: PasswordHasherInterface and BcryptPasswordHasher

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create the PasswordHasherInterface contract for password hashing/verification and the BcryptPasswordHasher implementation using PHP's password_hash/password_verify.

## Context
- Related files: packages/hashing/ (similar pattern)
- Use PHP's built-in password_hash() and password_verify() functions
- Support configurable bcrypt cost

## Requirements (Test Descriptions)
- [ ] `it creates PasswordHasherInterface with hash method`
- [ ] `it creates PasswordHasherInterface with verify method`
- [ ] `it creates PasswordHasherInterface with needsRehash method`
- [ ] `it creates BcryptPasswordHasher implementing interface`
- [ ] `it hashes password with bcrypt algorithm`
- [ ] `it verifies correct password returns true`
- [ ] `it verifies incorrect password returns false`
- [ ] `it detects when rehash is needed`
- [ ] `it supports configurable cost parameter`
- [ ] `it uses default cost of 12`

## Acceptance Criteria
- All requirements have passing tests
- Uses PHP's secure password_hash with PASSWORD_BCRYPT
- Cost is configurable via constructor

## Implementation Notes
(Left blank - filled in by programmer during implementation)
