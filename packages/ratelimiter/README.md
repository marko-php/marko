# marko/ratelimiter

Rate limiting for Marko — throttle requests by key with configurable windows and cache-backed hit counts.

## Overview

`marko/ratelimiter` provides a simple, cache-backed rate limiter that integrates with Marko's routing layer via middleware. Define limits by key (IP, user ID, API token, etc.) with configurable max attempts and decay windows. The `RateLimiter` class is the core service; `RateLimitMiddleware` applies limits to routes automatically.

## Installation

```bash
composer require marko/ratelimiter
```

## Usage

Apply the middleware to a route group:

```php
use Marko\RateLimiter\Middleware\RateLimitMiddleware;

$router->group(['middleware' => RateLimitMiddleware::class], function ($router): void {
    $router->get('/api/search', SearchController::class);
});
```

Use the service directly for custom logic:

```php
use Marko\RateLimiter\RateLimiter;

$result = $rateLimiter->attempt(
    key: 'api:' . $request->ip(),
    maxAttempts: 60,
    decaySeconds: 60,
);

if ($result->exceeded()) {
    throw new TooManyRequestsException($result->retryAfter);
}
```

## API Reference

- `RateLimiter::attempt(string $key, int $maxAttempts, int $decaySeconds)` — Record a hit and return a `RateLimitResult`
- `RateLimiter::clear(string $key)` — Reset the counter for a key
- `RateLimitResult::exceeded()` — Whether the limit has been breached
- `RateLimitResult::$remaining` — Remaining attempts
- `RateLimitResult::$retryAfter` — Seconds until the window resets

## Documentation

Full configuration and middleware usage: [marko/ratelimiter](https://marko.build/docs/packages/ratelimiter/)
