# Task 015: Comment Rate Limiter

**Status**: pending
**Depends on**: 013
**Retry count**: 0

## Description
Create a rate limiting service to prevent comment spam by limiting how frequently the same IP or email can submit comments. Uses the configured rate limit seconds from BlogConfig.

## Context
- Related files: `packages/blog/src/Services/CommentRateLimiterInterface.php`, `packages/blog/src/Services/CommentRateLimiter.php`
- Patterns to follow: Interface/implementation split
- Rate limit checked before accepting new comments
- Depends on CacheInterface (from marko/cache) - NOT a specific driver
- Works with any cache backend (file, Redis, Memcached, etc.)
- IP stored as hashed cache key with short TTL, GDPR compliant (processing, not storage)

## Requirements (Test Descriptions)
- [ ] `it allows comment when no recent comment from same IP`
- [ ] `it allows comment when no recent comment from same email`
- [ ] `it blocks comment when recent comment from same IP within limit`
- [ ] `it blocks comment when recent comment from same email within limit`
- [ ] `it uses configured rate_limit_seconds from BlogConfig`
- [ ] `it records comment submission for rate tracking`
- [ ] `it returns seconds remaining until next allowed comment`
- [ ] `it uses cache TTL for automatic cleanup no manual cleanup needed`
- [ ] `it hashes IP address for cache key not storing raw IP`

## Acceptance Criteria
- All requirements have passing tests
- CommentRateLimiterInterface defined for Preference swapping
- CommentRateLimiter depends on CacheInterface (not specific driver)
- Swappable to use any cache backend via DI
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
