# Task 002: RateLimiter Implementation and Tests

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Implement RateLimiter using CacheInterface for storing attempt counts with TTL-based decay windows.

## Context
- Uses CacheInterface to store/increment attempt counts
- Cache key format: "rate_limit:{key}" with TTL = decaySeconds
- attempt() checks current count, increments, returns RateLimitResult
- tooManyAttempts() quick check without incrementing
- Clear method to reset a key's limits

## Requirements (Test Descriptions)
- [ ] `it implements RateLimiterInterface`
- [ ] `it allows first attempt within limit`
- [ ] `it tracks attempt count across calls`
- [ ] `it blocks when max attempts exceeded`
- [ ] `it returns remaining attempts count`
- [ ] `it returns retry after seconds when blocked`
- [ ] `it resets after decay window expires`
- [ ] `it reports too many attempts without incrementing`
- [ ] `it clears rate limit for a key`
