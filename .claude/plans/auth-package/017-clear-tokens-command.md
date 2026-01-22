# Task 017: CLI auth:clear-tokens Command

**Status**: completed
**Depends on**: 011
**Retry count**: 0

## Description
Create the auth:clear-tokens CLI command for clearing expired remember tokens.

## Context
- Cleans up expired remember tokens from storage
- Can be run via cron for maintenance
- Follows existing CLI command patterns

## Requirements (Test Descriptions)
- [x] `it has correct command name auth:clear-tokens`
- [x] `it has description`
- [x] `it clears expired tokens`
- [x] `it reports number of tokens cleared`
- [x] `it handles no expired tokens gracefully`
- [x] `it supports --force flag for all tokens`

## Acceptance Criteria
- All requirements have passing tests
- Command follows CLI command patterns
- Provides useful output

## Implementation Notes
- Created `RememberTokenStorageInterface` in `packages/auth/src/Contracts/` with `clearExpiredTokens()` and `clearAllTokens()` methods
- Created `ClearTokensCommand` in `packages/auth/src/Command/` that depends on the storage interface
- Command uses `#[Command]` attribute for name and description
- Supports `--force` flag to clear ALL tokens (not just expired)
- Provides informative output: count of cleared tokens or message when none found
- Follows existing CLI command patterns (GarbageCollectCommand, ClearCommand)
