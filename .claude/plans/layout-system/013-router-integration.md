# Task 013: Router Integration

**Status**: completed
**Depends on**: 008, 010
**Retry count**: 0

## Description
Integrate the layout system with Marko's Router. When a controller has a `#[Layout]` attribute, the router should delegate rendering to `LayoutProcessor` instead of the normal `wrapResult()` flow. This is done via a `LayoutMiddleware` that inspects the matched controller for `#[Layout]` and hands off to `LayoutProcessor`.

**Important:** `Router` is a `readonly class` and has no `RouterInterface`, so Plugins cannot intercept it (the interceptor generator throws `PluginException::cannotInterceptReadonly()`). Middleware is the correct integration mechanism.

## Context
- Related files: `packages/routing/src/Router.php` (the `handle()` and `wrapResult()` methods), `packages/routing/src/Middleware/MiddlewareInterface.php`, `packages/routing/src/MatchedRoute.php`
- The middleware checks for `#[Layout]` on the matched controller class/method via `LayoutResolver`
- If `#[Layout]` is present, invoke `LayoutProcessor::process()` and return its Response
- If no `#[Layout]`, call `$next($request)` to let the normal flow proceed
- The controller action is still called by the Router's normal flow for non-layout routes
- For layout routes, the middleware must have access to the matched route data (controller class, method, route parameters) to pass to `LayoutProcessor`
- The middleware needs the matched route info. Options: (a) the middleware re-resolves the route from the request path, (b) Router stores matched route on the Request, or (c) the middleware uses `RouteMatcher` directly. Approach (c) is cleanest -- inject `RouteMatcher` or `RouteCollection` into the middleware.
- When `#[Layout]` is present, the controller's return value is ignored -- components provide their own data via `data()` methods. The controller action is still invoked (for side effects like authorization), but its return is not used.
- The middleware lives in `packages/layout/src/Middleware/` since it belongs to the layout package

## Requirements (Test Descriptions)
- [ ] `it delegates to LayoutProcessor when controller has Layout attribute`
- [ ] `it preserves existing behavior when controller has no Layout attribute`
- [ ] `it passes route parameters to LayoutProcessor`
- [ ] `it handles method-level Layout attribute override`
- [ ] `it works within the middleware pipeline`
- [ ] `it ignores controller return value when Layout is present`
- [ ] `it still invokes the controller action for layout routes (for side effects)`

## Acceptance Criteria
- All requirements have passing tests
- Uses Middleware (not Plugin) since Router is readonly and cannot be intercepted
- Layout package remains decoupled from routing internals
- The middleware implements `MiddlewareInterface` from the routing package
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
