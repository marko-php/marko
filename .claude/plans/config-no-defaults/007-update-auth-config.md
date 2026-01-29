# Task 007: Update Auth Package Config

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Update AuthConfig class to remove fallback parameters and ensure auth.php config file has all required values.

## Context
- Related files: `packages/auth/src/Config/AuthConfig.php`, `packages/auth/config/auth.php`
- Check all getString/getInt calls and remove fallback parameters
- Ensure config file defines: default.guard, default.provider, password.bcrypt.cost

## Requirements (Test Descriptions)
- [ ] `it reads default guard from config without fallback`
- [ ] `it reads default provider from config without fallback`
- [ ] `it reads bcrypt cost from config without fallback`
- [ ] `config file contains all required keys with defaults`

## Acceptance Criteria
- All requirements have passing tests
- AuthConfig has no fallback parameters
- auth.php config file has all values
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
