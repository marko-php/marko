# Task 004: Update Session Package Config

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Update SessionConfig class to remove fallback parameters and ensure session.php config file has all required values.

## Context
- Related files: `packages/session/src/Config/SessionConfig.php`, `packages/session/config/session.php`
- Check all getString/getInt/getBool calls and remove fallback parameters
- Ensure config file defines: driver, lifetime, expire_on_close, path, cookie.name, cookie.path, cookie.domain, cookie.secure, cookie.httponly, cookie.samesite, gc_probability, gc_divisor

## Requirements (Test Descriptions)
- [ ] `it reads driver from config without fallback`
- [ ] `it reads lifetime from config without fallback`
- [ ] `it reads expire_on_close from config without fallback`
- [ ] `it reads path from config without fallback`
- [ ] `it reads all cookie settings from config without fallback`
- [ ] `it reads gc_probability from config without fallback`
- [ ] `it reads gc_divisor from config without fallback`
- [ ] `config file contains all required keys with defaults`

## Acceptance Criteria
- All requirements have passing tests
- SessionConfig has no fallback parameters
- session.php config file has all values
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
