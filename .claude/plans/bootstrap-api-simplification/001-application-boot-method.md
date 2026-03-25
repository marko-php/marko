# Task 001: Fix Router hard type dependency

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Change the `Router` property type declarations in `Application.php` from hard `?Router` / `Router` types to `?object` / `object` with `@var` docblocks. This fixes a fatal error when `marko/core` is loaded without `marko/routing` installed — PHP cannot resolve the `Router` type at class-load time if the routing package is absent.

## Context
- Related files: `packages/core/src/Application.php`
- Lines 67-71: `private ?Router $_router = null;` and `public Router $router { get => ... }`
- The `use Marko\Routing\Router;` import at line 42 is safe (PHP `use` is just an alias, doesn't trigger autoloading)
- The `discoverRoutes()` method already guards with `class_exists(RoutingBootstrapper::class)` — runtime is fine, the issue is class-load time type resolution
- Other routing imports (`RouteConflictException`, `RouteException`, `MiddlewareInterface`) are only used in docblocks and inside the `class_exists` guard — those are safe
- `marko/core` does NOT require `marko/routing` in composer.json — routing is optional

## Requirements (Test Descriptions)
- [ ] `it loads Application class without marko/routing installed (no Router type fatal)`
- [ ] `it stores router as nullable object property`
- [ ] `it exposes router via public property hook that throws RuntimeException("Router not available. Install marko/routing: composer require marko/routing") when null`
- [ ] `it still assigns Router instance correctly when routing is available`

## Acceptance Criteria
- All requirements have passing tests
- `$_router` type is `?object` with `@var ?Router` docblock
- `$router` public property type is `object` with `@var Router` docblock
- Property hook behavior unchanged (throws RuntimeException when null), but message updated to "Router not available. Install marko/routing: composer require marko/routing"
- All existing tests still pass
- Code follows project standards

## Implementation Notes
Change from:
```php
private ?Router $_router = null;

public Router $router {
    get => $this->_router ?? throw new RuntimeException('Router not available. Call boot() first.');
}
```

To:
```php
/** @var ?Router */
private ?object $_router = null;

/** @var Router */
public object $router {
    get => $this->_router ?? throw new RuntimeException('Router not available. Install marko/routing: composer require marko/routing');
}
```

**Important**: The error message MUST be "Router not available. Install marko/routing: composer require marko/routing" (not "Call boot() first."). Task 002 also updates this message as part of the rename. Both tasks use the same final message to avoid conflicts if they run in parallel.

The `use Marko\Routing\Router;` import can remain — it's only an alias and is needed for the docblocks and for type safety inside `discoverRoutes()` where the actual `Router` object is assigned.

Keep the `use` imports for `RouteConflictException`, `RouteException`, `MiddlewareInterface`, `RoutingBootstrapper` — they're all used inside the `class_exists` guard or in docblocks only.
