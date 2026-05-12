# Task 007: CacheabilityChecker Service

**Status**: completed
**Depends on**: 003, 005
**Retry count**: 0

## Description
Create the `CacheabilityChecker` service that decides whether a request and response are eligible for caching, and reads the `#[Cacheable]` attribute from the matched route's controller action.

## Context
- Related files:
  - `packages/routing/src/RouteMatcherInterface.php` and `RouteMatcher.php` (injected here for re-matching)
  - `packages/routing/src/Http/Request.php`, `Response.php`
  - `packages/page-cache/src/Attributes/Cacheable.php` (from task 003)
  - `packages/page-cache/src/Config/PageCacheConfig.php` (from task 005)
- Patterns to follow:
  - `readonly class`
  - Constructor injection
  - Methods return plain bools or nullable `Cacheable` attribute instance — no exceptions for normal flow

## Requirements (Test Descriptions)
- [ ] `it accepts GET requests as cacheable`
- [ ] `it accepts HEAD requests as cacheable`
- [ ] `it rejects POST requests as not cacheable`
- [ ] `it rejects requests with methods not in the configured cacheable methods list`
- [ ] `it accepts responses with status code 200 as cacheable`
- [ ] `it rejects responses with status code 500 as not cacheable`
- [ ] `it rejects responses with status codes not in the configured cacheable status codes list`
- [ ] `it rejects responses with a Set-Cookie header as not cacheable`
- [ ] `it rejects responses whose Cache-Control header contains the no-store directive among others (e.g. "private, no-store, max-age=0")`
- [ ] `it rejects responses whose Cache-Control header contains the private directive among others (e.g. "private, max-age=0")`
- [ ] `it parses Cache-Control directives case-insensitively (e.g. "NO-STORE" rejected the same as "no-store")`
- [ ] `it accepts responses with a Cache-Control header that contains only public directives (e.g. "public, max-age=600")`
- [ ] `it returns the Cacheable attribute when the matched route declares it`
- [ ] `it returns null when the matched route has no Cacheable attribute`
- [ ] `it returns null when no route matches the request`
- [ ] `it returns null when the matched route's controller class does not exist (defensive)`
- [ ] `it returns null when the matched route's action method does not exist on the controller (defensive)`

## Acceptance Criteria
- `src/CacheabilityChecker.php` is a `readonly class`
- Public methods: `isRequestCacheable(Request): bool`, `isResponseCacheable(Response): bool`, `getRouteAttribute(Request): ?Cacheable`
- Constructor injects `RouteMatcherInterface` and `PageCacheConfig`
- `getRouteAttribute` reflects on `$matched->route->controller::$matched->route->action` for `#[Cacheable]`. The `controller` property is a `class-string` (set by `RouteDefinition`), so reflecting on it directly hits the original class — `PluginInterceptedInterface` unwrapping is not required at this layer (interception only matters for instances resolved from the container, not for class-strings carried by `RouteDefinition`).
- `getRouteAttribute` calls `RouteMatcherInterface::match($request->method(), $request->path())` — pass through the route's HTTP method, not a hardcoded `'GET'`. Returns `null` if no match (covers 404 routes, methods filtered by `RouteCollection::byMethod()`).
- `getRouteAttribute` defends against `ReflectionException` from missing class/method by returning `null` rather than throwing — a missing controller class or action would already explode in `Router::handle()`, but the cache layer must not be the source of new exceptions during a request.
- `isResponseCacheable` parses `Cache-Control` as comma-separated directives, trimming whitespace and lower-casing each token before comparing against `no-store` and `private`. Do **not** use `str_contains` against the raw header value (false positives on tokens like `no-store-after-redirect` are unlikely but exact-string match is also wrong because `private, max-age=0` would not match `private` alone). Recommended: `array_map('trim', explode(',', strtolower($header)))` then check membership; treat each token's directive name (left of `=`) as the directive.
- `Response::headers()` returns header keys as the application set them (e.g., `'Cache-Control'`, `'Set-Cookie'`); the checker must look up headers case-insensitively (existing applications and middleware may use different casings). Implement a small helper to find a header by case-insensitive key.
- Tests in `tests/Unit/CacheabilityCheckerTest.php` use fixture controllers with annotated methods
- Strict types declared
- All `@throws` propagated (e.g., `ConfigNotFoundException`); `getRouteAttribute` MUST NOT declare `@throws ReflectionException` — it catches and returns `null` per the requirement above.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
