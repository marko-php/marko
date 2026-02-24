# Task 003: RateLimitMiddleware Implementation and Tests

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Implement RateLimitMiddleware that integrates with the routing middleware pipeline. Returns 429 responses when rate limited, with standard rate limit headers.

## Context
- Implements MiddlewareInterface from marko/routing
- Uses RateLimiterInterface for checking/recording attempts
- Rate limit key defaults to client IP from Request
- Adds headers: X-RateLimit-Limit, X-RateLimit-Remaining, Retry-After
- Returns 429 response with JSON body when blocked

## Requirements (Test Descriptions)
- [ ] `it implements MiddlewareInterface`
- [ ] `it allows requests within rate limit`
- [ ] `it passes request to next handler when allowed`
- [ ] `it returns 429 response when rate limited`
- [ ] `it includes rate limit headers on allowed response`
- [ ] `it includes retry after header on blocked response`
- [ ] `it uses client IP as default rate limit key`
