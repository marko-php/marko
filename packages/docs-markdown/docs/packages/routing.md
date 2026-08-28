---
title: marko/routing
description: Attribute-based routing with automatic conflict detection — define routes on controller methods, not in separate files.
---

Routes live on the methods they handle. Conflicts are caught at boot time with clear error messages. Override vendor routes cleanly via [Preferences](/docs/packages/core/), or disable them explicitly with `#[DisableRoute]`.

## Installation

```bash
composer require marko/routing
```

## Usage

### Defining Routes

Add route attributes to controller methods:

```php title="ProductController.php"
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Response;

class ProductController
{
    #[Get('/products')]
    public function index(): Response
    {
        return new Response('Product list');
    }

    #[Get('/products/{id}')]
    public function show(
        int $id,
    ): Response {
        return new Response("Product $id");
    }

    #[Post('/products')]
    public function store(): Response
    {
        return new Response('Created', 201);
    }
}
```

Route parameters, POST body values, and query string values are automatically resolved and passed to method arguments. Typed scalar parameters (`int`, `float`, `bool`, `string`) are cast to the declared type. If a required typed scalar parameter cannot be found in the route, POST body, or query string, the router returns a `400` response (via `InvalidRouteParameterException`) instead of throwing a `TypeError`.

### Available Methods

```php
#[Get('/path')]
#[Post('/path')]
#[Put('/path')]
#[Patch('/path')]
#[Delete('/path')]
```

### Adding Middleware

```php title="AdminController.php"
use Marko\Routing\Attributes\Middleware;

class AdminController
{
    #[Get('/admin/dashboard')]
    #[Middleware(AuthMiddleware::class)]
    public function dashboard(): Response
    {
        return new Response('Admin dashboard');
    }
}
```

Middleware classes implement `MiddlewareInterface`:

```php title="AuthMiddleware.php"
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        if (!$this->isAuthenticated($request)) {
            return new Response('Unauthorized', 401);
        }

        return $next($request);
    }
}
```

### Decorating Responses

Middleware that adds headers, cookies, or changes the status code should decorate the response returned by `$next()` rather than construct a new one:

```php title="SecurityHeadersMiddleware.php"
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $response = $next($request);

        return $response->withHeader('X-Frame-Options', 'DENY');
    }
}
```

`withHeader()`, `withHeaders()`, `withStatus()`, and `withCookie()` each return a clone of the response, preserving its concrete class. Rebuilding a response instead, e.g. `new Response($response->body(), $response->statusCode(), $headers)`, silently discards subclass identity --- a `StreamingResponse` returned by an SSE endpoint would be downgraded to a plain `Response` and its stream would never send. Always decorate, never rebuild.

### Setting Cookies

Attach a cookie to a response with `withCookie()`:

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Cookie;
use Marko\Routing\Http\Response;

#[Get('/login')]
public function login(): Response
{
    return Response::json(['ok' => true])
        ->withCookie(new Cookie(
            name: 'session',
            value: $sessionId,
            expires: time() + 3600,
            path: '/',
            secure: true,
            httpOnly: true,
            sameSite: 'Lax',
        ));
}
```

`expires: null` or `expires: 0` omits the `Expires` attribute entirely, producing a browser-session cookie instead of a persistent one. Cookie values are `rawurlencode()`d automatically, so a value containing `;` cannot inject a second attribute. An invalid cookie name throws `CookieException`, as does `sameSite: 'None'` without `secure: true` --- browsers silently drop such cookies, so Marko fails loudly instead.

Read cookies sent by the client with `Request::cookie()`, which mirrors `query()` and `post()`:

```php
$sessionId = $request->cookie('session');
```

### Overriding Vendor Routes

Use [Preferences](/docs/packages/core/) to replace a vendor's controller:

```php title="MyPostController.php"
use Marko\Core\Attributes\Preference;
use Marko\Routing\Attributes\Get;
use Vendor\Blog\PostController;

#[Preference(replaces: PostController::class)]
class MyPostController extends PostController
{
    #[Get('/blog')]  // Your route takes over
    public function index(): Response
    {
        return new Response('My custom blog');
    }
}
```

### Disabling Routes

Explicitly remove an inherited route:

```php title="MyPostController.php"
use Marko\Routing\Attributes\DisableRoute;

#[Preference(replaces: PostController::class)]
class MyPostController extends PostController
{
    #[DisableRoute]  // Removes /blog/{slug} route
    public function show(
        string $slug,
    ): Response {
        // Method still exists but has no route
    }
}
```

### Route Conflicts

If two modules define the same route, Marko throws `RouteConflictException` at boot:

```
Route conflict detected for GET /products

Defined in:
  - Vendor\Catalog\ProductController::index()
  - App\Store\ProductController::list()

Resolution: Use #[Preference] to extend one controller,
or use #[DisableRoute] to remove one route.
```

## CLI

Requires [`marko/cli`](/docs/packages/cli/) for the `marko` binary.

### Listing Routes

See all registered routes:

```bash
marko route:list
```

```
METHOD  PATH            ACTION                    MIDDLEWARE
GET     /               HelloController::index
GET     /blog           PostController::index
GET     /blog/{id}      PostController::show
GET     /products       ProductController::index
GET     /products/{id}  ProductController::show
```

Filter by HTTP method or path:

```bash
marko route:list --method=POST
marko route:list --path=products
marko route:list --method=GET --path=blog
```

## API Reference

### Route Attributes

```php
#[Get(path: '/path', middleware: [])]
#[Post(path: '/path')]
#[Put(path: '/path')]
#[Patch(path: '/path')]
#[Delete(path: '/path')]
#[DisableRoute]
#[Middleware(MiddlewareClass::class)]
```

### Request

```php
class Request
{
    public function method(): string;
    public function path(): string;
    public function query(?string $key = null, mixed $default = null): mixed;
    public function post(?string $key = null, mixed $default = null): mixed;
    public function cookie(?string $key = null, mixed $default = null): mixed;
    public function body(): string;
    public function header(string $name, ?string $default = null): ?string;
    public function headers(): array;
    public function server(string $key): ?string;
    public function ip(): ?string;
    public function withRoute(string $controller, string $action): self;
    public function controller(): ?string;
    public function action(): ?string;
    public static function fromGlobals(): self;
}
```

`ip()` returns `REMOTE_ADDR` from the server bag (equivalent to `server('REMOTE_ADDR')`). `cookie()` reads from the request's `$_COOKIE` bag and mirrors the signature of `query()` and `post()`. `withRoute()` returns a new immutable `Request` with the matched controller class and action method attached; `controller()` and `action()` retrieve them. The router attaches route context before invoking middleware, which allows middleware (such as `AdminAuthMiddleware`) to inspect which controller method is handling the request.

### Response

```php
class Response
{
    public function __construct(
        string $body = '',
        int $statusCode = 200,
        array $headers = [],
    );

    public function body(): string;
    public function statusCode(): int;
    public function headers(): array;
    public function cookies(): array;
    public function headerLines(): array;
    public function send(): void;
    public static function json(mixed $data, int $statusCode = 200): self;
    public static function html(string $html, int $statusCode = 200): self;
    public static function redirect(string $url, int $statusCode = 302): self;
    public function withHeader(string $name, string $value): static;
    public function withHeaders(array $headers): static;
    public function withStatus(int $statusCode): static;
    public function withCookie(Cookie $cookie): static;
}
```

`cookies()` returns the `Cookie` instances attached to the response; `headerLines()` returns the raw `"Name: value"` lines followed by one `Set-Cookie:` line per cookie, without making any SAPI calls --- `send()` uses it internally, and it's also useful for testing. `withHeader()` and `withHeaders()` merge into the existing headers (`withHeaders()` merges its argument over the current set). `withCookie()` replaces an existing cookie that matches on `(name, path, domain)`, or appends a new one otherwise. Every `with*()` method is marked `#[\NoDiscard]` and returns a clone via PHP's `clone` operator rather than `new static(...)`, so a `Response` subclass such as `StreamingResponse` survives decoration intact --- see [Decorating Responses](#decorating-responses). `Response` is deliberately not a `readonly class` for this reason: immutability is enforced by API design (private properties, no setters) rather than the `readonly` keyword.

`json()`, `html()`, and `redirect()` construct with `new self()`, not `new static()`, since a subclass such as `StreamingResponse` has a different constructor signature and cannot accept `(body:, statusCode:, headers:)`.

### Cookie

```php
use Marko\Routing\Http\Cookie;

public function __construct(
    string $name,
    string $value = '',
    ?int $expires = null,
    ?string $path = null,
    ?string $domain = null,
    bool $secure = false,
    bool $httpOnly = false,
    ?string $sameSite = null,
)

public function name(): string;
public function path(): ?string;
public function domain(): ?string;
public function toSetCookieString(): string;
```

`expires: null` or `expires: 0` omits the `Expires` attribute, producing a browser-session cookie. The value passed to `toSetCookieString()` is `rawurlencode()`d. The constructor throws `CookieException` for an invalid cookie name (control characters, whitespace, or separator characters such as `( ) < > @ , ; : \ " / [ ] ? = { }`), and also throws when `sameSite` is `'None'` without `secure: true`.

### MiddlewareInterface

```php
interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
```

### Parameter Resolution

The router resolves controller method parameters in priority order: route path params → POST body → query string → default value. Typed scalars (`int`, `float`, `bool`, `string`) are automatically cast. A required typed scalar with no matching source throws `InvalidRouteParameterException`, which the router catches and converts to a `400` response. Route path literals containing dots or other regex metacharacters are matched literally (via `preg_quote`). URL-encoded path segments are decoded once before matching.
