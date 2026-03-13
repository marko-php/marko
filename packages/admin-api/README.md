# marko/admin-api

Admin REST API --- exposes admin sections, menu items, and current user data as JSON endpoints for headless or SPA-based admin clients.

## Installation

```bash
composer require marko/admin-api
```

Requires `marko/admin` and `marko/admin-auth`.

## Quick Example

```php
use Marko\AdminApi\ApiResponse;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;

#[Middleware(AdminAuthMiddleware::class)]
class OrderApiController
{
    #[Get('/admin/api/v1/orders')]
    public function index(): Response
    {
        return ApiResponse::paginated(
            data: $orders,
            page: 1,
            perPage: 20,
            total: 150,
        );
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/admin-api](https://marko.build/docs/packages/admin-api/)
