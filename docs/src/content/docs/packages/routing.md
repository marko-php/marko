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

Route parameters are automatically passed to method arguments.

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
    public function body(): string;
    public function header(string $name, ?string $default = null): ?string;
    public function headers(): array;
    public static function fromGlobals(): self;
}
```

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
    public function send(): void;
    public static function json(mixed $data, int $statusCode = 200): self;
    public static function html(string $html, int $statusCode = 200): self;
    public static function redirect(string $url, int $statusCode = 302): self;
}
```

### MiddlewareInterface

```php
interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
```
