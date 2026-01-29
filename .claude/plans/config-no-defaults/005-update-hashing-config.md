# Task 005: Update Hashing Package Config

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Update HashConfig class to remove fallback parameters and ensure hashing.php config file has all required values.

## Context
- Related files: `packages/hashing/src/Config/HashConfig.php`, `packages/hashing/config/hashing.php`
- Check all getString/getInt calls and remove fallback parameters
- Ensure config file defines: default, hashers.bcrypt.cost, hashers.argon2id.memory, hashers.argon2id.time, hashers.argon2id.threads

## Requirements (Test Descriptions)
- [ ] `it reads default hasher from config without fallback`
- [ ] `it reads bcrypt cost from config without fallback`
- [ ] `it reads argon2id memory from config without fallback`
- [ ] `it reads argon2id time from config without fallback`
- [ ] `it reads argon2id threads from config without fallback`
- [ ] `config file contains all required keys with defaults`

## Acceptance Criteria
- All requirements have passing tests
- HashConfig has no fallback parameters
- hashing.php config file has all values
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
