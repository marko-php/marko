# Task 011: F5 — Enforce #[RequiresPermission] in AdminAuthMiddleware via route context

**Status**: pending
**Depends on**: [006]
**Retry count**: 0

## Description
Close the broken-access-control hole: the `#[RequiresPermission]` branch in `AdminAuthMiddleware` is dead because the pipeline instantiates the middleware by class-string and the container fills the `?string $controller`/`?string $action` constructor params with `null`, so `getRequiredPermission()` always returns `null` and any authenticated admin reaches every gated route. Rewrite `getRequiredPermission()` to read the controller/action from the route-bearing `Request` (`$request->controller()`/`$request->action()`, added in task 006) instead of constructor params, and remove the now-unused `?string $controller`/`?string $action` constructor params. Reflect the route's controller+action to find the `#[RequiresPermission]` attribute and enforce it.

## Context
- Related files:
  - `packages/admin-auth/src/Middleware/AdminAuthMiddleware.php` (`handle()` ~33; `getRequiredPermission()` ~117-123; constructor params `?string $controller`/`?string $action`)
  - `packages/admin-auth/src/Attributes/RequiresPermission.php` (`public string $permission`)
  - `packages/admin-auth/tests/` (Pest — likely an existing `AdminAuthMiddlewareTest`)
  - Consumes `Request::controller()`/`action()`/`withRoute()` from task 006.
- Patterns to follow:
  - Existing `getRequiredPermission()` uses `ReflectionMethod($controller, $action)->getAttributes(RequiresPermission::class)` — keep that mechanism, just source controller/action from the request (`$request->controller()`/`$request->action()`).
  - Existing `userHasPermission()` / `forbiddenResponse()` (403) and `PermissionRegistryInterface` wildcard matching are unchanged.
  - Use `marko/testing` fakes for guard/user where possible; build a route-bearing Request via `withRoute()`.
- CRITICAL gotchas (verified against source):
  - `getRequiredPermission()` must now take the `Request` as an argument (or read it from the already-passed `$request` in `handle()`), since the controller/action no longer come from constructor params. Pass `$request` into `getRequiredPermission($request)`.
  - When `$request->controller()`/`$request->action()` are `null` (no route context — e.g. a 404 path or a request not run through `Router::handle()`), return `null` (no permission required), preserving current behavior. Add a test for the no-route-context case.
  - The existing test helper `createMiddleware()` in `AdminAuthMiddlewareTest.php` (lines ~66-80) passes `controller`/`action` to the constructor. This task MUST rewrite that helper to drop those params and instead build a route-bearing `Request` via `Request::withRoute($controller, $action)` and pass it to `handle()`. All existing tests in that file that relied on the ctor params must be updated.
  - `getAttributes(RequiresPermission::class)` only finds the attribute on the controller's real method; the route's controller class-string is the un-intercepted class, so reflection works. No plugin-unwrap needed here.
  - Keep the `@throws ReflectionException` annotation; a non-existent controller/action would throw `ReflectionException` (loud, acceptable).

## Requirements (Test Descriptions)
- [ ] `it allows an authenticated admin through a route with no RequiresPermission attribute`
- [ ] `it denies a low-privilege admin with a 403 on a RequiresPermission route they lack`
- [ ] `it allows a properly-permissioned admin on a RequiresPermission route`
- [ ] `it reads the required permission from the route controller and action on the request`
- [ ] `it requires no permission when the request carries no route context (controller/action null)`
- [ ] `it returns the unauthorized response when the guard reports no authenticated user`
- [ ] `it returns a 403 forbidden response when the authenticated user is not an admin user on a gated route`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
