# marko/rate-limiting

Cache-backed rate limiter with route middleware — throttle requests by IP with configurable limits and automatic `Retry-After` headers.

## Installation

```bash
composer require marko/rate-limiting
```

## Quick Example

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\RateLimiting\Middleware\RateLimitMiddleware;

class ApiController
{
    #[Get('/api/data')]
    #[Middleware(RateLimitMiddleware::class)]
    public function index(): Response
    {
        return new Response('OK');
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/rate-limiting](https://marko.build/docs/packages/rate-limiting/)
