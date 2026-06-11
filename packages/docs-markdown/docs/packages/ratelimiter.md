---
title: marko/ratelimiter
description: Cache-backed rate limiter with route middleware — throttle requests by IP with configurable limits and automatic Retry-After headers.
---

Cache-backed rate limiter with route middleware --- throttle requests by IP with configurable limits and automatic `Retry-After` headers. Rate limiting uses the [cache](/docs/packages/cache/) layer to track request attempts per key (typically client IP) via atomic increments. When limits are exceeded, the middleware returns a 429 response with a `Retry-After` header. Responses include `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers so clients can self-throttle.

## Installation

```bash
composer require marko/ratelimiter
```

Requires [`marko/cache`](/docs/packages/cache/) for the storage backend and [`marko/routing`](/docs/packages/routing/) for the middleware.

## Configuration

Publish the default config and add it to your project:

```php title="config/ratelimiter.php"
return [
    /*
    | A list of IP addresses (IPv4 or IPv6) that are trusted reverse proxies.
    | When REMOTE_ADDR matches a trusted proxy, the X-Forwarded-For header is
    | honored to resolve the real client IP. The right-most untrusted hop in
    | the XFF chain is used to prevent header-spoofing attacks.
    |
    | Default: [] (no proxies trusted — REMOTE_ADDR is always used directly)
    */
    'trusted_proxies' => [],
];
```

| Key | Default | Description |
|---|---|---|
| `trusted_proxies` | `[]` | IP addresses of trusted reverse proxies. When empty, `REMOTE_ADDR` is always used and `X-Forwarded-For` is ignored. |

## Usage

### Route Middleware

Apply `RateLimitMiddleware` to routes that need throttling:

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;
use Marko\RateLimiter\Middleware\RateLimitMiddleware;

class ApiController
{
    #[Get('/api/data')]
    #[Middleware(RateLimitMiddleware::class)]
    public function index(): Response
    {
        return new Response('OK');
    }
}
```

The middleware defaults to 60 requests per 60 seconds. It resolves the real client IP via `ClientIpResolver`: `REMOTE_ADDR` is used directly unless it belongs to a configured trusted proxy, in which case the right-most untrusted hop from `X-Forwarded-For` is used instead. Configure limits via constructor injection:

```php
use Marko\RateLimiter\ClientIpResolver;
use Marko\RateLimiter\Contracts\RateLimiterInterface;
use Marko\RateLimiter\Middleware\RateLimitMiddleware;

$middleware = new RateLimitMiddleware(
    limiter: $rateLimiter,
    clientIpResolver: $clientIpResolver,
    maxAttempts: 100,
    decaySeconds: 120,
);
```

### Using the Rate Limiter Directly

For custom throttling logic, inject `RateLimiterInterface`:

```php
use Marko\RateLimiter\Contracts\RateLimiterInterface;

public function __construct(
    private readonly RateLimiterInterface $rateLimiter,
) {}

public function processLogin(
    string $email,
): void {
    $result = $this->rateLimiter->attempt(
        "login:$email",
        5,
        300,
    );

    if (!$result->allowed()) {
        // Too many attempts, retry after $result->retryAfter() seconds
    }
}
```

### Checking Without Incrementing

Check if a key is rate-limited without consuming an attempt:

```php
if ($this->rateLimiter->tooManyAttempts('api:' . $ip, 60)) {
    // Already rate-limited
}
```

### Clearing Rate Limits

Reset the counter for a key --- for example, after a successful login:

```php
$this->rateLimiter->clear("login:$email");
```

## Customization

Replace `RateLimiter` via [Preferences](/docs/packages/core/) to change the keying strategy or storage:

```php
use Marko\Core\Attributes\Preference;
use Marko\RateLimiter\RateLimiter;
use Marko\RateLimiter\RateLimitResult;

#[Preference(replaces: RateLimiter::class)]
readonly class SlidingWindowRateLimiter extends RateLimiter
{
    public function attempt(
        string $key,
        int $maxAttempts,
        int $decaySeconds,
    ): RateLimitResult {
        // Custom sliding window logic
    }
}
```

## API Reference

### RateLimiterInterface

```php
public function attempt(string $key, int $maxAttempts, int $decaySeconds): RateLimitResult;
public function tooManyAttempts(string $key, int $maxAttempts): bool;
public function clear(string $key): void;
```

### RateLimitResult

```php
public function allowed(): bool;
public function remaining(): int;
public function retryAfter(): ?int;
```

### ClientIpResolver

Resolves the real client IP from a request, respecting the `ratelimiter.trusted_proxies` config. When `REMOTE_ADDR` is not in the trusted list, `X-Forwarded-For` is ignored entirely (prevents header forgery). When it is trusted, the right-most untrusted hop in the `X-Forwarded-For` chain is returned.

```php
use Marko\RateLimiter\ClientIpResolver;

public function resolve(Request $request): string;
```

Throws `ClientIpException` if `REMOTE_ADDR` is missing, and `ConfigNotFoundException` if the config key is absent.

### RateLimitMiddleware

```php
public function handle(Request $request, callable $next): Response;
```

Constructor parameters:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$limiter` | `RateLimiterInterface` | --- | The rate limiter instance |
| `$clientIpResolver` | `ClientIpResolver` | --- | Resolves the real client IP |
| `$maxAttempts` | `int` | `60` | Maximum requests allowed in the window |
| `$decaySeconds` | `int` | `60` | Time window in seconds before attempts reset |
