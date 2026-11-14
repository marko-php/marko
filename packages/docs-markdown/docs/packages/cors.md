---
title: marko/cors
description: CORS middleware for Marko — enables browser-based frontends and mobile apps to access your API by adding the correct HTTP headers automatically.
---

CORS middleware for Marko --- enables browser-based frontends and mobile apps to access your API by adding the correct HTTP headers automatically.

Cross-Origin Resource Sharing (CORS) headers tell browsers which origins, methods, and headers are permitted when making cross-domain requests. Without them, your API is inaccessible to JavaScript running on a different domain.

Installing the package is enough: `CorsMiddleware` registers itself as **global middleware** and runs on every request, matched or not (it is marked `#[RunsOnUnmatched]`), before every other framework global middleware (page cache, sessions, authentication, authorization, layout). It does nothing unless the request carries an `Origin` header from an allowed origin and its path matches `paths`. A browser preflight is answered with a `204` before any controller, session or auth code runs, and every other cross-origin response --- including cached pages, `401`/`403` and `404`/`405` responses --- gets the CORS headers.

## Installation

```bash
composer require marko/cors
```

## Configuration

All options are set via environment variables and default values are defined in `config/cors.php`:

```php title="config/cors.php"
use Marko\Config\Env;

return [
    'paths' => Env::list('CORS_PATHS', ['*']),
    'allowed_origins' => Env::list('CORS_ALLOWED_ORIGINS', []),
    'allowed_methods' => Env::list('CORS_ALLOWED_METHODS', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']),
    'allowed_headers' => Env::list('CORS_ALLOWED_HEADERS', ['Content-Type', 'Authorization']),
    'expose_headers' => Env::list('CORS_EXPOSE_HEADERS', []),
    'supports_credentials' => Env::bool('CORS_SUPPORTS_CREDENTIALS', false),
    'max_age' => Env::int('CORS_MAX_AGE', 0, min: 0),
];
```

| Environment Variable        | Default                             | Description                                               |
|-----------------------------|-------------------------------------|-----------------------------------------------------------|
| `CORS_PATHS`                | `*`                                 | Comma-separated path patterns CORS applies to             |
| `CORS_ALLOWED_ORIGINS`      | _(empty)_                           | Comma-separated origins allowed to access the API         |
| `CORS_ALLOWED_METHODS`      | `GET,POST,PUT,PATCH,DELETE,OPTIONS` | Comma-separated HTTP methods allowed in CORS requests     |
| `CORS_ALLOWED_HEADERS`      | `Content-Type,Authorization`        | Comma-separated request headers the browser may send      |
| `CORS_EXPOSE_HEADERS`       | _(empty)_                           | Comma-separated response headers the browser may read     |
| `CORS_SUPPORTS_CREDENTIALS` | `false`                             | Whether cookies and auth headers are allowed (`true`, `false`, `1`, `0`, `yes`, `no`, `on`, `off`) |
| `CORS_MAX_AGE`              | `0`                                 | Preflight cache duration in seconds (`0` disables caching)|

To override defaults, publish `config/cors.php` into your application and modify it directly, or set the corresponding environment variables.

With no allowed origins configured (the default), the middleware never adds headers, so installing the package changes nothing until you list an origin.

## Usage

### Limiting CORS to Some Paths

`paths` holds patterns matched against the request path without its leading slash. `*` matches any characters, including `/`. The default `*` covers every path. To apply CORS to your API only:

```bash
CORS_PATHS=api/*
```

`api/*` matches `/api/users` and `/api/v1/users/7` but not `/api` itself; add `api` as a second pattern if you need it. Requests outside `paths` pass through untouched.

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

> **Restriction:** A wildcard origin (`*`) cannot be combined with `CORS_SUPPORTS_CREDENTIALS=true`. Setting both throws `CorsException::wildcardWithCredentials()` loudly on the first cross-origin request. Either restrict `CORS_ALLOWED_ORIGINS` to explicit origins, or keep `CORS_SUPPORTS_CREDENTIALS=false`.

### Preflight Requests

A browser sends a preflight before a cross-origin request that is not "simple" (a JSON `POST`, a `PUT`/`DELETE`, or any custom header). A preflight is an `OPTIONS` request with an `Origin` and an `Access-Control-Request-Method` header. When the origin is allowed and the path is covered, the middleware answers it directly with `204 No Content` and these headers:

- `Access-Control-Allow-Origin` --- the request's origin
- `Access-Control-Allow-Methods` --- `allowed_methods`
- `Access-Control-Allow-Headers` --- `allowed_headers`
- `Access-Control-Allow-Credentials: true` --- when `supports_credentials` is on
- `Access-Control-Max-Age` --- when `max_age` is above `0`
- `Vary: Origin`

No route needs to exist for `OPTIONS`. `CorsMiddleware` is marked `#[RunsOnUnmatched]`, so it is one of the few global middleware that still run when no route matches (see [Which middleware runs](/docs/packages/routing/#which-middleware-runs)). It answers the preflight with `204` whether or not any route exists for the path: a preflight to an unknown path that `paths` covers also gets a `204` with CORS headers, and the browser's actual request then receives a `404` that carries the CORS headers too, so client code can read the status. Session, CSRF and auth middleware never run for preflights. An `OPTIONS` request without `Access-Control-Request-Method` is not a preflight; it continues to the router, which answers it automatically with `204` and an `Allow` header (see [HEAD and OPTIONS](/docs/packages/routing/#head-and-options)), and gets the normal CORS headers.

### Actual Requests

For any other request from an allowed origin, the response from the rest of the pipeline gets:

- `Access-Control-Allow-Origin` --- the request's origin
- `Vary: Origin` --- appended to an existing `Vary` header, so caches store separate entries per origin
- `Access-Control-Allow-Credentials: true` --- when `supports_credentials` is on
- `Access-Control-Expose-Headers` --- `expose_headers`, when any are configured

### Sending Cookies and Auth Headers

To allow browsers to send credentials (cookies, `Authorization` headers), restrict `CORS_ALLOWED_ORIGINS` to explicit origins first, then enable:

```bash
CORS_ALLOWED_ORIGINS=https://app.example.com
CORS_SUPPORTS_CREDENTIALS=true
```

### Exposing Response Headers

Browsers only let JavaScript read a small set of response headers. List any others the frontend needs, such as pagination headers:

```bash
CORS_EXPOSE_HEADERS=X-Total-Count,Link
```

### Preflight Caching

Set `CORS_MAX_AGE` to avoid repeated preflight requests:

```bash
CORS_MAX_AGE=3600
```

This adds `Access-Control-Max-Age: 3600` to preflight responses, telling the browser to cache the result for one hour.

### Upgrading from Per-Route CORS

Earlier versions required `#[Middleware(CorsMiddleware::class)]` on controllers or routes. The middleware is now global, so remove those attributes --- otherwise it runs twice on those routes. Use `paths` to scope CORS instead. `marko/security` no longer ships its own `CorsMiddleware`; see [marko/security](/docs/packages/security/).

## API Reference

### CorsMiddleware

```php
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

public function handle(
    Request $request,
    callable $next,
): Response;
```

Processes the request: checks `paths` and the `Origin` header, answers preflight requests with a `204` response, and adds CORS headers to all other responses for allowed origins. Implements `MiddlewareInterface`. Registered as `globalMiddleware` in the package's `module.php`.

### CorsConfig

```php
use Marko\Config\ConfigRepositoryInterface;

public function __construct(private ConfigRepositoryInterface $config);

public function paths(): array;
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
use Marko\Cors\Exceptions\CorsException;

public static function wildcardWithCredentials(): self;
public function getContext(): string;
public function getSuggestion(): string;
```

Base exception for CORS-related errors. Extends [`MarkoException`](/docs/packages/core/) --- carries a `context` (where the error occurred) and a `suggestion` (how to fix it). `wildcardWithCredentials()` is thrown when `allowed_origins` contains `*` and `supports_credentials` is `true` simultaneously.
