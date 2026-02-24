# Task 001: Package Scaffolding, Contracts, and Value Objects

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the rate-limiting package with RateLimiterInterface, RateLimitResult value object, and package scaffolding.

## Context
- Namespace: `Marko\RateLimiting\`
- Package: `marko/rate-limiting`
- Dependencies: marko/core, marko/config, marko/cache, marko/routing
- Single package (not interface/driver split - there's only one sensible implementation)

## Requirements (Test Descriptions)
- [ ] `it defines RateLimiterInterface with attempt and tooManyAttempts methods`
- [ ] `it creates RateLimitResult with allowed remaining and retryAfter`
- [ ] `it reports allowed when remaining is positive`
- [ ] `it reports not allowed when remaining is zero`
- [ ] `it has marko module flag in composer.json`
