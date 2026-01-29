# Task 006: Update Cache Package Config

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Update CacheConfig class to remove fallback parameters and ensure cache.php config file has all required values.

## Context
- Related files: `packages/cache/src/Config/CacheConfig.php`, `packages/cache/config/cache.php`
- Check all getString/getInt calls and remove fallback parameters
- Ensure config file defines: driver, path, default_ttl

## Requirements (Test Descriptions)
- [ ] `it reads driver from config without fallback`
- [ ] `it reads path from config without fallback`
- [ ] `it reads default_ttl from config without fallback`
- [ ] `config file contains all required keys with defaults`

## Acceptance Criteria
- All requirements have passing tests
- CacheConfig has no fallback parameters
- cache.php config file has all values
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
