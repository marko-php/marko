# marko/cors

CORS middleware for Marko—enables browser-based frontends and mobile apps to access your API by adding the correct HTTP headers automatically.

## Overview

Cross-Origin Resource Sharing (CORS) headers tell browsers which origins, methods, and headers are permitted when making cross-domain requests. Without them, your API is inaccessible to JavaScript running on a different domain.

This package provides `CorsMiddleware` that inspects each request, validates the origin, and attaches the appropriate response headers. Preflight `OPTIONS` requests are handled automatically and short-circuited with a `204` response—no controller code runs for them.

Apply the middleware per-controller or per-route using the `#[Middleware]` attribute.

## Installation

```bash
composer require marko/cors
```

## Usage

### Applying to a Controller

To enable CORS for all routes in a controller, add the `#[Middleware]` attribute at the class level:

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

### Applying to Individual Routes

Apply the attribute on a specific method to scope CORS to that route only:

```php
use Marko\Cors\Middleware\CorsMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;

class PostController
{
    #[Get('/posts')]
    #[Middleware(CorsMiddleware::class)]
    public function index(): Response
    {
        // CORS headers added only on this route
    }
}
```

### Allowing Specific Origins

Configure allowed origins via the `CORS_ALLOWED_ORIGINS` environment variable (comma-separated):

```bash
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com
```

### Wildcard Origin

To allow any origin (useful for fully public APIs):

```bash
CORS_ALLOWED_ORIGINS=*
```

When a wildcard is configured, all origins are permitted.

### Sending Cookies and Auth Headers

To allow browsers to send credentials (cookies, `Authorization` headers):

```bash
CORS_SUPPORTS_CREDENTIALS=true
```

When enabled, `Access-Control-Allow-Credentials: true` is added to each response.

## Configuration

All options are set via environment variables and default values are defined in `config/cors.php`.

| Environment Variable      | Default                                       | Description                                                   |
|---------------------------|-----------------------------------------------|---------------------------------------------------------------|
| `CORS_ALLOWED_ORIGINS`    | _(empty)_                                     | Comma-separated origins allowed to access the API             |
| `CORS_ALLOWED_METHODS`    | `GET,POST,PUT,PATCH,DELETE,OPTIONS`           | Comma-separated HTTP methods allowed in CORS requests         |
| `CORS_ALLOWED_HEADERS`    | `Content-Type,Authorization`                  | Comma-separated request headers the browser may send          |
| `CORS_EXPOSE_HEADERS`     | _(empty)_                                     | Comma-separated response headers the browser may read         |
| `CORS_SUPPORTS_CREDENTIALS` | `false`                                     | Whether cookies and auth headers are allowed                  |
| `CORS_MAX_AGE`            | `0`                                           | Preflight cache duration in seconds (`0` disables caching)    |

To override defaults, publish `config/cors.php` into your application and modify it directly, or set the corresponding environment variables.

### Preflight Caching

Set `CORS_MAX_AGE` to avoid repeated preflight requests:

```bash
CORS_MAX_AGE=3600
```

This adds `Access-Control-Max-Age: 3600` to preflight responses, telling the browser to cache the result for one hour.

## API Reference

### CorsMiddleware

```php
public function handle(Request $request, callable $next): Response;
```

Processes the request: validates the `Origin` header, handles `OPTIONS` preflight requests with a `204` response, and appends CORS headers to all other responses from allowed origins.

### CorsConfig

```php
public function allowedOrigins(): array;
public function allowedMethods(): array;
public function allowedHeaders(): array;
public function exposeHeaders(): array;
public function supportsCredentials(): bool;
public function maxAge(): int;
```

Reads CORS configuration from the config repository under the `cors.*` namespace.

### CorsException

```php
public function getContext(): string;
public function getSuggestion(): string;
```

Base exception for CORS-related errors. Carries a `context` (where the error occurred) and a `suggestion` (how to fix it).
