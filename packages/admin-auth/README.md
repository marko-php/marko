# marko/admin-auth

Admin authentication and role-based authorization---manages admin users, roles, permissions, and access control for the admin panel.

## Installation

```bash
composer require marko/admin-auth
```

## Quick Example

```php
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;

class ProductController
{
    #[Get('/admin/catalog/products')]
    #[Middleware(AdminAuthMiddleware::class)]
    #[RequiresPermission(permission: 'catalog.products.view')]
    public function index(): Response
    {
        // Only admin users with 'catalog.products.view' permission
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/admin-auth](https://marko.build/docs/packages/admin-auth/)
