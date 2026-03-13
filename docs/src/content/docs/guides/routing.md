---
title: Routing
description: Define routes with PHP attributes, middleware, and route groups.
---

Marko uses PHP attributes to define routes directly on controller methods. No separate route files, no registration boilerplate.

## Defining Routes

```php title="app/blog/Controller/PostController.php"
<?php

declare(strict_types=1);

namespace App\Blog\Controller;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Attributes\Delete;
use Marko\Http\ResponseInterface;
use Marko\Http\JsonResponse;

class PostController
{
    #[Get('/posts')]
    public function index(): ResponseInterface
    {
        return new JsonResponse(data: ['posts' => []]);
    }

    #[Get('/posts/{id}')]
    public function show(int $id): ResponseInterface
    {
        return new JsonResponse(data: ['id' => $id]);
    }

    #[Post('/posts')]
    public function store(): ResponseInterface
    {
        return new JsonResponse(data: ['created' => true], status: 201);
    }

    #[Delete('/posts/{id}')]
    public function destroy(int $id): ResponseInterface
    {
        return new JsonResponse(status: 204);
    }
}
```

## Available HTTP Method Attributes

| Attribute | HTTP Method |
|---|---|
| `#[Get]` | GET |
| `#[Post]` | POST |
| `#[Put]` | PUT |
| `#[Patch]` | PATCH |
| `#[Delete]` | DELETE |

## Route Parameters

Parameters in `{braces}` are automatically injected into the method by name:

```php
#[Get('/users/{userId}/posts/{postId}')]
public function show(int $userId, int $postId): ResponseInterface
{
    // $userId and $postId are extracted from the URL
}
```

## Middleware

Apply middleware to routes using the `#[Middleware]` attribute:

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Authentication\Middleware\AuthMiddleware;

class AdminController
{
    #[Get('/admin/dashboard')]
    #[Middleware(AuthMiddleware::class)]
    public function dashboard(): ResponseInterface
    {
        // Only authenticated users reach this
    }
}
```

### Multiple Middleware

```php
#[Get('/admin/settings')]
#[Middleware(AuthMiddleware::class)]
#[Middleware(AdminRoleMiddleware::class)]
public function settings(): ResponseInterface
{
    // Must be authenticated AND have admin role
}
```

## Route Conflict Detection

Marko detects route conflicts at boot time, not at request time. If two controllers register the same path and method, you get a loud error immediately — not a mysterious 404 in production.

## Route Overrides

Higher-priority modules can override routes from lower-priority modules. If `vendor/marko/blog` defines `GET /posts` and your `app/blog` also defines `GET /posts`, your version wins.

## Disabling Routes

To remove a vendor route without replacing it, use the `#[DisableRoute]` attribute. Place it on a method that also has the route attribute you want to disable --- `#[DisableRoute]` takes no parameters and simply disables the route defined by the preceding routing attribute:

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\DisableRoute;

class BlogRouteOverrides
{
    #[Get('/blog/rss')]
    #[DisableRoute]
    public function disableRss(): void {}
}
```

## Creating Middleware

Middleware implements `MiddlewareInterface`:

```php title="app/myapp/Middleware/RateLimitMiddleware.php"
<?php

declare(strict_types=1);

namespace App\MyApp\Middleware;

use Marko\Http\RequestInterface;
use Marko\Http\ResponseInterface;
use Marko\Routing\Middleware\MiddlewareInterface;

class RateLimitMiddleware implements MiddlewareInterface
{
    public function handle(
        RequestInterface $request,
        callable $next,
    ): ResponseInterface {
        // Before the controller
        $this->checkRateLimit($request);

        // Call the next middleware or controller
        $response = $next($request);

        // After the controller
        return $response->withHeader('X-RateLimit-Remaining', '99');
    }
}
```

## Next Steps

- [Database](/docs/guides/database/) — persist and query data
- [Authentication](/docs/guides/authentication/) — protect routes
- [Middleware deep dive](/docs/concepts/plugins/) — how plugins compare to middleware
- [Routing package reference](/docs/packages/routing/) — full API details
