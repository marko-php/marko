# Task 011: RememberTokenManager

**Status**: pending
**Depends on**: 004
**Retry count**: 0

## Description
Create the RememberTokenManager for "remember me" token generation and validation.

## Context
- Generates cryptographically secure tokens
- Tokens are hashed before storage
- Supports token lifetime configuration

## Requirements (Test Descriptions)
- [ ] `it generates cryptographically secure tokens`
- [ ] `it generates unique tokens each time`
- [ ] `it hashes token for storage`
- [ ] `it validates token with timing-safe comparison`
- [ ] `it checks token expiration`
- [ ] `it returns false for expired tokens`
- [ ] `it supports configurable token lifetime`
- [ ] `it clears expired tokens`

## Acceptance Criteria
- All requirements have passing tests
- Uses random_bytes for token generation
- Uses hash_equals for timing-safe comparison

## Implementation Notes
(Left blank - filled in by programmer during implementation)
