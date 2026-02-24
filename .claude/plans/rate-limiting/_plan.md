# Plan: rate-limiting Package

## Created
2026-02-23

## Status
ready

## Objective
Build `marko/rate-limiting` — rate limiter using cache backend with middleware integration.

## Scope
### In Scope
- RateLimiter service using CacheInterface
- RateLimitResult value object (allowed, remaining, retryAfter)
- RateLimitMiddleware implementing MiddlewareInterface
- Fixed window rate limiting algorithm

### Out of Scope
- Sliding window or token bucket algorithms
- Per-user rate limiting (just key-based)
- Redis-specific optimizations

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding, contracts, and value objects | - | pending |
| 002 | RateLimiter implementation and tests | 001 | pending |
| 003 | RateLimitMiddleware implementation and tests | 002 | pending |

## Architecture Notes
- RateLimiter::attempt(key, maxAttempts, decaySeconds): RateLimitResult
- Uses cache to store attempt counts with TTL = decaySeconds
- RateLimitResult: allowed (bool), remaining (int), retryAfter (?int seconds)
- Middleware reads config for max attempts and decay, uses request IP as key
- Returns 429 Too Many Requests response when rate limited
