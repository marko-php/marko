# Marko Rate Limiting

Cache-backed rate limiter with route middleware--throttle requests by IP with configurable limits and automatic `Retry-After` headers.

## Overview

Rate limiting uses the cache layer to track request attempts per key (typically client IP). When limits are exceeded, the middleware returns a 429 response with a `Retry-After` header. Responses include `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers so clients can self-throttle.

## Installation

```bash
composer require marko/rate-limiting
```

Requires `marko/cache` for the storage backend and `marko/routing` for the middleware.

## Usage

### Route Middleware

Apply `RateLimitMiddleware` to routes that need throttling:

```php
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
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

The middleware defaults to 60 requests per 60 seconds. Configure via constructor injection:

```php
use Marko\RateLimiting\Contracts\RateLimiterInterface;
use Marko\RateLimiting\Middleware\RateLimitMiddleware;

$middleware = new RateLimitMiddleware(
    limiter: $limiter,
    maxAttempts: 100,
    decaySeconds: 120,
);
```

### Using the Rate Limiter Directly

For custom throttling logic, inject `RateLimiterInterface`:

```php
use Marko\RateLimiting\Contracts\RateLimiterInterface;

public function __construct(
    private readonly RateLimiterInterface $limiter,
) {}

public function processLogin(
    string $email,
): void {
    $result = $this->limiter->attempt(
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
if ($this->limiter->tooManyAttempts('api:' . $ip, 60)) {
    // Already rate-limited
}
```

### Clearing Rate Limits

Reset the counter for a key (e.g., after successful login):

```php
$this->limiter->clear("login:$email");
```

## Customization

Replace `RateLimiter` via Preferences to change the keying strategy or storage:

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
