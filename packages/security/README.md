# Marko Security

CSRF protection, CORS handling, and security headers middleware--secure your routes with drop-in middleware.

## Overview

Three middleware classes cover the most common web security needs: `CsrfMiddleware` validates tokens on state-changing requests, `CorsMiddleware` handles preflight and cross-origin headers, and `SecurityHeadersMiddleware` adds protective response headers (HSTS, CSP, X-Frame-Options, etc.). All are configured via `config/security.php`.

## Installation

```bash
composer require marko/security
```

Requires `marko/session` and `marko/encryption` for CSRF token management.

## Usage

### CSRF Protection

Apply `CsrfMiddleware` to routes that accept form submissions:

```php
use Marko\Routing\Attributes\Post;
use Marko\Routing\Attributes\Middleware;
use Marko\Security\Middleware\CsrfMiddleware;

class FormController
{
    #[Post('/contact')]
    #[Middleware(CsrfMiddleware::class)]
    public function submit(): Response
    {
        // Token validated automatically
        return new Response('Submitted');
    }
}
```

The middleware checks `_token` in POST data or the `X-CSRF-TOKEN` header. Safe methods (GET, HEAD, OPTIONS) are skipped.

Include the token in forms:

```php
use Marko\Security\Contracts\CsrfTokenManagerInterface;

public function __construct(
    private readonly CsrfTokenManagerInterface $csrf,
) {}

public function form(): Response
{
    $token = $this->csrf->get();
    // Render form with <input type="hidden" name="_token" value="$token">
}
```

### CORS Middleware

Handle cross-origin requests and preflight `OPTIONS` responses:

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Security\Middleware\CorsMiddleware;

class ApiController
{
    #[Get('/api/products')]
    #[Middleware(CorsMiddleware::class)]
    public function list(): Response
    {
        return new Response('Products');
    }
}
```

Configure allowed origins, methods, and headers in `config/security.php`:

```php
return [
    'cors' => [
        'allowed_origins' => ['https://example.com'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
        'allowed_headers' => ['Content-Type', 'Authorization'],
        'max_age' => 3600,
    ],
];
```

### Security Headers Middleware

Add protective HTTP headers to all responses:

```php
use Marko\Routing\Attributes\Middleware;
use Marko\Security\Middleware\SecurityHeadersMiddleware;

#[Middleware(SecurityHeadersMiddleware::class)]
```

Headers are configured in `config/security.php`:

```php
return [
    'headers' => [
        'x_content_type_options' => 'nosniff',
        'x_frame_options' => 'DENY',
        'x_xss_protection' => '1; mode=block',
        'strict_transport_security' => 'max-age=31536000; includeSubDomains',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'content_security_policy' => "default-src 'self'",
    ],
];
```

Empty values are omitted from the response.

### Using the CSRF Token Manager Directly

Regenerate tokens (e.g., after login):

```php
$newToken = $this->csrf->regenerate();
```

Validate manually:

```php
if (!$this->csrf->validate($submittedToken)) {
    // Invalid token
}
```

## Customization

Replace `CsrfTokenManager` via Preferences to change token generation or storage:

```php
use Marko\Core\Attributes\Preference;
use Marko\Security\CsrfTokenManager;

#[Preference(replaces: CsrfTokenManager::class)]
class MyCsrfTokenManager extends CsrfTokenManager
{
    public function get(): string
    {
        // Custom token retrieval logic
    }
}
```

## API Reference

### CsrfTokenManagerInterface

```php
public function get(): string;
public function validate(string $token): bool;
public function regenerate(): string;
```

### CsrfMiddleware

```php
public function handle(Request $request, callable $next): Response;
```

### CorsMiddleware

```php
public function handle(Request $request, callable $next): Response;
```

### SecurityHeadersMiddleware

```php
public function handle(Request $request, callable $next): Response;
```

### SecurityConfig

```php
public function csrfSessionKey(): string;
public function corsAllowedOrigins(): array;
public function corsAllowedMethods(): array;
public function corsAllowedHeaders(): array;
public function corsMaxAge(): int;
public function headerXContentTypeOptions(): string;
public function headerXFrameOptions(): string;
public function headerXXssProtection(): string;
public function headerStrictTransportSecurity(): string;
public function headerReferrerPolicy(): string;
public function headerContentSecurityPolicy(): string;
```
