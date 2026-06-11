# Task 006: F2+F5 — Request server()/ip() accessors, withRoute()/controller()/action(), and Router route-context wiring

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
Give `marko/routing` the request facts that the rate limiter (F2) and the admin permission middleware (F5) both need, in the single file that owns the `Request`. Add a `server(string $key): ?string` accessor and an `ip(): ?string` accessor that returns `REMOTE_ADDR` (a server param, NOT a header). Add an immutable `withRoute(string $controller, string $action): self` plus `controller(): ?string` / `action(): ?string` accessors (Request stays `readonly` — `withRoute` returns a cloned instance). Wire `Router::handle()` to rebind the request via `withRoute()` using the matched route's controller/action BEFORE dispatching through the middleware pipeline, so every middleware sees route context.

## Context
- Related files:
  - `packages/routing/src/Http/Request.php` (readonly; `private array $server`; existing `header()` reads only `HTTP_*` keys ~89-95)
  - `packages/routing/src/Router.php` (`handle()` ~37; `$matched->route->controller`/`->action` in scope ~47-56; pipeline dispatch ~61)
  - `packages/routing/tests/` (Pest Unit/Feature)
- Patterns to follow:
  - Keep `Request` `readonly`; clone-with-change pattern for `withRoute()`. Use PHP 8.5 `clone(... with ...)` or a manual constructor clone preserving `server`/`query`/`post`/`body` plus the new controller/action.
  - `ip()` reads `$this->server['REMOTE_ADDR']`; it must NOT consult any `X-Forwarded-*` header (proxy-trust policy belongs to the consumer in task 007).
  - `MiddlewarePipeline::process()` already threads the `Request` through each middleware; ensure the route-bearing request is the one passed in.
- Gotchas (verified against source):
  - In `Router::handle()` (line ~37), rebind `$request = $request->withRoute($matched->route->controller, $matched->route->action)` AFTER the `$matched === null` 404 check and BEFORE `$this->pipeline->process(...)` (line ~63). The `$handler` closure takes `Request $request` as a parameter (line ~46), so the pipeline-threaded route-bearing request flows into the handler and into `resolveParameters()` automatically — do NOT also capture the outer `$request` by `use` in the closure, or you risk injecting the pre-route request into controllers.
  - `Request` currently stores `server`/`query`/`post`/`body` only; add `?string $controller`/`?string $action` as new readonly promoted constructor params defaulting to `null` (keeps `fromGlobals()` and all existing `new Request(...)` call sites working). `controller()`/`action()` return them.
  - `server(string $key)` returns `$this->server[$key] ?? null` (raw, no `HTTP_` prefixing — that's what `header()` does). `ip()` returns `$this->server['REMOTE_ADDR'] ?? null`. The `$server` array values are `mixed`; cast/treat as `?string`.

## Requirements (Test Descriptions)
- [x] `it returns a raw server param by key via server()`
- [x] `it returns null from server() when the key is absent`
- [x] `it returns the REMOTE_ADDR value via ip()`
- [x] `it returns null from ip() when REMOTE_ADDR is absent`
- [x] `it ignores X-Forwarded-For when resolving ip()`
- [x] `it returns a new Request carrying the controller and action via withRoute()`
- [x] `it leaves the original Request unchanged after withRoute() (immutability)`
- [x] `it returns null from controller() and action() before withRoute() is called`
- [x] `it makes the matched controller and action visible to middleware during Router::handle()`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added `server(string $key): ?string` to `Request` — returns `$this->server[$key] ?? null`, casting to string. Raw access, no `HTTP_` prefix (that's `header()`'s job).
- Added `ip(): ?string` to `Request` — delegates to `$this->server('REMOTE_ADDR')`. No proxy-header consulting by design.
- Added `?string $controller = null` and `?string $action = null` readonly constructor params to `Request` (defaults preserve all existing call sites).
- Added `withRoute(string $controller, string $action): self` — returns `new self(...)` with all existing fields preserved plus new controller/action. PHP 8.5.1 does not yet support `clone() with {}` syntax (RFC not shipped in this build), so manual constructor cloning is used.
- Added `controller(): ?string` and `action(): ?string` accessors returning the respective properties.
- In `Router::handle()`, added `$request = $request->withRoute($matched->route->controller, $matched->route->action)` after the 404 check and before the handler closure and `$this->pipeline->process(...)` call. The route-bearing request flows through the middleware pipeline and into the controller handler automatically.
