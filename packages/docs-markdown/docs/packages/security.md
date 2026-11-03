---
title: marko/security
description: CSRF protection and security headers middleware -- secure your routes with drop-in middleware.
---

CSRF protection and security headers middleware --- secure your routes with drop-in middleware. Two middleware classes, both registered globally, cover the most common web security needs: `CsrfMiddleware` validates tokens on every state-changing request, and `SecurityHeadersMiddleware` adds protective response headers (HSTS, CSP, X-Frame-Options, etc.). Both are configured via `config/security.php`.

For cross-origin requests, install [marko/cors](/docs/packages/cors/). `marko/security` used to ship its own `CorsMiddleware` with `security.cors.*` config; it was removed so there is a single CORS implementation. Replace any `#[Middleware]` reference to the old class with `marko/cors` (which registers itself globally) and move the `cors` block of `config/security.php` to `config/cors.php`.

## Installation

```bash
composer require marko/security
```

Requires [marko/session](/docs/packages/session/) and [marko/encryption](/docs/packages/encryption/) for CSRF token management.

## Configuration

Both middleware classes read from `config/security.php`:

```php title="config/security.php"
return [
    'csrf' => [
        'session_key' => '_csrf_token',
    ],
    'headers' => [
        'x_content_type_options' => 'nosniff',
        'x_frame_options' => 'SAMEORIGIN',
        'x_xss_protection' => '0',
        'strict_transport_security' => 'max-age=31536000; includeSubDomains',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'content_security_policy' => "default-src 'self'",
    ],
];
```

`csrf.session_key` is the session key `CsrfTokenManager` stores the token under.

## Usage

### CSRF Protection

Installing `marko/security` protects every route. Its `module.php` registers `CsrfMiddleware` as **global** middleware (running inside the session middleware), so a `POST`, `PUT`, `PATCH` or `DELETE` to any matched route must carry a valid token or it is rejected with `419`. Nothing has to be added to your controllers:

```php
use Marko\Routing\Attributes\Post;

class FormController
{
    #[Post('/contact')]
    public function submit(): Response
    {
        // Token already validated by the global CsrfMiddleware
        return new Response('Submitted');
    }
}
```

The middleware reads the token from the `_token` form field, the `X-CSRF-TOKEN` header, or the `X-XSRF-TOKEN` header. Safe methods (GET, HEAD, OPTIONS) are skipped.

It carries `#[RunsInnermost]`, so it checks the token after the route's own middleware (see [Innermost global middleware](/docs/packages/routing/#innermost-global-middleware)). An auth guard on the route answers a logged out visitor's stale form with its login redirect, not a `419`. When that middleware logs the visitor in, for example from a remember-me cookie, the login rotates the token, and the token the request arrived with is still accepted for that one request.

Route middleware therefore also sees requests that end in a `419`. A route that needs the token checked before its own middleware, for a rate limiter that should only count verified requests, excludes the global check and lists `RouteCsrfMiddleware` where the check belongs. The admin panel's login route does this:

```php
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Security\Middleware\RouteCsrfMiddleware;

#[Middleware([RouteCsrfMiddleware::class, RateLimitMiddleware::class])]
#[WithoutMiddleware(CsrfMiddleware::class)]
class ContactController
```

`#[WithoutMiddleware]` removes middleware by exact class, so `RouteCsrfMiddleware`, a subclass, stays. A `#[Preference]` that replaces `CsrfMiddleware` does not reach `RouteCsrfMiddleware`, so such a route, the admin login included, keeps the shipped check; prefer `RouteCsrfMiddleware` as well if you change how the token is read.

A response that route middleware returns before reaching `CsrfMiddleware`, such as an auth redirect, does not carry a refreshed `XSRF-TOKEN` cookie. The next response that passes through it does.

It never runs on requests that match no route, because it does not carry `#[RunsOnUnmatched]` (see [Which middleware runs](/docs/packages/routing/#which-middleware-runs)). A `POST` to an unknown path gets a `404`, and a `POST` to a GET-only path gets a `405` with `Allow` --- never a `419` token mismatch.

:::caution[Upgrading from per-route CSRF]
In earlier releases, `CsrfMiddleware` only ran on routes that declared `#[Middleware(CsrfMiddleware::class)]`. Now it runs on every route. Remove the now-redundant `#[Middleware(CsrfMiddleware::class)]` attributes, make sure every form and JavaScript client sends the token, and exempt the routes that cannot carry one (see below).
:::

### Exempting Routes

Webhook receivers, token-authenticated APIs and other routes that are not driven by a browser session opt out explicitly with [`#[WithoutMiddleware]`](/docs/packages/routing/#skipping-middleware), on one route or on a whole controller:

```php
use Marko\Routing\Attributes\Post;
use Marko\Routing\Attributes\WithoutMiddleware;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Session\Middleware\SessionMiddleware;

#[WithoutMiddleware([SessionMiddleware::class, CsrfMiddleware::class])]
class StripeWebhookController
{
    #[Post('/webhooks/stripe')]
    public function handle(): Response
    {
        // Verify the provider's signature instead
    }
}
```

A route that excludes `SessionMiddleware` but not `CsrfMiddleware` has no session to compare tokens against. A state-changing request to it throws `CsrfSessionUnavailableException` (a loud `500`) that tells you to exempt the route, rather than rejecting every request with a misleading `419`.

### Tokens in Forms

Issuing a token (`CsrfTokenManagerInterface::get()` the first time) writes it to the session. That counts as a modification, so the session is saved and the visitor gets a session cookie even under [lazy session persistence](/docs/packages/session/#lazy-persistence). The form submission then carries the cookie that holds the token.

Include the token in forms:

```php
use Marko\Security\Contracts\CsrfTokenManagerInterface;

readonly class ContactController
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    public function form(): Response
    {
        $token = $this->csrfTokenManager->get();
        // Render form with <input type="hidden" name="_token" value="$token">
    }
}
```

### SPAs, Inertia and axios (`XSRF-TOKEN`)

Whenever a response persists the session (the request resumed the visitor's session, or modified it --- issuing a token counts), `CsrfMiddleware` mirrors the current token into an `XSRF-TOKEN` cookie. axios, and therefore Inertia, read that cookie and send it back as the `X-XSRF-TOKEN` header on every same-origin request, so SPA requests pass the CSRF check with no extra code.

| Attribute | Value |
|---|---|
| `HttpOnly` | off --- JavaScript must read it |
| `SameSite` | `Lax` |
| `Secure`, `Path`, `Domain` | follow the session cookie (`session.cookie.*` in `config/session.php`) |
| Lifetime | browser session; re-issued whenever the token differs from the one the client sent |

A request whose session is only read and then discarded (a cookieless visitor on a page that never issues a token) gets no `XSRF-TOKEN` cookie, so bots and health checks still create no sessions. If the first page of your SPA neither starts a session nor renders a form, issue the token there --- for example call `$this->csrfTokenManager->get()` in the controller that renders the SPA's root view --- so the first `POST` already has a cookie to echo.

### Security Headers

Installing `marko/security` adds protective headers to every response. Its `module.php` registers `SecurityHeadersMiddleware` as **global** middleware, outside `CsrfMiddleware` so a `419` carries the headers too. It declares `#[RunsOnUnmatched]`, so 404 and 405 responses get them as well (see [Which middleware runs](/docs/packages/routing/#which-middleware-runs)).

Headers are configured in `config/security.php` under the `headers` key (see [Configuration](#configuration) above):

- An empty value omits that header.
- A header the response already carries is left alone (names compared case-insensitively). A controller or inner middleware that sets its own `Content-Security-Policy` or `X-Frame-Options` keeps it, so one route can send a stricter or looser policy than the default.
- `Strict-Transport-Security` is sent only on HTTPS responses: when the server reports `HTTPS` (anything but `off`), `REQUEST_SCHEME` is `https`, or the first `X-Forwarded-Proto` value is `https` (a TLS-terminating proxy). Browsers ignore HSTS on plain HTTP, so a forged `X-Forwarded-Proto` gains nothing.
- `X-XSS-Protection` defaults to `0`, which turns off the legacy browser XSS auditor. The old `1; mode=block` value is deprecated and opened XS-Leak attacks in the browsers that still honoured it. Set the value to `''` to omit the header.

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

class ReportController
{
    #[Get('/reports/embed')]
    public function embed(): Response
    {
        // Kept as-is: the global middleware only fills in headers that are missing
        return new Response('<p>Report</p>', 200, [
            'Content-Security-Policy' => "frame-ancestors https://partner.example.com",
        ]);
    }
}
```

To drop the headers from a route entirely, exclude the middleware with [`#[WithoutMiddleware]`](/docs/packages/routing/#skipping-middleware), on one route or a whole controller:

```php
use Marko\Routing\Attributes\WithoutMiddleware;
use Marko\Security\Middleware\SecurityHeadersMiddleware;

#[WithoutMiddleware(SecurityHeadersMiddleware::class)]
```

:::caution[Upgrading from per-route security headers]
In earlier releases, `SecurityHeadersMiddleware` only ran on routes that declared `#[Middleware(SecurityHeadersMiddleware::class)]`, and its headers replaced any the controller had set. Now every response gets them, and the controller's own headers win. Remove the redundant `#[Middleware(SecurityHeadersMiddleware::class)]` attributes, check that the default `Content-Security-Policy` (`default-src 'self'`) does not block assets your pages load from other origins (set `content_security_policy` in `config/security.php`, or `''` to omit it), and change `x_xss_protection` to `'0'` if your published `config/security.php` still has `'1; mode=block'`.
:::

### Token Rotation on Login and Logout

When [marko/authentication](/docs/packages/authentication/) is installed, the token is regenerated on every `LoginEvent` and `LogoutEvent` (by the `RotateCsrfTokenOnLogin` and `RotateCsrfTokenOnLogout` observers). A token issued before login --- including one an attacker learned by planting a session from a sibling subdomain --- stops validating on every request after the one that logged the victim in, and the next person on a shared machine does not inherit the logged-out user's token. Forms rendered before the login or logout must be reloaded to pick up the new token. Without marko/authentication these events are never dispatched, so nothing changes.

### Using the CSRF Token Manager Directly

Regenerate tokens (e.g., after a privilege change of your own):

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
// suggestion: "Ensure your form includes a valid CSRF token field (_token), or send it in the X-CSRF-TOKEN or X-XSRF-TOKEN header..."
```

`CsrfTokenMismatchException` implements `Marko\Core\Exceptions\HttpExceptionInterface`, so the routing pipeline renders it as **`419 Page Expired`** with the body `{"message": "CSRF token mismatch."}` (JSON, or a minimal HTML page for browsers). The detailed message, context and suggestion above stay server-side. Because the response is rendered where the middleware threw, outer middleware such as CORS and security headers still decorate it --- see [Errors and HTTP Exceptions](/docs/packages/routing/#errors-and-http-exceptions).

## API Reference

### CsrfTokenManagerInterface

```php
use Marko\Security\Contracts\CsrfTokenManagerInterface;

public function get(): string;       // Get token, generating one if none exists
public function validate(string $token): bool;  // Validate against the stored token, or the one this request arrived with
public function regenerate(): string; // Regenerate token, replacing the previous one
```

### CsrfMiddleware

```php
use Marko\Security\Middleware\CsrfMiddleware;

public const string COOKIE_NAME = 'XSRF-TOKEN';

public function __construct(
    CsrfTokenManagerInterface $tokenManager,
    SessionInterface $session,
    SessionConfig $sessionConfig,
);
public function handle(Request $request, callable $next): Response;
```

### RouteCsrfMiddleware

```php
use Marko\Security\Middleware\RouteCsrfMiddleware;

class RouteCsrfMiddleware extends CsrfMiddleware {} // the same check, at the position a route lists it
```

### CsrfTokenManager

```php
use Marko\Security\CsrfTokenManager;

public function __construct(
    SessionInterface $session,
    EncryptorInterface $encryptor,
    string $sessionKey = '_csrf_token', // module.php passes security.csrf.session_key
);
```

### SecurityHeadersMiddleware

```php
use Marko\Security\Middleware\SecurityHeadersMiddleware;

#[RunsOnUnmatched]
public function __construct(SecurityConfig $securityConfig);
public function handle(Request $request, callable $next): Response;
```

### SecurityConfig

```php
use Marko\Security\Config\SecurityConfig;

public function csrfSessionKey(): string;
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
public function getStatusCode(): int;       // 419
public function getHeaders(): array;        // []
public function getResponseData(): array;   // ['message' => 'CSRF token mismatch.']
```

### CsrfSessionUnavailableException

```php
use Marko\Security\Exceptions\CsrfSessionUnavailableException;

public static function forRequest(string $method, string $path): self;
```
