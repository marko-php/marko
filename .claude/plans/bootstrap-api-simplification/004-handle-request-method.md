# Task 004: Add Application::handleRequest() method

**Status**: completed
**Depends on**: 001, 003
**Retry count**: 0

## Description
Add a `handleRequest()` method to Application that encapsulates the full web request lifecycle: create a Request from globals, route it, and send the Response. This eliminates the need for users to manually call `Request::fromGlobals()`, `$app->router->handle()`, and `$response->send()`.

## Context
- Related files: `packages/core/src/Application.php`, `packages/routing/src/Http/Request.php`, `packages/routing/src/Http/Response.php`, `packages/routing/src/Router.php`
- The Router is optional (only available if `marko/routing` is installed). Must check availability and throw a helpful error.
- Current working pattern: `$request = Request::fromGlobals(); $response = $app->router->handle($request); $response->send();`
- `Router::handle(Request $request): Response` — takes a Request, returns a Response
- `Response::send(): void` — emits headers and body
- The `$_router` private property is `?object` (after task 001) and `null` when routing is not installed. Use `$this->_router === null` to check availability.
- **CRITICAL**: The router availability check MUST come BEFORE calling `Request::fromGlobals()`, because `Request` is a class from `marko/routing` and would fail if that package is not installed.

## Requirements (Test Descriptions)
- [ ] `it throws RuntimeException with helpful message when routing package is not installed`
- [ ] `it creates a request from globals and routes it through the router`
- [ ] `it sends the response after routing`
- [ ] `it returns void`

## Acceptance Criteria
- All requirements have passing tests
- `handleRequest()` checks `$this->_router === null` FIRST, throws RuntimeException before touching any routing classes
- Error message: "Cannot handle HTTP requests: marko/routing is not installed. Run: composer require marko/routing"
- Code follows project standards

## Implementation Notes

**Required imports**: Add these `use` statements to Application.php (they do not currently exist):
```php
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
```
These are safe -- PHP `use` is just an alias and does not trigger autoloading. The runtime guard on `$this->_router === null` prevents any actual usage of these classes when routing is not installed.

```php
public function handleRequest(): void
{
    if ($this->_router === null) {
        throw new RuntimeException(
            'Cannot handle HTTP requests: marko/routing is not installed. Run: composer require marko/routing'
        );
    }

    $request = Request::fromGlobals();
    $response = $this->_router->handle($request);
    $response->send();
}
```

Note: We access `$this->_router` directly (the private backing property) rather than `$this->router` (the property hook) to avoid the property hook's own RuntimeException. We want our own, more specific error message.

### Testing Strategy

**Error path** (straightforward):
Create an Application with empty paths (no modules, no routing package discovery). Call `handleRequest()` and assert it throws RuntimeException with the expected message. The `_router` will be `null` because `discoverRoutes()` found no routing package.

**Happy path** (requires integration-level setup):
1. Set `$_SERVER['REQUEST_METHOD'] = 'GET'` and `$_SERVER['REQUEST_URI'] = '/'`
2. Use `ob_start()` / `ob_get_clean()` to capture output from `Response::send()`
3. Call `handleRequest()` and verify the captured output
4. `Response::send()` guards with `if (!headers_sent())` so this is safe in CLI tests

If a full module fixture is too complex, testing the error path thoroughly and verifying the method signature is acceptable. The happy path will be covered by integration tests.
