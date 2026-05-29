---
title: marko/cors
description: CORS middleware for Marko — enables browser-based frontends and mobile apps to access your API by adding the correct HTTP headers automatically.
---

CORS middleware for Marko --- enables browser-based frontends and mobile apps to access your API by adding the correct HTTP headers automatically.

Cross-Origin Resource Sharing (CORS) headers tell browsers which origins, methods, and headers are permitted when making cross-domain requests. Without them, your API is inaccessible to JavaScript running on a different domain.

This package provides `CorsMiddleware` that inspects each request, validates the origin, and attaches the appropriate response headers. Preflight `OPTIONS` requests are handled automatically and short-circuited with a `204` response --- no controller code runs for them.

Apply the middleware per-controller or per-route using the `#[Middleware]` attribute.

## Installation

```bash
composer require marko/cors
```

## Configuration

All options are set via environment variables and default values are defined in `config/cors.php`:

```php title="config/cors.php"
return [
    'allowed_origins' => array_filter(explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '')),
    'allowed_methods' => explode(',', $_ENV['CORS_ALLOWED_METHODS'] ?? 'GET,POST,PUT,PATCH,DELETE,OPTIONS'),
    'allowed_headers' => explode(',', $_ENV['CORS_ALLOWED_HEADERS'] ?? 'Content-Type,Authorization'),
    'expose_headers' => array_filter(explode(',', $_ENV['CORS_EXPOSE_HEADERS'] ?? '')),
    'supports_credentials' => filter_var($_ENV['CORS_SUPPORTS_CREDENTIALS'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'max_age' => (int) ($_ENV['CORS_MAX_AGE'] ?? 0),
];
```

| Environment Variable        | Default                             | Description                                               |
|-----------------------------|-------------------------------------|-----------------------------------------------------------|
| `CORS_ALLOWED_ORIGINS`      | _(empty)_                           | Comma-separated origins allowed to access the API         |
| `CORS_ALLOWED_METHODS`      | `GET,POST,PUT,PATCH,DELETE,OPTIONS` | Comma-separated HTTP methods allowed in CORS requests     |
| `CORS_ALLOWED_HEADERS`      | `Content-Type,Authorization`        | Comma-separated request headers the browser may send      |
| `CORS_EXPOSE_HEADERS`       | _(empty)_                           | Comma-separated response headers the browser may read     |
| `CORS_SUPPORTS_CREDENTIALS` | `false`                             | Whether cookies and auth headers are allowed              |
| `CORS_MAX_AGE`              | `0`                                 | Preflight cache duration in seconds (`0` disables caching)|

To override defaults, publish `config/cors.php` into your application and modify it directly, or set the corresponding environment variables.

## Usage

### Applying to a Controller

To enable CORS for all routes in a controller, add the `#[Middleware]` attribute at the class level:

```php
use Marko\Cors\Middleware\CorsMiddleware;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;

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
use Marko\Routing\Http\Response;

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

### Preflight Caching

Set `CORS_MAX_AGE` to avoid repeated preflight requests:

```bash
CORS_MAX_AGE=3600
```

This adds `Access-Control-Max-Age: 3600` to preflight responses, telling the browser to cache the result for one hour.

## API Reference

### CorsMiddleware

```php
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

public function handle(Request $request, callable $next): Response;
```

Processes the request: validates the `Origin` header, handles `OPTIONS` preflight requests with a `204` response, and appends CORS headers to all other responses from allowed origins. Implements `MiddlewareInterface`.

### CorsConfig

```php
use Marko\Config\ConfigRepositoryInterface;

public function __construct(private ConfigRepositoryInterface $config);

public function allowedOrigins(): array;
public function allowedMethods(): array;
public function allowedHeaders(): array;
public function exposeHeaders(): array;
public function supportsCredentials(): bool;
public function maxAge(): int;
```

Reads CORS configuration from the [config](/docs/packages/config/) repository under the `cors.*` namespace. All methods throw `ConfigNotFoundException` if the underlying config key is missing.

### CorsException

```php
public function getContext(): string;
public function getSuggestion(): string;
```

Base exception for CORS-related errors. Extends [`MarkoException`](/docs/packages/core/) --- carries a `context` (where the error occurred) and a `suggestion` (how to fix it).
