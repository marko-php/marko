---
title: marko/admin-api
description: Authenticated JSON endpoints for the admin panel --- exposes sections, menu items, and current user data for headless or SPA-based admin clients.
---

Authenticated JSON endpoints for the admin panel --- exposes admin sections, menu items, and current user data for headless or SPA-based admin clients. All responses follow a consistent `{data, meta}` / `{errors}` envelope format. Sections are filtered by user permissions, section detail includes nested menu items, and the current user endpoint returns roles and permissions. Routes are protected by `AdminAuthMiddleware`.

## Installation

```bash
composer require marko/admin-api
```

Requires [`marko/admin`](/docs/packages/admin/) and `marko/admin-auth`.

## Usage

### Available Endpoints

All endpoints require admin authentication. Unauthenticated requests receive a 401 JSON response.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/api/v1/sections` | List all sections (filtered by permissions) |
| GET | `/admin/api/v1/sections/{id}` | Section detail with menu items |
| GET | `/admin/api/v1/me` | Current authenticated user profile |

### List Sections

```
GET /admin/api/v1/sections
```

Response:

```json
{
    "data": [
        {
            "id": "catalog",
            "label": "Catalog",
            "icon": "box",
            "sort_order": 20
        }
    ],
    "meta": {}
}
```

Sections are filtered based on the authenticated user's permissions --- only sections with at least one accessible menu item are returned.

### Section Detail

```
GET /admin/api/v1/sections/catalog
```

Response:

```json
{
    "data": {
        "id": "catalog",
        "label": "Catalog",
        "icon": "box",
        "sort_order": 20,
        "menu_items": [
            {
                "id": "products",
                "label": "Products",
                "url": "/admin/catalog/products",
                "icon": "package",
                "sort_order": 10,
                "permission": "catalog.products.view"
            }
        ]
    },
    "meta": {}
}
```

Returns 404 if the section ID does not exist.

### Current User

```
GET /admin/api/v1/me
```

Response:

```json
{
    "data": {
        "id": 1,
        "email": "admin@example.com",
        "name": "Admin User",
        "roles": [
            {"id": 1, "name": "Administrator", "slug": "admin"}
        ],
        "permissions": ["catalog.products.view", "catalog.products.edit"]
    },
    "meta": {}
}
```

### Using ApiResponse in Custom Endpoints

Build consistent JSON responses for your own admin API controllers:

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

    #[Get('/admin/api/v1/orders/{id}')]
    public function show(
        int $id,
    ): Response {
        $order = $this->findOrder($id);

        if ($order === null) {
            return ApiResponse::notFound("Order #$id not found");
        }

        return ApiResponse::success(data: [
            'id' => $order->id,
            'status' => $order->status,
        ]);
    }
}
```

## API Reference

### ApiResponse

```php
use Marko\AdminApi\ApiResponse;
use Marko\Routing\Http\Response;

class ApiResponse
{
    public static function success(array $data = [], array $meta = []): Response;
    public static function created(array $data = [], array $meta = []): Response;
    public static function error(array $errors, int $statusCode = 400): Response;
    public static function paginated(array $data, int $page, int $perPage, int $total): Response;
    public static function notFound(string $message = 'Not found'): Response;
    public static function forbidden(string $message = 'Forbidden'): Response;
    public static function unauthorized(string $message = 'Unauthorized'): Response;
}
```

### AdminApiConfigInterface

```php
use Marko\AdminApi\Config\AdminApiConfigInterface;

interface AdminApiConfigInterface
{
    public function getVersion(): string;
    public function getRateLimit(): int;
    public function getGuardName(): string;
}
```
