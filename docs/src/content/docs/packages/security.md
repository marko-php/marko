---
title: marko/security
description: CSRF protection, CORS handling, and security headers middleware -- secure your routes with drop-in middleware.
---

CSRF protection, CORS handling, and security headers middleware --- secure your routes with drop-in middleware. Three middleware classes cover the most common web security needs: `CsrfMiddleware` validates tokens on state-changing requests, `CorsMiddleware` handles preflight and cross-origin headers, and `SecurityHeadersMiddleware` adds protective response headers (HSTS, CSP, X-Frame-Options, etc.). All are configured via `config/security.php`.

## Installation

```bash
composer require marko/security
```

Requires [marko/session](/docs/packages/session/) and [marko/encryption](/docs/packages/encryption/) for CSRF token management.

## Configuration

All three middleware classes read from `config/security.php`:

```php title="config/security.php"
return [
    'csrf' => [
        'session_key' => '_csrf_token',
    ],
    'cors' => [
        'allowed_origins' => ['https://example.com'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
        'allowed_headers' => ['Content-Type', 'Authorization'],
        'max_age' => 3600,
    ],
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

The middleware checks `_token` in POST data or the `X-CSRF-TOKEN` header. Safe methods (GET, HEAD, OPTIONS) are skipped automatically.

Include the token in forms:

```php
use Marko\Security\Contracts\CsrfTokenManagerInterface;

class ContactController
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    public function form(): Response
    {
        $token = $this->csrfTokenManager->get();
        // Render form with <input type="hidden" name="_token" value="$token">
    }
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

Configure allowed origins, methods, and headers in `config/security.php` under the `cors` key (see [Configuration](#configuration) above). When a request includes an `Origin` header that matches the allowed origins list, the middleware adds the appropriate CORS headers. For preflight `OPTIONS` requests, it short-circuits with a `204` response containing `Access-Control-Allow-Origin`, `Access-Control-Allow-Methods`, `Access-Control-Allow-Headers`, and `Access-Control-Max-Age` headers. Use `'*'` in `allowed_origins` to permit any origin.

### Security Headers Middleware

Add protective HTTP headers to all responses:

```php
use Marko\Routing\Attributes\Middleware;
use Marko\Security\Middleware\SecurityHeadersMiddleware;

#[Middleware(SecurityHeadersMiddleware::class)]
```

Headers are configured in `config/security.php` under the `headers` key (see [Configuration](#configuration) above). Empty values are omitted from the response --- only headers with non-empty values are added.

### Using the CSRF Token Manager Directly

Regenerate tokens (e.g., after login):

```php
$newToken = $this->csrfTokenManager->regenerate();
```

Validate manually:

```php
if (!$this->csrfTokenManager->validate($submittedToken)) {
    // Invalid token
}
```

Tokens are generated using 32 random bytes encrypted via [marko/encryption](/docs/packages/encryption/) and stored in the session. The `validate()` method uses timing-safe comparison (`hash_equals`) to prevent timing attacks.

## Customization

Replace `CsrfTokenManager` via [Preferences](/docs/packages/core/) to change token generation or storage:

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

## Exceptions

`CsrfMiddleware` throws `CsrfTokenMismatchException` when validation fails. This exception extends `SecurityException`, which provides rich context and suggestions --- consistent with Marko's loud-errors principle:

```php
use Marko\Security\Exceptions\CsrfTokenMismatchException;

// Thrown automatically by CsrfMiddleware:
// message:    "CSRF token validation failed."
// context:    "The submitted CSRF token does not match the token stored in the session..."
// suggestion: "Ensure your form includes a valid CSRF token field (_token) or X-CSRF-TOKEN header..."
```

## API Reference

### CsrfTokenManagerInterface

```php
use Marko\Security\Contracts\CsrfTokenManagerInterface;

public function get(): string;       // Get token, generating one if none exists
public function validate(string $token): bool;  // Validate against stored token
public function regenerate(): string; // Regenerate token, replacing the previous one
```

### CsrfMiddleware

```php
use Marko\Security\Middleware\CsrfMiddleware;

public function handle(Request $request, callable $next): Response;
```

### CorsMiddleware

```php
use Marko\Security\Middleware\CorsMiddleware;

public function handle(Request $request, callable $next): Response;
```

### SecurityHeadersMiddleware

```php
use Marko\Security\Middleware\SecurityHeadersMiddleware;

public function handle(Request $request, callable $next): Response;
```

### SecurityConfig

```php
use Marko\Security\Config\SecurityConfig;

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

### SecurityException

```php
use Marko\Security\Exceptions\SecurityException;

public function getContext(): string;
public function getSuggestion(): string;
```

### CsrfTokenMismatchException

```php
use Marko\Security\Exceptions\CsrfTokenMismatchException;

public static function invalidToken(): self;
```
