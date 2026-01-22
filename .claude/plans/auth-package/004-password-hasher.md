# Task 004: PasswordHasherInterface and BcryptPasswordHasher

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the PasswordHasherInterface contract for password hashing/verification and the BcryptPasswordHasher implementation using PHP's password_hash/password_verify.

## Context
- Related files: packages/hashing/ (similar pattern)
- Use PHP's built-in password_hash() and password_verify() functions
- Support configurable bcrypt cost

## Requirements (Test Descriptions)
- [x] `it creates PasswordHasherInterface with hash method`
- [x] `it creates PasswordHasherInterface with verify method`
- [x] `it creates PasswordHasherInterface with needsRehash method`
- [x] `it creates BcryptPasswordHasher implementing interface`
- [x] `it hashes password with bcrypt algorithm`
- [x] `it verifies correct password returns true`
- [x] `it verifies incorrect password returns false`
- [x] `it detects when rehash is needed`
- [x] `it supports configurable cost parameter`
- [x] `it uses default cost of 12`

## Acceptance Criteria
- All requirements have passing tests
- Uses PHP's secure password_hash with PASSWORD_BCRYPT
- Cost is configurable via constructor

## Implementation Notes
Created two files:
- `packages/auth/src/Contracts/PasswordHasherInterface.php` - Interface with hash(), verify(), and needsRehash() methods
- `packages/auth/src/Hashing/BcryptPasswordHasher.php` - Implementation using PHP's password_hash/password_verify with PASSWORD_BCRYPT algorithm

The BcryptPasswordHasher supports configurable cost via constructor with a default of 12. All methods are implemented using PHP's built-in secure password functions.
