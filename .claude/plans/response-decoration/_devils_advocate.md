# Devil's Advocate Review: response-decoration

Reviewed against the actual source, not just the plan text. The PHP 8.5.1 decoration finding in
`_plan.md` holds up and no task contradicts it — `readonly class StreamingResponse extends Response`
means dropping `readonly` on `Response` *forces* dropping it on `StreamingResponse` (PHP forbids a
readonly class extending a non-readonly one and vice versa), which the plan already accounts for.
`StreamingResponse` is the only subclass in the repo. `SseStream` is a separate `readonly class` in
an unrelated hierarchy and needs no change.

The problems are elsewhere: a missing `withStatus()`, an undefined `cookies()` accessor, a session
approach that would break session continuity entirely, a page-cache interaction that would silently
disable the page cache, and an architecture test that is defeated by the exact code it is meant to catch.

---

## Critical (Must fix before building)

### C1 — Task 002 has no `withStatus()`, so task 004 cannot migrate Inertia
`packages/inertia/src/Middleware/InertiaMiddleware.php:55` rebuilds the response **with a changed
status code** (302 → 303 for PUT/PATCH/DELETE). Task 002 only specifies `withHeader()` and
`withCookie()`; task 004 says "Each becomes a chain of `withHeader()` calls". A worker on task 004
would be hard-blocked with no way to change the status while preserving the subclass.

`SecurityHeadersMiddleware`, both `CorsMiddleware`s and `RateLimitMiddleware` all apply a *map* of
headers via `array_merge`, so a `withHeaders(array $headers)` bulk method keeps the migration a
one-liner instead of a hand-unrolled chain.

**Fix applied:** task 002 now requires `withHeaders(array<string,string>): static` and
`withStatus(int): static` alongside `withHeader()`/`withCookie()`. Task 004 updated to reference them.

### C2 — Task 004 undercounts InertiaMiddleware's rebuild sites
The plan lists `InertiaMiddleware.php:64` only. There are **two** rebuild sites — line 55 (redirect
branch, status-changing) and line 64 (normal branch). Line 28 (the 409 version-mismatch response)
is a genuinely fresh response and must be left alone. Verified: five files, six rebuild sites, and
`->body()` appears in exactly those five middleware plus the page-cache driver.

**Fix applied:** task 004 context now lists both Inertia sites and explicitly exempts line 28.

### C3 — `cookies()` accessor is never defined, but tasks 003 and 005 both consume it
Task 002 says cookies are "a SEPARATE collection (`list<Cookie>`)" and requires a test that they stay
out of `headers()` — but never names a public accessor. Task 003 must read the cookies to build
`Set-Cookie` lines; task 005 must read them to refuse caching. Two parallel workers would invent two
different names, or block.

Duplicate-name semantics are also undefined: `withCookie()` called twice with the same name either
appends two `Set-Cookie` lines or replaces. Task 002's requirement literally says "accumulates
multiple cookies", which reads as append-always.

**Fix applied:** task 002 now specifies `public function cookies(): array` returning `list<Cookie>`,
and pins replace-on-same-(name, path, domain) semantics with a test.

### C4 — Disabling `session.use_cookies` also disables session *reading*, breaking every session
`_plan.md` and task 006 both say "the session must start with cookie emission disabled". But
`session.use_cookies` is not write-only — it controls whether PHP reads the session ID from the
request cookie as well. `Session::configure()` (`packages/session/src/Session.php:240-241`) currently
sets both `use_cookies=1` and `use_only_cookies=1`. Flipping `use_cookies` to `0` while
`use_only_cookies` stays `1` leaves `session_start()` with **no ID source at all** — every single
request starts a brand-new empty session. Logins, flash messages and CSRF tokens all stop working,
and no existing test would catch it because the current session tests never assert continuity across
two requests.

The plan's own mitigation as written is the bug.

**Fix applied:** task 006 now requires the middleware to seed the ID explicitly — read the inbound
cookie from the `Request`, `setId()` it before `start()` — and adds a continuity requirement
("it reuses the session id from the inbound request cookie"). `Session::setId()` throws
`InvalidSessionIdException` for anything failing `^[a-zA-Z0-9-]{32,128}$`, and that value is
attacker-controlled, so an invalid inbound cookie must be ignored (fresh session), never a 500.
Added as an explicit requirement.

### C5 — `Request` has no cookie access at all; task 006 is blocked on `marko/routing`
`packages/routing/src/Http/Request.php` has no `cookie()` method and `fromGlobals()` (line 37) never
captures `$_COOKIE`. The fix for C4 needs the inbound session cookie, and reading `$_COOKIE` directly
from `SessionMiddleware` contradicts the plan's own superglobal stance and the worker-mode direction
of #151.

**Fix applied:** new **task 006a — Request cookie access** in `marko/routing` (no dependencies, so it
parallelises with 001). Task 006 now depends on 002, 003 and 006a.

### C6 — Tasks 005 + 006 together silently disable the page cache
`SessionMiddleware` is registered as **global middleware** by the driver packages
(`packages/session-file/module.php:19-21`, same in `session-database`), and those modules sequence
`'after' => ['marko/page-cache']`. `Router::handle()` builds `[...$globalMiddleware, ...routeMiddleware]`
and `MiddlewarePipeline` peels from the front — so a later-sequenced module runs *inside* an earlier
one. `SessionMiddleware` therefore runs inside `PageCacheMiddleware`, and its return value is what
`PageCacheMiddleware` hands to `isResponseCacheable()`.

`CacheabilityChecker::isResponseCacheable()` (`packages/page-cache/src/CacheabilityChecker.php:40`)
**already** returns false for any response carrying a `set-cookie` header. Once task 006 makes every
response carry a session cookie and task 005 extends that check to the cookie collection, the page
cache refuses to store anything at all on any app with sessions enabled. Every existing page-cache
test would still pass — they don't run `SessionMiddleware`.

Today PHP only emits `Set-Cookie` at `session_start()` when it *creates* or *regenerates* an ID;
repeat visitors get no cookie. Replicating that is both the correctness fix and the "FPM behavior
byte-identical" success criterion the plan already claims.

**Fix applied:** task 006 now requires the cookie to be attached **only** when the outgoing ID differs
from the inbound cookie value (new session or `regenerate()`), or when the session was destroyed —
with requirements covering both the attach and the no-attach case. Task 005 gained a requirement that
a plain cacheable response passing through `SessionMiddleware` on a repeat visit is still cached, and
`_plan.md` gained a matching success criterion and risk entry.

### C7 — Task 006's constructor change breaks five existing tests, the `SessionInterface` fake, and the manifest
`SessionMiddleware::__construct(SessionInterface $session)` gains `SessionConfig`. All five tests in
`packages/session/tests/Unit/Middleware/SessionMiddlewareTest.php` call `new SessionMiddleware($session)`,
and the file's `createFakeSession()` anonymous class implements the full `SessionInterface` — any
interface addition breaks it, and so does `marko/testing`'s `FakeSession`
(`packages/testing/src/Fake/FakeSession.php`). Note that fake's `getId()` returns `''`
unconditionally, which is exactly the sentinel the destroyed-session path wants to use.

Separately, `packages/session/composer.json` requires only `marko/core` and `marko/config`, yet
`SessionMiddleware` already imports `Marko\Routing\Http\{Request,Response}`. Adding `Cookie` makes an
existing undeclared dependency worse.

**Fix applied:** task 006 context now enumerates all of these — the five call sites, both fakes, and
the `marko/routing: self.version` composer requirement — and prefers a signal that needs **no**
`SessionInterface` change (compare `getId()` before/after; `destroy()` already zeroes it at
`Session.php:169`).

### C8 — Task 007 lives in the wrong test suite and will break the split repos
Task 007 places a repo-wide scanner "in the routing package's test suite". `packages/routing/tests/`
is published to the standalone read-only `marko/routing` repo (see `tests/SplitWorkflowTest.php`,
`tests/PackagingTest.php`), where `packages/` does not exist — the test would either fail or, worse,
scan zero files and pass vacuously forever. It also has no business making `marko/routing` reach into
`marko/security` and `marko/inertia`.

`phpunit.xml` already defines a `Monorepo` testsuite pointing at the root `tests/` directory, which is
exactly where cross-package architecture tests belong (`tests/PackagingTest.php`, `tests/CiWorkflowTest.php`).

**Fix applied:** task 007 relocated to `/Users/markshust/Sites/marko/tests/MiddlewareDecorationTest.php`.

### C9 — Task 007's heuristic returns a false negative on the very code it must catch
Task 007 describes the signal as "a `new Response(` whose arguments are fed from another response's
`body()`/`statusCode()`/`headers()`". `InertiaMiddleware` does:

```php
$headers = $response->headers();
$headers['Vary'] = $this->mergeVaryHeader($headers['Vary'] ?? null);
...
return new Response(body: $response->body(), statusCode: $statusCode, headers: $headers);
```

The variable indirection defeats argument-source matching for the `headers` argument, and a
body-less variant (`new Response(body: $body, ...)`) would be missed entirely. The test would go green
while the rebuild pattern is present — the single worst outcome for a guard test.

There is also no `nikic/php-parser` in the repo (checked the root `composer.json`), so real AST
analysis is not available; `token_get_all()` is.

A much more robust rule, which I verified against every middleware in the repo: **no `new *Response(`
may appear after the first `$next(` call within the same method body.** Every legitimate fresh
response is constructed *before* `$next()` or in a separate helper method:

| Site | Construct | Position | Verdict |
|---|---|---|---|
| `inertia/InertiaMiddleware.php:28` | fresh 409 | before `$next()` at :39 | passes |
| `cors/CorsMiddleware.php:48` | fresh 204 preflight | before `$next()` | passes |
| `security/CorsMiddleware.php:34` | fresh 204 preflight | before `$next()` at :42 | passes |
| `ratelimiter/RateLimitMiddleware.php:36` | fresh 429 | before `$next()` at :49 | passes |
| `authentication/AuthMiddleware.php:49` | fresh 401 | separate helper method | passes |
| `admin-auth/AdminAuthMiddleware.php:96` | fresh 403 | separate helper method | passes |
| `authorization/AuthorizationMiddleware.php:88,107` | fresh 401/403 | separate helper methods | passes |
| the six rebuild sites | rebuild | after `$next()` | **caught** |

`LayoutMiddleware` calls `$next()` and then returns `$this->layoutProcessor->process(...)` — no
`new Response`, passes cleanly.

**Fix applied:** task 007 rewritten around the position rule (with the argument heuristic kept as a
secondary signal), `token_get_all()` mandated over regex, and the detector required to be a class
taking an explicit file list — otherwise the negative-case fixture gets picked up by the repo-wide
scan and the suite fails against itself.

---

## Important (Should fix before building)

### I1 — Task 007's glob misses half the middleware
`packages/*/src/**/Middleware/*.php` does not match `packages/security/src/Middleware/SecurityHeadersMiddleware.php`
under PHP's `glob()`, which has no `**` support whatsoever. Middleware in this repo sit at *both*
`src/Middleware/` (security, session, cors, inertia, ratelimiter, layout, page-cache) and
`src/Middleware/` nested variants. Discovery must be a recursive directory walk, and should key off
`implements MiddlewareInterface` rather than directory name so a middleware placed elsewhere is not
silently exempt.

**Fix applied** in task 007.

### I2 — Task 001 leaves cookie encoding, "no expiry", and SameSite=None undefined
Three gaps that will produce wrong headers:
- **Value encoding.** `Set-Cookie` values cannot contain `;`, `,`, whitespace or control characters.
  Session IDs happen to be safe; arbitrary application cookies are not. Raw vs. `urlencode` must be
  decided in the value object, not left to callers.
- **No-expiry cookies.** `SessionConfig::expireOnClose()` maps to `lifetime => 0`, i.e. a browser
  session cookie with **no** `Expires`/`Max-Age` attribute at all. The task's `expires` attribute has
  no stated representation for that.
- **`SameSite=None` requires `Secure`.** Browsers drop the cookie otherwise. Per "loud errors", this
  should throw at construction.

**Fix applied:** three requirements added to task 001.

### I3 — Task 005's logger dependency adds a package dependency and breaks existing tests
Injecting `LoggerInterface` into `FilePageCacheDriver` requires adding `marko/log` to
`packages/page-cache-file/composer.json` (currently core/config/routing/page-cache only) and changes
a constructor that `packages/page-cache-file/tests/Unit/Driver/FilePageCacheDriverTest.php`
instantiates directly.

More importantly the check is in the wrong place. `CacheabilityChecker::isResponseCacheable()` is
already the central cacheability decision and *already* rejects a literal `set-cookie` header at
line 40 — extending it to the cookie collection is one line, benefits every driver, and needs no new
dependency. The driver check is worth keeping as defence in depth, but it is the checker that must change.

**Fix applied:** task 005 now names `CacheabilityChecker` as the required location, keeps the driver
guard as secondary, and demotes the debug logging to optional (relocated to `PageCacheMiddleware`,
which can already reach the container) so it does not drag `marko/log` into a driver package.

### I4 — "Exactly one Set-Cookie under FPM" is not assertable from the test suite
`header()` is a no-op under the CLI SAPI — the plan says so itself in task 003's rationale — so
task 006's requirement `it emits exactly one session set-cookie header` can only ever observe the
`Response`'s own header lines. It structurally cannot detect a duplicate emitted by `session_start()`
through the SAPI, which is the actual risk being mitigated. As written the test gives false confidence.

**Fix applied:** task 006's requirement is now split into two concrete, actually-observable
assertions: the response's header lines contain exactly one `Set-Cookie` for the session cookie name,
**and** `session.use_cookies` reads `'0'` after `start()` (the ini state is the only in-process proxy
for "the SAPI will not emit its own"). `_plan.md` success criterion reworded to match.

### I5 — Task 006 is well over one TDD cycle
As written it spans two packages and covers: `Session::configure()` ini semantics, `Session::destroy()`,
a new `SessionMiddleware` constructor dependency, inbound-ID seeding, invalid-ID handling,
new-vs-existing attach logic, destroyed-session expiry, a composer.json change, five broken test call
sites, and two `SessionInterface` fakes. That is not one red-green-refactor loop.

**Fix applied:** extracted task 006a (Request cookie access, `marko/routing`, zero dependencies) which
also improves parallelism — it can run alongside 001. Task 006 retains the session work.

### I6 — Task 002 does not test that the decorated stream still streams
The requirement `it preserves subclass state such as the streaming payload when decorating` asserts
the property survives. It does not assert the *behaviour* survives — that `send()` on the clone still
runs `StreamingResponse::send()` and iterates the stream. `clone` is shallow, so the clone shares one
`SseStream` instance with the original; `SseStream::getIterator()` returns a fresh `Generator` per
call so this is safe, but it is an unstated invariant that a future `__clone()` could break. Also
untested: that `withHeader()` on a `StreamingResponse` does not clobber the four SSE headers set by
its constructor.

**Fix applied:** two requirements added to task 002.

### I7 — `Session::destroy()`'s clear path dies silently under the new ini setting
`Session.php:172` guards the cookie clearing with `if (ini_get('session.use_cookies'))`. Once
`configure()` sets that to `'0'`, the branch is permanently dead — the `setcookie()` disappears and
nothing replaces it unless the task explicitly says so. Task 006 says both paths must move, but the
guard is not mentioned and a worker deleting only the `setcookie()` line would leave a dead `if`.

`destroy()` sets `$this->id = ''` at line 169, which is a usable "was destroyed" signal for the
middleware without touching `SessionInterface`. Note `createFakeSession()`'s `getId()` returns `''`
unconditionally, so the existing fakes need updating or those tests will see a phantom destroy.

**Fix applied:** task 006 context and requirements updated.

### I8 — Nothing forbids a worker from "fixing" the named constructors into `new static()`
`Response::json()`, `html()` and `redirect()` are `static ...: self` using `new self(...)`. A worker
reading task 002's "preserves the concrete subclass, return `static` not `self`" guidance could
reasonably decide the named constructors should be late-static-bound too. That would be a fatal
error: `StreamingResponse::__construct(SseStream $stream, int $statusCode)` cannot accept
`(body:, statusCode:, headers:)`, so `StreamingResponse::json()` would blow up at runtime — the exact
signature mismatch that makes `clone` necessary in the first place.

**Fix applied:** task 002 now states explicitly that the three named constructors keep `self` /
`new self()` and are out of scope, with the reason.

### I9 — The session cookie is lost on the exception path
`SessionMiddleware` calls `save()` in a `finally` and lets the exception propagate — there is no
response to decorate. Today the SAPI emitted the session cookie at `session_start()`, so even a
500 page carried it. After this change an error response carries no cookie, so a first-time visitor
who hits an error loses the session and any flash message or CSRF token queued for the error page.
The existing test `it saves session even when handler throws` covers `save()` but not the cookie.

The C6 fix ("attach only when new") narrows this to first requests, but it is still a real behaviour
change and should be a conscious, tested decision rather than a discovery.

**Fix applied:** noted in task 006 context with a requirement that the exception path is covered.

---

## Minor (Nice to address — not applied)

- **M1.** `_plan.md` and task 002 both cite "nine existing `->headers()` call sites". The real count
  is eight production call sites on a Marko `Response` (`ratelimiter:54`, `security/SecurityHeaders:30`,
  `security/Cors:47`, `cors:65`, `inertia:45`, `sse/StreamingResponse:38`, `page-cache-file:81`,
  `page-cache/CacheabilityChecker:96`) plus roughly eighty in tests, plus an unrelated `headers()` on
  `marko/http-guzzle`'s own response type. Harmless — the signature is unchanged, so all of them stay
  source-compatible — but the number is wrong and reads as more precise than it is.
- **M2.** `phpstan.neon` analyses `packages/core/src` only. The success criterion "PHPStan at zero
  errors" therefore provides no static-analysis coverage of any file this plan touches. True as
  written, but weaker than it sounds.
- **M3.** `Session::validateId()` requires `^[a-zA-Z0-9-]{32,128}$`. PHP's default
  `session.sid_bits_per_character=5` produces a matching alphabet, but an app that sets it to `6`
  gets `,` in IDs and every inbound cookie would be rejected as invalid. Edge case, only reachable via
  explicit ini tuning.
- **M4.** Nothing stops `withHeader('Set-Cookie', ...)` from bypassing the cookie collection entirely,
  producing a cookie that `cookies()` cannot see. Per "loud errors", consider throwing and directing
  the caller to `withCookie()`.
- **M5.** The shallow-`clone` sharing of `SseStream` between original and decorated response is
  correct today but undocumented. Worth a class-level comment so nobody adds a deep-copying
  `__clone()` later.

## Questions for the Team

1. **`withCookie()` with a duplicate name — replace or append?** I pinned *replace* on matching
   (name, path, domain) in task 002, since that is what `Response::withHeader()` does for headers and
   what browsers effectively do with duplicate `Set-Cookie` lines. Append is defensible if you want
   the same name scoped to two paths. Confirm the choice.
2. **Should the session cookie always ride the response, or only when it changes?** I applied
   "only when new/regenerated/destroyed" because always-attach silently kills the page cache (C6) and
   diverges from today's FPM behaviour. The cost: the `Response` is not a complete picture of session
   state on repeat requests, which slightly weakens the "session cookie travels on the Response"
   framing. If you would rather always attach, task 005 needs a session-cookie carve-out instead —
   which is a security decision, not a mechanical one.
3. **Should `Cookie` live in `marko/routing`?** It forces `marko/session` to declare a dependency on
   `marko/routing` (which it already has undeclared). That is fine and probably correct, but it does
   mean any package wanting to set a cookie now depends on the routing package.
4. **Follow-up: late-static-bound named constructors.** Making `json()`/`html()`/`redirect()` work
   correctly for subclasses would need a different construction strategy than `new static()`. Worth an
   issue, deliberately not touched here.
5. **Should `Request` become the sole source of cookies for the whole framework?** Task 006a adds
   `Request::cookie()`. `Session` still reaches PHP's session machinery. Full superglobal removal is
   #151 territory but the boundary is worth agreeing on now.
