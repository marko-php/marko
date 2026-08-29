# Task 007: Architecture Test Forbidding the Rebuild Pattern

**Status**: completed
**Depends on**: 004
**Retry count**: 0

## Description
Add a build-gating architecture test that fails when any middleware constructs a `Response` from another response's accessors. Without this, the rebuild pattern silently returns the first time someone adds a header the obvious-looking way, and the SSE bug comes back.

## Context

### Location: the root monorepo suite, NOT the routing package
- New test: `/Users/markshust/Sites/marko/tests/MiddlewareDecorationTest.php`
- Fixtures: `/Users/markshust/Sites/marko/tests/Fixtures/MiddlewareDecoration/`

The original draft put this in `packages/routing/tests/`. That is wrong on two counts: `packages/routing/tests/` is published to the standalone read-only `marko/routing` repo (see `tests/SplitWorkflowTest.php` and `tests/PackagingTest.php`), where `packages/` does not exist — the test would either fail or, far worse, scan zero files and pass vacuously forever. And `marko/routing` has no business reaching into `marko/security` and `marko/inertia`.

`phpunit.xml` already declares a `Monorepo` testsuite pointing at the root `tests/` directory. That is where cross-package architecture tests live (`tests/PackagingTest.php`, `tests/CiWorkflowTest.php`).

### The detection rule: position relative to `$next()`, not argument provenance

The original draft proposed detecting "a `new Response(` whose arguments are fed from another response's `body()` / `statusCode()` / `headers()`". **That heuristic returns a false negative on the exact code this test must catch.** `InertiaMiddleware` does:

```php
$headers = $response->headers();
$headers['Vary'] = $this->mergeVaryHeader($headers['Vary'] ?? null);
...
return new Response(body: $response->body(), statusCode: $statusCode, headers: $headers);
```

The variable indirection defeats argument matching for `headers`, and a body-less variant would be missed entirely. A guard test that goes green while the pattern is present is the worst possible outcome.

**Use this rule instead: within a single method body, no `new *Response(` may appear after the first `$next(` call.**

Verified against every middleware in the repo. Every legitimate fresh response is constructed either *before* `$next()` or in a separate helper method:

| Site | Construct | Position | Verdict |
|---|---|---|---|
| `inertia/InertiaMiddleware.php:28` | fresh 409 | before `$next()` at :39 | allowed |
| `cors/CorsMiddleware.php:48` | fresh 204 preflight | before `$next()` | allowed |
| `security/CorsMiddleware.php:34` | fresh 204 preflight | before `$next()` at :42 | allowed |
| `ratelimiter/RateLimitMiddleware.php:36` | fresh 429 | before `$next()` at :49 | allowed |
| `authentication/AuthMiddleware.php:49` | fresh 401 | separate helper method | allowed |
| `admin-auth/AdminAuthMiddleware.php:96` | fresh 403 | separate helper method | allowed |
| `authorization/AuthorizationMiddleware.php:88,107` | fresh 401/403 | separate helper methods | allowed |
| the six sites migrated in task 004 | rebuild | after `$next()` | **caught** |

`LayoutMiddleware` calls `$next()` then returns `$this->layoutProcessor->process(...)` — no `new Response`, passes cleanly.

Keep the `->body()` / `->statusCode()` / `->headers()` argument check as a **secondary** signal that strengthens the failure message, not as the primary rule.

### Scanning mechanics
- **There is no `nikic/php-parser` in this repo** (checked the root `composer.json`). Use `token_get_all()`, not regex — regex cannot reliably track method boundaries or distinguish `new Response(` in a comment or string.
- **Do not use a `**` glob.** PHP's `glob()` has no `**` support at all, so `packages/*/src/**/Middleware/*.php` silently misses `packages/security/src/Middleware/SecurityHeadersMiddleware.php`. Walk with `RecursiveDirectoryIterator` over `packages/*/src`.
- Key discovery off `implements MiddlewareInterface` rather than the directory name, so a middleware placed outside a `Middleware/` directory is not silently exempt.
- **The detector must be a class or callable taking an explicit list of files.** If it hardcodes the repo scan, the deliberately-bad fixture used by the negative-case test gets picked up by the repo-wide scan and the suite fails against itself. The repo-wide test passes the discovered file list; the fixture tests pass a fixture path.
- The fixtures directory must be excluded from the repo-wide scan (it lives under `tests/`, not `packages/*/src`, so this falls out naturally — but assert it).
- The failure message must name the offending file **and line** and explain the fix ("decorate with `withHeader()` / `withHeaders()` / `withStatus()` instead of rebuilding"), per the loud-errors principle.

## Requirements (Test Descriptions)
- [x] `it passes for the current middleware in the repository`
- [x] `it discovers middleware that live directly under src slash middleware`
- [x] `it fails when a middleware constructs a response after calling next`
- [x] `it fails when a middleware rebuilds a response from headers held in a local variable`
- [x] `it allows a middleware to construct a genuinely new response before calling next`
- [x] `it allows a middleware to construct a response in a helper method`
- [x] `it reports the offending file and line and a suggested fix when it fails`

## Acceptance Criteria
- All requirements have passing tests
- The test passes against the migrated middleware from task 004
- The test lives in the root `Monorepo` testsuite and does not ship inside any package
- Detection uses `token_get_all()` and `RecursiveDirectoryIterator`, not regex and not `glob()` with `**`
- Code follows code standards

## Implementation Notes

- Test file: `tests/MiddlewareDecorationTest.php` (root `Monorepo` testsuite).
- Support classes (`require_once`'d directly from the test file — root `tests/` has
  no PSR-4 autoload mapping in `composer.json`, unlike each package's own `tests/`):
  - `tests/Support/MiddlewareDecoration/MiddlewareDiscovery.php` — walks `packages/*/src`
    with `RecursiveDirectoryIterator`/`RecursiveIteratorIterator` (no `glob()`), then
    tokenizes each file with `PhpToken::tokenize()` and looks for a real `implements`
    clause naming `MiddlewareInterface` (or a qualified name ending in it).
  - `tests/Support/MiddlewareDecoration/MiddlewareDecorationDetector.php` — tokenizes
    each file with `PhpToken::tokenize()`, tracks brace depth to find method/closure
    body boundaries, and flags any `new *Response(` whose class name ends in
    `Response` that appears after the first "consuming" `$next(` call within the same
    method body. A secondary signal (`->body()`/`->statusCode()`/`->headers()` calls
    earlier in the same method) enriches the failure message but is not the trigger.
- Fixtures: `tests/Fixtures/MiddlewareDecoration/{RebuildAfterNextMiddleware,
  RebuildFromLocalVariableMiddleware,FreshResponseBeforeNextMiddleware,
  HelperMethodResponseMiddleware}.php`. These implement the real
  `Marko\Routing\Middleware\MiddlewareInterface` (autoloaded via the `vendor/marko/routing`
  path symlink) so the fixtures exercise realistic code, not stand-ins.

### Bug found and fixed during implementation: bare `return $next(...)` must not "consume" $next()

Initial implementation flagged the *first* `$next(` call textually in a method body as the
marker for "everything after this is a rebuild if it's `new Response(`." That produced false
positives on real repo code: `cors/CorsMiddleware.php`, `security/CorsMiddleware.php`, and
`authentication/AuthMiddleware.php` all have an **earlier** short-circuit
`return $next($request);` guard clause (e.g. "no Origin header, pass through") that occurs
*before* a later, unrelated, genuinely-fresh `new Response(` (the OPTIONS-preflight 204, or the
plain-401 fallback) — even though the task's own table classifies these as "before $next()"
positionally.

Fix: a bare `return $next($request);` statement (nothing chained, nothing assigned) terminates
its branch immediately and has no bearing on sibling code reached only via a different branch
that never called `$next()`. The detector now only marks `$next()` as "consumed" for the rest of
the method when its result is kept (assigned to a variable, chained, or otherwise used) —
`isBareReturnOfNextCall()` in the detector. This matches every verified case in the task's table,
including the two that use an early-return `$next()` guard before their allowed fresh response.

### Second bug found and fixed: discovery must tokenize, not text-search, for `implements`

The first `MiddlewareDiscovery` implementation used a `preg_match('/\bimplements\b[^{]*\bMiddlewareInterface\b/')`
text search over raw file contents. This produced a false-positive match on
`packages/core/src/Exceptions/ModuleException.php`, which contains the string
`"...exists and implements " . MiddlewareInterface::class` inside an exception message — a
comment/string mention, not an actual `implements` clause. Discovery now tokenizes each file
with `PhpToken::tokenize()` and only counts a `T_IMPLEMENTS` keyword followed by a name token
(`T_STRING`/`T_NAME_QUALIFIED`/`T_NAME_FULLY_QUALIFIED`/`T_NAME_RELATIVE`) ending in
`MiddlewareInterface`, before the class body `{`. Discovery now returns exactly the 13 real
middleware classes in the repo (previously 14, including the false positive).

### Verification
- `./vendor/bin/pest tests/MiddlewareDecorationTest.php` — 7/7 passing, 16 assertions.
- `./vendor/bin/pest tests/ --parallel` — full `Monorepo` testsuite green (103 passed).
- `composer test` (full monorepo, both testsuites) — 6965 passed, 0 failures.
- `./vendor/bin/php-cs-fixer fix` and `./vendor/bin/phpcbf`/`phpcs` run against all new files
  (test file, both support classes, all four fixtures) — clean, no remaining violations.
- PHPStan's `phpstan.neon` scope is `packages/core/src` only, so it does not analyze root
  `tests/` — no PHPStan action needed for these files.
