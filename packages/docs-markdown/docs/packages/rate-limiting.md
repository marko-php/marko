---
title: marko/rate-limiting
description: Cache-backed rate limiter with route middleware — throttle requests by IP with configurable limits and automatic Retry-After headers.
---

Cache-backed rate limiter with route middleware --- throttle requests by IP with configurable limits and automatic `Retry-After` headers. Rate limiting uses the [cache](/docs/packages/cache/) layer to track request attempts per key (typically client IP). When limits are exceeded, the middleware returns a 429 response with a `Retry-After` header. Responses include `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers so clients can self-throttle.

## Installation

```bash
composer require marko/rate-limiting
```

Requires [`marko/cache`](/docs/packages/cache/) for the storage backend and [`marko/routing`](/docs/packages/routing/) for the middleware.

## Usage

### Route Middleware

Apply `RateLimitMiddleware` to routes that need throttling:

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;
use Marko\RateLimiting\Middleware\RateLimitMiddleware;

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

The middleware defaults to 60 requests per 60 seconds. It resolves the client IP from the `X-Forwarded-For` header, falling back to `Remote-Addr`. Configure limits via constructor injection:

```php
use Marko\RateLimiting\Contracts\RateLimiterInterface;
use Marko\RateLimiting\Middleware\RateLimitMiddleware;

$middleware = new RateLimitMiddleware(
    limiter: $rateLimiter,
    maxAttempts: 100,
    decaySeconds: 120,
);
```

### Using the Rate Limiter Directly

For custom throttling logic, inject `RateLimiterInterface`:

```php
use Marko\RateLimiting\Contracts\RateLimiterInterface;

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
use Marko\RateLimiting\RateLimiter;
use Marko\RateLimiting\RateLimitResult;

#[Preference(replaces: RateLimiter::class)]
class SlidingWindowRateLimiter extends RateLimiter
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

### RateLimitMiddleware

```php
public function handle(Request $request, callable $next): Response;
```

Constructor parameters:

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$limiter` | `RateLimiterInterface` | --- | The rate limiter instance |
| `$maxAttempts` | `int` | `60` | Maximum requests allowed in the window |
| `$decaySeconds` | `int` | `60` | Time window in seconds before attempts reset |
