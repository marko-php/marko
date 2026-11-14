---
title: marko/admin-api
description: Authenticated JSON endpoints for the admin panel --- exposes sections, menu items, and current user data for headless or SPA-based admin clients.
---

Authenticated JSON endpoints for the admin panel --- exposes admin sections, menu items, and current user data for headless or SPA-based admin clients. Successful responses use a `{data, meta}` envelope; every error, whichever layer raises it, is a `{"message": ...}` body rendered by the routing [`ExceptionRenderer`](/docs/packages/routing/#errors-and-http-exceptions). Sections are filtered by user permissions, section detail includes nested menu items, and the current user endpoint returns roles and permissions. Routes are protected by `AdminAuthMiddleware`.

## Installation

```bash
composer require marko/admin-api
```

Requires [`marko/admin`](/docs/packages/admin/) and `marko/admin-auth`.

## Configuration

The package has no configuration of its own. The routes are always under `/admin/api/v1`, and they authenticate on the [admin guard](/docs/packages/admin-auth/#the-admin-guard) named by `admin-auth.guard`. Earlier versions shipped a `config/admin-api.php` with `version`, `rate_limit` and `guard` keys that nothing read, so setting them changed nothing; they have been removed. The endpoints are not rate limited; to throttle them, put [`marko/ratelimiter`](/docs/packages/ratelimiter/)'s `RateLimitMiddleware` in front of them.

## Usage

### Available Endpoints

All endpoints require admin authentication through `AdminAuthMiddleware`, on the [admin guard](/docs/packages/admin-auth/#the-admin-guard) (`admin-auth.guard`), not the application's default guard. When the admin guard is stateless (the token guard from [`marko/authentication-token`](/docs/packages/authentication-token/), the usual setup for a headless admin), an unauthenticated request always gets a `401` with the guard's `WWW-Authenticate` challenge, whatever its `Accept` header. On a stateful (session) guard, an unauthenticated request whose `Accept` header asks for JSON gets a `401` and any other unauthenticated request is redirected to the admin login, so send `Accept: application/json` from API clients that authenticate with the session. A user that is not an admin user, or lacks the required permission, gets a `403`. See [Protecting Admin Routes](/docs/packages/admin-auth/#protecting-admin-routes).

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/api/v1/sections` | List all sections (filtered by permissions) |
| GET | `/admin/api/v1/sections/{id}` | Section detail with menu items |
| GET | `/admin/api/v1/me` | Current authenticated user profile |

### Error Responses

Every error from these endpoints has the same shape, whether the middleware denied the request, the router found no route, or a controller threw:

```json
{"message": "Section 'nope' not found"}
```

| Status | When | Body |
|--------|------|------|
| `401` | No authenticated user (JSON request) | `{"message": "Unauthorized."}`, plus a `WWW-Authenticate` header on a stateless guard |
| `403` | Missing permission, or the authenticated user is not an admin user | `{"message": "Forbidden."}` |
| `404` | Unknown section, or a section the user cannot see | `{"message": "Section 'nope' not found"}` |
| `422` | A custom endpoint's validation fails | `{"message": "The given data was invalid.", "errors": {"field": ["..."]}}` |

Read `message` for every error. Validation errors also carry `errors`, a map of field names to messages. All of these bodies come from the routing [`ExceptionRenderer`](/docs/packages/routing/#errors-and-http-exceptions). To send a different JSON error shape, override its `renderJson()` through a `#[Preference]` (see [Custom error pages](/docs/packages/routing/#custom-error-pages)); that one change covers the middleware, the router, validation and controllers together.

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

Sections are filtered based on the authenticated user's permissions --- only sections with at least one accessible menu item are returned. Permission checks honor wildcard permissions (e.g. a user holding `catalog.*` can access any menu item whose permission starts with `catalog.`). A user that is not an admin user sees no sections, even if the controller is reached without `AdminAuthMiddleware`.

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

Returns 404 if the section ID does not exist or if the authenticated user does not have access to any menu item in that section (same visibility rules as the list endpoint).

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

Return success responses with `ApiResponse` and throw [`HttpException`](/docs/packages/routing/#errors-and-http-exceptions) for errors, so your endpoints answer in the same shapes as the built-in ones:

```php
use Marko\AdminApi\ApiResponse;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Exceptions\HttpException;
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
        $order = $this->findOrder($id)
            ?? throw HttpException::notFound("Order #$id not found");

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
    public static function success(
        array $data = [],
        array $meta = [],
    ): Response;
    public static function created(
        array $data = [],
        array $meta = [],
    ): Response;
    public static function paginated(
        array $data,
        int $page,
        int $perPage,
        int $total,
    ): Response;
}
```

`ApiResponse` builds success responses only. For errors, throw an `HttpException` (`notFound()`, `forbidden()`, `unauthorized()`, `badRequest()`, ...) from `marko/routing`.
