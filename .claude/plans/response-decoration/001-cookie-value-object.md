# Task 001: Cookie Value Object

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create `Marko\Routing\Http\Cookie`, an immutable value object describing a single HTTP cookie and rendering itself as a `Set-Cookie` header value. This is the foundation for first-class cookie support on `Response`; nothing else in the plan can proceed without it.

## Context
- New file: `packages/routing/src/Http/Cookie.php`
- New test: `packages/routing/tests/Http/CookieTest.php`
- Attributes needed by `Session`: name, value, expires, path, domain, secure, httpOnly, sameSite
- Follow the existing style in `packages/routing/src/Http/` — constructor property promotion, `declare(strict_types=1)`, full type declarations, no final classes
- This class MAY be `readonly` — the readonly restriction only blocks `Response`, which needs `clone`-based decoration. `Cookie` is constructed whole and never decorated.
- Loud errors: a malformed cookie name must throw, not silently emit a broken header. Add a `Cookie`-specific exception in `packages/routing/src/Exceptions/` following the existing exception style.

**Three encoding/semantics decisions that must be made in this class, not left to callers:**

1. **Value encoding.** A `Set-Cookie` value cannot contain `;`, `,`, whitespace or control characters. Session IDs happen to be safe but arbitrary application cookie values are not. Decide raw vs. `urlencode` here and cover it with a test — a caller passing `a; b` must not be able to inject a second cookie attribute.
2. **No-expiry (browser-session) cookies.** `SessionConfig::expireOnClose()` maps to `lifetime => 0`, which means a cookie with **no** `Expires` and **no** `Max-Age` attribute at all — not `Expires: <epoch>`. The `expires` attribute needs an explicit representation for "omit entirely" (`null` or `0`).
3. **`SameSite=None` requires `Secure`.** Browsers silently drop a `SameSite=None` cookie that is not `Secure`. Per the loud-errors principle this must throw at construction rather than emit a cookie that vanishes.

## Requirements (Test Descriptions)
- [x] `it renders name and value as a set-cookie string`
- [x] `it renders the path attribute when provided`
- [x] `it renders the domain attribute when provided`
- [x] `it renders expires as an rfc 7231 formatted date`
- [x] `it omits the expires attribute entirely for a browser session cookie`
- [x] `it renders secure and httponly flags only when enabled`
- [x] `it renders the samesite attribute when provided`
- [x] `it encodes a value containing characters that are illegal in a set-cookie header`
- [x] `it throws when the cookie name contains an invalid character`
- [x] `it throws when samesite is none without the secure flag`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Value encoding: `rawurlencode()` applied to the cookie value in `toSetCookieString()`. This strips `;`, `,`, whitespace, and control characters from the rendered header, preventing attribute injection from arbitrary application values while leaving safe values (e.g. session IDs) unchanged.
- No-expiry cookies: `expires` is `?int` (unix timestamp), defaulting to `null`. Both `null` and `0` are treated as "omit the `Expires` attribute entirely" — `0` is included so a raw `SessionConfig::lifetime()` value (which is `0` for expire-on-close) can be passed straight through by a future caller without conversion.
- `SameSite=None` without `secure: true` throws `CookieException::sameSiteNoneRequiresSecure()` at construction (loud errors, not a silently dropped cookie).
- Cookie name validation rejects control characters, whitespace, and RFC 2616 token separators (`()<>@,;:\"/[]?={}`) via `CookieException::invalidName()`; empty names are also rejected.
- Used a literal `gmdate()` format string (`'D, d M Y H:i:s \G\M\T'`) instead of the `DATE_RFC7231` constant, which is deprecated as of PHP 8.5.
- New exception `Marko\Routing\Exceptions\CookieException` added following the existing `MarkoException` static-factory style used by `RouteException`/`RouteConflictException`.
- Full suite (`composer test` equivalent: `pest -c phpunit.xml --parallel --exclude-group=integration-destructive`) passes: 6908 passed, 0 failed. `phpcs`, `php-cs-fixer`, and `phpstan` all clean on touched files.
