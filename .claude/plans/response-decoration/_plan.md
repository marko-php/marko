# Plan: Response Decoration API with Cookie Support

## Created
2026-08-28

## Status
completed

## Objective
Make every piece of request-scoped state in the framework explicit and clearable: give `Marko\Routing\Http\Response` a decoration API so middleware stop rebuilding responses (which silently downgrades `StreamingResponse` and discards SSE streams), and give responses first-class cookie support so the session cookie travels on the `Response` object instead of the SAPI.

## Related Issues
Closes #150

## Discovery Notes

**The live bug.** `StreamingResponse extends Response` (`packages/sse/src/StreamingResponse.php:11`) carries its payload in an `SseStream` with `body` = `''`, and overrides `send()` to stream it. Five middleware add headers by constructing a brand-new base `Response` from `body()`/`statusCode()`/`headers()`. Any of them in front of an SSE route downgrades the `StreamingResponse` to a plain `Response` — stream discarded, overridden `send()` never runs, client gets an empty 200. `SecurityHeadersMiddleware` is the kind of middleware apps register globally, so this is reachable in practice.

**Verified on PHP 8.5.1 — the decoration mechanism.** Four approaches were tested empirically:

| Approach | Result |
|---|---|
| `clone $this with { ... }` | Not available in 8.5.1 — parse error |
| `readonly class` + clone then assign in class scope | Fails: `Cannot modify readonly property` |
| `readonly class` + `ReflectionProperty::setValue` on the clone | Fails: same error |
| Plain class, private non-readonly props, `clone` + assign | **Works** — preserves concrete subclass, subclass state, and leaves the original untouched |

`Response` must therefore drop the `readonly` **class modifier**. Immutability is preserved by API design: private properties, no setters, `with*()` returns modified clones. This is the PSR-7 implementation approach and is consistent with the project convention "readonly — use when appropriate for immutability, not as a blanket rule."

**Cookie representation (decided).** Cookies live in a separate collection, not as multi-valued headers. `headers()` keeps its `array<string, string>` type, so every existing `->headers()` call site stays source-compatible — eight in production code, roughly eighty across the test suites. `send()` merges cookies into `Set-Cookie` lines at emit time.

**Decoration needs more than `withHeader()`.** `InertiaMiddleware.php:55` rebuilds specifically to change the status code (302 → 303 for PUT/PATCH/DELETE), and four of the five middleware apply a *map* of headers via `array_merge`. The API is therefore `withHeader()`, `withHeaders()`, `withStatus()`, `withCookie()`, plus a `cookies()` accessor that tasks 003 and 005 consume. `InertiaMiddleware` has **two** rebuild sites (lines 55 and 64), so the five files contain six rebuild sites.

**Session is more involved than one call site.** `Session.php:174` is the only `setcookie()` in the repo, but it lives in `destroy()` (clearing the cookie). The *setting* of the session cookie is emitted implicitly by `session_start()` through the SAPI. Both paths must move onto the `Response`. `SessionMiddleware` (`packages/session/src/Middleware/SessionMiddleware.php`) already wraps the response and calls `save()`, so it is the natural attach point.

Two traps here, both verified against the source:

- **`session.use_cookies = 0` disables session *reading*, not just writing.** `Session::configure()` sets `use_cookies=1` and `use_only_cookies=1` (lines 240-241). Turning the first off leaves `session_start()` with no ID source and every request begins a brand-new empty session. The middleware must therefore seed the ID explicitly — read the inbound cookie off the `Request` and `setId()` it before `start()` — and ignore invalid attacker-supplied IDs rather than letting `InvalidSessionIdException` become a 500. This requires cookie access on `Request`, which does not exist today (`fromGlobals()` never captures `$_COOKIE`); hence task 006a.
- **Attaching the cookie unconditionally would silently disable the page cache.** See below.

**Page-cache is a distinct case, and it collides with the session work.** `FilePageCacheDriver:59,81` is a serialize/hydrate round-trip, not middleware decoration. A cached `Set-Cookie` would serve one user's session cookie to every later visitor, so cookie-bearing responses must not be cached. `CacheabilityChecker::isResponseCacheable()` already enforces exactly this for a literal `set-cookie` header (line 40), and is the right place to extend.

But `SessionMiddleware` is global middleware registered by the session drivers (`session-file/module.php:19-21`) and sequenced `after: marko/page-cache`, so it runs *inside* `PageCacheMiddleware`. If every response carried a session cookie, the page cache would refuse to store anything on any app with sessions enabled — and no existing test would notice. PHP itself only emits `Set-Cookie` when `session_start()` creates or regenerates an ID; the middleware must mirror that and attach only on new / regenerated / destroyed sessions.

## Scope

### In Scope
- `Cookie` value object with correct `Set-Cookie` rendering
- `Request` cookie access (`cookie()` accessor plus `$_COOKIE` capture in `fromGlobals()`)
- `Response` decoration API (`withHeader()`, `withHeaders()`, `withStatus()`, `withCookie()`, `cookies()`) preserving concrete subclass
- Testable header-line emission consumed by `send()`, emitting one `Set-Cookie` per cookie
- Migrating the five rebuild-pattern middleware to decoration
- Architecture test that fails the build if the rebuild pattern reappears in middleware
- Session cookie (set and clear) travelling on the `Response`
- Page-cache refusing to cache cookie-bearing responses
- `ResettableInterface` in `marko/core` — the contract for services holding request-scoped state
- `Session` and `SessionGuard` made request-scoped (three verified cross-user leaks) and implementing that contract
- `ReadWriteConnection` implementing that contract, wiring up its existing `resetStickyState()`
- `Container` accessor exposing already-resolved instances, without forcing instantiation

### Out of Scope
- Worker-mode runtime, PSR-7 bridge, RoadRunner package (issue #151)
- Calling the reset lifecycle per request — the worker that does the calling is #151; this plan only supplies the contract and its implementors
- Refactoring debugbar's superglobal reads (dev-only tool)
- `errors-advanced/RequestDataCollector` (already constructor-injectable with superglobal fallback)
- Introducing PSR-7 anywhere — core has zero PSR-7 and that must stay true

## Success Criteria
- [ ] A `StreamingResponse` passed through `SecurityHeadersMiddleware` retains its concrete subclass, its stream, and still streams when sent
- [ ] A response can carry multiple cookies, each emitted as its own `Set-Cookie` line
- [ ] `Response::json()`, `html()`, `redirect()` and the 3-arg constructor remain source-compatible and keep using `new self()`
- [ ] A request carrying a valid session cookie reuses that session — no new session per request
- [ ] The response carries exactly one session `Set-Cookie` line when the session is new or regenerated, and none when the ID is unchanged; `session.use_cookies` reads `'0'` after `start()` so the SAPI emits no duplicate
- [ ] Existing session tests still green; FPM behavior unchanged from the client's perspective
- [ ] Page-cache never stores a response carrying cookies, **and still caches repeat-visit responses that passed through `SessionMiddleware`**
- [ ] Architecture test fails when the rebuild pattern is reintroduced in middleware, including via a local `$headers` variable
- [ ] An anonymous request following an authenticated one starts a fresh session — the verified cross-user leak is reproduced by a failing test first, then fixed
- [ ] The cached authenticated user does not survive into a subsequent request
- [ ] The session save-handler shutdown function is registered once, not once per request
- [ ] `SessionInterface` and `GuardInterface` are byte-identical to before, and every `marko/testing` fake still satisfies them unedited
- [ ] The container accessor never constructs a service as a side effect of being called
- [ ] `composer ci` fully green (tests, lint, PHPStan at zero errors — note `phpstan.neon` analyses `packages/core/src` only, so it covers none of the files this plan touches)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Cookie value object and Set-Cookie rendering | - | completed |
| 006a | Request cookie access | - | completed |
| 008 | ResettableInterface in core | - | completed |
| 011 | Container resolved-instances accessor | - | completed |
| 002 | Response decoration API preserving subclass | 001 | completed |
| 010 | ReadWriteConnection implements ResettableInterface | 008 | completed |
| 003 | Header line emission and cookie-aware send() | 002 | completed |
| 004 | Migrate rebuild-pattern middleware to decoration | 002 | completed |
| 005 | Page-cache refuses cookie-bearing responses | 002 | completed |
| 006 | Session cookie travels on the Response | 002, 003, 006a | completed |
| 007 | Architecture test forbidding the rebuild pattern | 004 | completed |
| 009 | Request-scoped Session and auth guard | 006, 008 | completed |
| 012 | Expose resolvedInstances on ContainerInterface | 011 | completed |
| 013 | Inertia implements ResettableInterface | 008 | completed |
| 014 | Roll back open transactions on reset | 010 | completed |

Batches: **(1)** 001, 006a, 008, 011 → **(2)** 002, 010 → **(3)** 003, 004, 005 → **(4)** 006, 007 → **(5)** 009.

### Later decisions (added after the RoadRunner plan's review)

- **The session and auth request-scoping fixes live here, not in #151.** They were found while planning the worker, but they modify `marko/session` and `marko/authentication` and cannot be fixed from inside a driver package. Putting them here keeps #151 purely additive, which was the whole point of splitting the two.
- **`ResettableInterface` is introduced now.** The earlier decision to defer it rested on not knowing what needed resetting. The set is now known and verified — `Session`, `SessionGuard`, `ReadWriteConnection` — so the contract is no longer speculative.
- **`Container` gains a resolved-instances accessor.** Without it a worker's reset list must be hardcoded, and any package that later adds request-scoped singleton state breaks worker mode undetectably.

## Architecture Notes
- `Response` loses the `readonly` class modifier; properties stay private with no setters. `StreamingResponse` **must** follow suit — PHP forbids a readonly class extending a non-readonly one — and it is the only `Response` subclass in the repo. `SseStream` is a separate `readonly class` in an unrelated hierarchy and is unaffected.
- `with*()` methods return `static` and use `clone` — never `new static(...)`, because `StreamingResponse`'s constructor signature differs from its parent's. This is precisely what makes the SSE fix work. The named constructors `json()` / `html()` / `redirect()` deliberately stay `self` / `new self()` for the same reason: `StreamingResponse::json()` would fatal under late static binding.
- `clone` is shallow, so a decorated `StreamingResponse` shares one `SseStream` with the original. That is correct — `SseStream::getIterator()` returns a fresh `Generator` per call — but it is an invariant a future `__clone()` could break.
- Cookies are a separate `list<Cookie>` collection; `headers()` keeps `array<string, string>`. `withCookie()` replaces on matching (name, path, domain) rather than appending duplicates.
- Header emission is extracted into a testable `headerLines()` method so `send()` stays a thin loop — `header()` is unobservable under the CLI SAPI, so emission logic must not live inside `send()` itself. This also gives the future RoadRunner bridge (#151) a ready-made seam.
- Loud errors: invalid cookie names throw, and `SameSite=None` without `Secure` throws, rather than silently producing a header the browser discards.

## Risks & Mitigations
- **Total session loss from disabling `session.use_cookies`**: the ini flag governs reading as well as writing, so flipping it without seeding the ID starts a fresh session on every request. Mitigation: `SessionMiddleware` reads the inbound cookie off the `Request` and calls `setId()` before `start()`; invalid IDs are ignored rather than thrown; a continuity test asserts the ID survives a round trip.
- **Duplicate session cookie under FPM**: `session_start()` emits its own `Set-Cookie`. Mitigation: disable SAPI cookie emission at session start and let `SessionMiddleware` own the cookie. Note this cannot be asserted directly — `header()` is a no-op under CLI — so the tests assert the ini state plus a single `Set-Cookie` in `headerLines()`.
- **Page cache silently stops caching**: `SessionMiddleware` runs inside `PageCacheMiddleware`, so an always-attached session cookie would make every response uncacheable. Mitigation: attach only on new / regenerated / destroyed sessions, mirroring PHP's own behavior; task 005 carries the cross-task regression test.
- **Dropping `readonly` weakens the immutability guarantee**: mitigated by keeping properties private with no setters and covering "original untouched" in tests for every `with*()` method.
- **Silent regression of the rebuild pattern**: mitigated by task 007's architecture test. The rule is positional (no `new *Response(` after `$next()` in the same method) rather than argument-provenance based, because the latter is defeated by `InertiaMiddleware`'s local `$headers` variable — the exact code it must catch.
- **Subclass state loss in decoration**: mitigated by testing decoration against `StreamingResponse` specifically, and by asserting the decorated clone still *streams*, not merely that it retains the property.
- **Session cookie lost on the exception path**: an exception in `$next()` leaves no response to decorate, so an error response carries no cookie where the SAPI previously supplied one. Narrowed to first requests by the attach-only-when-changed rule; pinned by a test rather than left to be discovered.
