# marko/cors

CORS middleware for Marko --- enables browser-based frontends and mobile apps to access your API by adding the correct HTTP headers automatically.

## Installation

```bash
composer require marko/cors
```

## Quick Example

```php
use Marko\Cors\Middleware\CorsMiddleware;
use Marko\Routing\Attributes\Middleware;

#[Middleware(CorsMiddleware::class)]
class PostController
{
    public function index(): Response
    {
        // CORS headers added automatically
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/cors](https://marko.build/docs/packages/cors/)
