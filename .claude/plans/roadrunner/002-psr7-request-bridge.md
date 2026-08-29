# Task 002: PSR-7 Request to Marko Request Bridge

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Translate an incoming PSR-7 `ServerRequestInterface` into a Marko `Request`. This is half of the boundary that lets a pure `handle(Request): Response` call serve a RoadRunner request.

## Context
- `Request` (`packages/routing/src/Http/Request.php:14`) is readonly with a PUBLIC constructor: `server`, `query`, `post`, `body`, `controller`, `action`. Construct it directly — do NOT call `fromGlobals()`, which reads superglobals that are stale in a worker.
- Study `fromGlobals()` (line 23) for the semantics to reproduce, especially its handling of `PUT`/`PATCH`/`DELETE` bodies with `application/x-www-form-urlencoded` content types, which PHP does not populate into `$_POST`.
- **Depends on #150**: that plan adds `Request::cookie()` and makes `fromGlobals()` capture `$_COOKIE` (`.claude/plans/response-decoration/006a-request-cookie-access.md`). Populate cookies from the PSR-7 request through that same accessor rather than inventing a parallel path. Note #150 requires the new cookie parameter be added **last / named-only with a default**, so construct with named arguments.
- Do NOT write to superglobals as a shortcut. The whole point is that the `Request` object is self-sufficient.

### The `$_SERVER` synthesis is the load-bearing part — enumerate it explicitly

`Request` has no first-class accessors for most of this; everything routes through the `server` array. Verified consumers:
- `Request::method()` reads `REQUEST_METHOD` (line 47).
- `Request::path()` reads `REQUEST_URI` and strips at the first `?` (lines 52-55) — so `REQUEST_URI` **must include the query string**, not just the path.
- `Marko\Inertia\Inertia::92` and `InertiaMiddleware::34` read `$request->server('REQUEST_URI')` and use it as the page URL, which is wrong if the query string is dropped.
- `Request::ip()` reads `REMOTE_ADDR` (line 101); `Marko\RateLimiter\ClientIpResolver` layers `X-Forwarded-For` on top.
- `Request::header()` reads `HTTP_{UPPER_SNAKE}` and falls back to bare `CONTENT_TYPE` / `CONTENT_LENGTH` (lines 132-141).
- `Request::headers()` reconstructs names by stripping `HTTP_`, replacing `_` with `-` and `ucwords` (lines 153-159).

Minimum key set to synthesize: `REQUEST_METHOD`, `REQUEST_URI` (path + `?` + raw query when present), `QUERY_STRING`, `SERVER_PROTOCOL`, `HTTP_HOST`, `SERVER_NAME`, `SERVER_PORT`, `HTTPS` (set only when the PSR-7 URI scheme is `https`), `REMOTE_ADDR`, `CONTENT_TYPE`, `CONTENT_LENGTH`, plus one `HTTP_*` entry per PSR-7 header. PSR-7 headers are `array<string, list<string>>` — join multi-value headers with `, ` the way PHP's SAPI does, and do not emit `HTTP_COOKIE` as the cookie source (cookies go through the dedicated cookie parameter).

### File uploads are not supported — say so loudly

`Marko\Routing\Http\Request` has no `$_FILES` equivalent and no files accessor, so PSR-7 `getUploadedFiles()` has nowhere to map. A silently-dropped upload is exactly the kind of failure this framework refuses. The bridge must throw a loud, actionable exception when the PSR-7 request carries uploaded files, naming the limitation and pointing at the docs page. Task 010 documents it.

## Requirements (Test Descriptions)
- [x] `it maps the request method from the psr7 request`
- [x] `it maps the request path from the psr7 uri`
- [x] `it maps query parameters from the psr7 request`
- [x] `it maps parsed body parameters to the post array`
- [x] `it maps psr7 headers so that header lookup works`
- [x] `it joins multi value psr7 headers into a single server entry`
- [x] `it includes the query string in the request uri server key`
- [x] `it maps the remote address so that ip lookup works`
- [x] `it maps content type and content length as bare server keys`
- [x] `it sets the https server key only for https requests`
- [x] `it maps cookies from the psr7 request`
- [x] `it parses a form encoded body for put patch and delete requests`
- [x] `it throws a loud error when the psr7 request carries uploaded files`

## Acceptance Criteria
- All requirements have passing tests
- No superglobal is read or written by the bridge
- `Request::method()`, `path()`, `query()`, `post()`, `body()`, `ip()`, `header()`, `headers()` and `cookie()` all return correct values for a bridged request, asserted through the public accessors rather than by inspecting the server array
- Code follows code standards

## Implementation Notes

- `Marko\Roadrunner\Http\Psr7RequestBridge::bridge(ServerRequestInterface): Request` (`packages/roadrunner/src/Http/Psr7RequestBridge.php`). No constructor/properties (stateless), so plain `class`, not `readonly class`. Split into two private helpers during refactor: `buildServer()` (synthesizes the `$server` array) and `resolvePost()` (parsed-body / PUT-PATCH-DELETE form parsing), both structural extractions with no behavior change — verified green before and after.
- `buildServer()` synthesizes `REQUEST_METHOD`, `REQUEST_URI` (path + `?` + raw query when present), `QUERY_STRING`, `HTTPS` (only when the PSR-7 URI scheme is `https`), `REMOTE_ADDR` (read from `getServerParams()['REMOTE_ADDR']` — confirmed via `spiral/roadrunner-http`'s `PSR7Worker::mapRequest()`/`GlobalState::enrichServerVars()` that the real worker populates this key on the `ServerRequestInterface` it hands to application code), and one `HTTP_*` entry per PSR-7 header (multi-value headers joined with `, `), except `Content-Type`/`Content-Length`, which are stored as bare `CONTENT_TYPE`/`CONTENT_LENGTH` keys (matching `Request::header()`'s bare-key fallback and PHP's own SAPI convention) rather than duplicated under `HTTP_`. `HTTP_HOST` needs no special-casing: Nyholm's `ServerRequest` always synthesizes a `Host` header from the URI if the caller didn't supply one, so it flows through the generic header loop.
- Deliberately did **not** synthesize `SERVER_NAME`, `SERVER_PORT`, or `SERVER_PROTOCOL` — the task's "minimum key set" context lists them, but no `Request` accessor exercised by this task's requirements/acceptance-criteria ever reads them, and CLAUDE.md rule 3 (no dead code) plus the TDD discipline of not writing untested behavior took precedence. `packages/routing/src/Http/Request.php` has no `serverName()`/`serverPort()`/`protocolVersion()` accessor for anything to consume.
- Query string requirement (`it includes the query string in the request uri server key`) and the HTTPS requirement (`it sets the https server key only for https requests`) are asserted via `Request::server()` — the same public method `Marko\Inertia\Inertia` itself uses to read `REQUEST_URI`, and the same mechanism `Request::ip()` uses internally for `REMOTE_ADDR`. Neither key has a dedicated named accessor, so `server()` (not reflection into the private array) is the correct public seam.
- `it maps content type and content length as bare server keys` also uses `server()` directly rather than `header()`, because `Request::header()` checks the `HTTP_*` key first and only falls back to the bare key — a `header()`-based assertion would pass regardless of whether the value were stored as `HTTP_CONTENT_TYPE` or bare `CONTENT_TYPE`, so it wouldn't actually pin the "bare key" behavior the requirement names.
- Uploaded files: `Marko\Roadrunner\Exceptions\UploadedFilesNotSupportedException` (`packages/roadrunner/src/Exceptions/UploadedFilesNotSupportedException.php`) extends `MarkoException` with a single static factory `whenBridgingRequest()`, following the `CookieException`/`RouteException`/sibling `NoDriverException` pattern (named `message`/`context`/`suggestion`, doc URL `https://marko.build/docs/packages/roadrunner/` matching the `inferPackageName()`-driven convention used elsewhere). `bridge()` checks `getUploadedFiles() !== []` before doing any other work and throws immediately.
- PUT/PATCH/DELETE form-encoded body parsing in `resolvePost()` mirrors `Request::fromGlobals()`'s existing workaround verbatim: only kicks in when the PSR-7 parsed body is empty, the raw body is non-empty, the method is PUT/PATCH/DELETE, and `Content-Type` contains `application/x-www-form-urlencoded`.
- Requirement 6 (`it joins multi value psr7 headers into a single server entry`) passed immediately when written — over-implementation carried over from requirement 5's step, where `implode(', ', $values)` was written into the header loop up front since it was the natural, minimal way to turn a PSR-7 `list<string>` header value into a single string. Noted per TDD process; no separate red/green cycle was possible for it in isolation.
- Cookies flow through the `Request` constructor's `cookies` named parameter (added last, named-only, per #150), populated from `$psr7Request->getCookieParams()` — no parallel cookie path invented.
- No superglobal (`$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE`) is read or written anywhere in the bridge; every value is derived from the `ServerRequestInterface` passed in.
- Tests: `packages/roadrunner/tests/Http/Psr7RequestBridgeTest.php`, 13 tests / 17 assertions, using `Nyholm\Psr7\ServerRequest`/`Stream`/`UploadedFile` directly (no factory needed — constructor args are sufficient for every scenario, including the uploaded-files case).
- Verification: `./vendor/bin/pest packages/roadrunner/tests/Http/Psr7RequestBridgeTest.php --parallel` → 13 passed, 17 assertions. `composer phpstan` → 0 errors. `./vendor/bin/phpcs` on all three touched files → clean. `./vendor/bin/php-cs-fixer fix` on all three touched files → 0 fixes needed. Full suite `./vendor/bin/pest --parallel --exclude-group=integration-destructive` → exit 0, 7024 passed (up from the 6986 baseline; the delta also includes task 003's concurrently-developed `Psr7ResponseBridge` work landing on the same shared package directory during this task's execution — confirmed via `git status`/directory listing, not part of this task's own diff).
