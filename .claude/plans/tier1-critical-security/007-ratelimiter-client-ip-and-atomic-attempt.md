# Task 007: F2 — Trusted-proxy client-IP resolution + atomic RateLimiter::attempt()

**Status**: pending
**Depends on**: [004, 005, 006]
**Retry count**: 0

## Description
Close the rate-limiter bypass and the non-atomic increment. Add `packages/ratelimiter/config/ratelimiter.php` with `trusted_proxies` (default `[]`). Add a `ClientIpResolver` that resolves the real client IP from `Request::ip()` (`REMOTE_ADDR`) and only honors the left-most `X-Forwarded-For` entry when `REMOTE_ADDR` is in `trusted_proxies`. Rewrite `RateLimitMiddleware::resolveKey()` to key off the resolved client IP and NEVER collapse distinct clients into a shared constant (no more literal `'unknown'`). Rewrite `RateLimiter::attempt()` to use the atomic `CacheInterface::increment()` from tasks 004/005 instead of get→compare→set.

## Context
- Related files:
  - `packages/ratelimiter/config/ratelimiter.php` (NEW)
  - `packages/ratelimiter/src/ClientIpResolver.php` (NEW) and its contract if one is warranted
  - `packages/ratelimiter/src/Middleware/RateLimitMiddleware.php` (`resolveKey()` ~53-59)
  - `packages/ratelimiter/src/RateLimiter.php` (`attempt()` ~20-49 — replace get→set with increment)
  - `packages/ratelimiter/module.php` (bindings/config registration)
  - `packages/ratelimiter/tests/Unit/` (existing `RateLimiterTest`, `RateLimitMiddlewareTest`)
  - Consumes `Request::ip()`/`server()` from task 006 and `CacheInterface::increment()` from tasks 004/005.
- Patterns to follow:
  - Config defaults ONLY in `config/ratelimiter.php`; getter throws when missing (no hardcoded fallback in code); `trusted_proxies` env reference, if any, only in the config file.
  - Use `marko/testing` fakes (e.g. `FakeConfigRepository`) for unit tests; the RateLimiter tests already use the real `ArrayCacheDriver` (which gains `increment()` in task 005).
  - Loud errors for misconfiguration.
- CRITICAL — trusted-proxy resolution edge cases (the whole point of F2; must be covered):
  - `X-Forwarded-For` is a comma-separated chain `client, proxy1, proxy2`. When `REMOTE_ADDR` is trusted, the REAL client is NOT simply "the left-most entry" — the left-most is attacker-controlled and can be spoofed. With a single trusted proxy, the correct client is the RIGHT-most entry that is NOT itself a trusted proxy (walk the chain from the right, skipping trusted proxies). The task description's "left-most XFF entry" is unsafe with multi-hop chains and known-trusted-proxy lists. Pick ONE documented strategy and test it: either (a) only honor XFF when REMOTE_ADDR is trusted and take the right-most untrusted hop, or (b) require an explicit trusted-proxy count. Do not blindly trust the left-most.
  - `REMOTE_ADDR` missing/empty: `Request::ip()` can return `null` (no `REMOTE_ADDR`, e.g. CLI). The resolver must still produce a deterministic, NON-shared key — but NOT a single global constant. If the IP is genuinely unresolvable, fail closed (treat as a distinct opaque key, or throw) rather than collapsing all clients into one bucket. Document the chosen behavior; add a test for the missing-`REMOTE_ADDR` case.
  - IPv6: trusted-proxy matching must compare normalized IPs (an IPv6 `REMOTE_ADDR` vs an IPv6 `trusted_proxies` entry). Add a test with an IPv6 `REMOTE_ADDR`.
  - Whitespace: XFF entries are `value, value` with spaces — trim each entry before comparison.
  - Validate XFF entries as IPs (`filter_var(..., FILTER_VALIDATE_IP)`); reject/skip garbage so a forged header cannot inject a non-IP key.
- Atomic `attempt()` ordering: increment FIRST, then compare the returned count to `maxAttempts`. Because the count keeps growing on blocked requests, do NOT use the count itself to derive `retryAfter` — read the remaining TTL via `getItem()` (still present) for the blocked-path `retryAfter`. Confirm the first allowed attempt returns `remaining = maxAttempts - 1` and the (maxAttempts+1)-th call is blocked.

## Requirements (Test Descriptions)
- [ ] `it resolves the client IP from REMOTE_ADDR when no proxies are trusted`
- [ ] `it ignores a forged X-Forwarded-For when REMOTE_ADDR is not a trusted proxy`
- [ ] `it honors X-Forwarded-For only when REMOTE_ADDR is in trusted_proxies`
- [ ] `it resolves the right-most untrusted hop from a multi-entry X-Forwarded-For chain`
- [ ] `it skips a forged non-IP X-Forwarded-For entry`
- [ ] `it matches an IPv6 REMOTE_ADDR against a trusted_proxies entry`
- [ ] `it fails closed (no shared global key) when REMOTE_ADDR is absent`
- [ ] `it never returns a shared constant key when the client IP is resolvable`
- [ ] `it derives distinct rate-limit keys for two clients behind different REMOTE_ADDR values`
- [ ] `it defaults trusted_proxies to an empty list from config`
- [ ] `it increments attempts atomically via the cache increment on attempt()`
- [ ] `it blocks the request once attempts reach maxAttempts`
- [ ] `it reports remaining attempts decreasing across successive attempts`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
