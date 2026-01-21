# Marko Routing

Attribute-based routing with automatic conflict detection—define routes on controller methods, not in separate files.

## Overview

Routes live on the methods they handle. Conflicts are caught at boot time with clear error messages. Override vendor routes cleanly via Preferences, or disable them explicitly with `#[DisableRoute]`.

## Installation

```bash
composer require marko/routing
```

## Usage

### Defining Routes

Add route attributes to controller methods:

```php
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
    public function show(int $id): Response
    {
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

```php
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

### Overriding Vendor Routes

Use Preferences to replace a vendor's controller:

```php
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

```php
use Marko\Routing\Attributes\DisableRoute;

#[Preference(replaces: PostController::class)]
class MyPostController extends PostController
{
    #[DisableRoute]  // Removes /blog/{slug} route
    public function show(string $slug): Response
    {
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

## API Reference

### Route Attributes

```php
#[Get(path: '/path', name: 'route.name')]
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
    public function getMethod(): string;
    public function getPath(): string;
    public function getQueryParams(): array;
    public function getBody(): string;
}
```

### Response

```php
class Response
{
    public function __construct(
        string $content = '',
        int $status = 200,
        array $headers = [],
    );

    public function getContent(): string;
    public function getStatus(): int;
    public function getHeaders(): array;
}
```

### MiddlewareInterface

```php
interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
```
