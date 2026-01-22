# Task 017: CLI auth:clear-tokens Command

**Status**: pending
**Depends on**: 011
**Retry count**: 0

## Description
Create the auth:clear-tokens CLI command for clearing expired remember tokens.

## Context
- Cleans up expired remember tokens from storage
- Can be run via cron for maintenance
- Follows existing CLI command patterns

## Requirements (Test Descriptions)
- [ ] `it has correct command name auth:clear-tokens`
- [ ] `it has description`
- [ ] `it clears expired tokens`
- [ ] `it reports number of tokens cleared`
- [ ] `it handles no expired tokens gracefully`
- [ ] `it supports --force flag for all tokens`

## Acceptance Criteria
- All requirements have passing tests
- Command follows CLI command patterns
- Provides useful output

## Implementation Notes
(Left blank - filled in by programmer during implementation)
