# Task 003: Update Blog Package Config

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Update BlogConfig class to remove fallback parameters and ensure blog.php config file has all required values.

## Context
- Related files: `packages/blog/src/Config/BlogConfig.php`, `packages/blog/config/blog.php`
- BlogConfig already updated to remove fallbacks, verify config file is complete
- Update test mocks in `packages/blog/tests/` to implement new getSiteName method

## Requirements (Test Descriptions)
- [ ] `it reads posts_per_page from config without fallback`
- [ ] `it reads comment_max_depth from config without fallback`
- [ ] `it reads comment_rate_limit_seconds from config without fallback`
- [ ] `it reads verification_token_expiry_days from config without fallback`
- [ ] `it reads verification_cookie_days from config without fallback`
- [ ] `it reads verification_cookie_name from config without fallback`
- [ ] `it reads route_prefix from config without fallback`
- [ ] `it reads site_name from config without fallback`
- [ ] `config file contains all required keys with defaults`

## Acceptance Criteria
- All requirements have passing tests
- BlogConfig has no fallback parameters
- blog.php config file has all values
- All test mocks implement getSiteName
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
