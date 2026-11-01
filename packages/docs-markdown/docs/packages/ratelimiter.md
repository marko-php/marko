---
title: marko/ratelimiter
description: Cache-backed rate limiter with route middleware — per-route limits via an attribute, IPv6 /64 bucketing, CIDR trusted proxies and automatic Retry-After headers.
---

Cache-backed rate limiter with route middleware --- set limits per route with `#[RateLimit]`, throttle by client IP (or any identity you choose), and send `Retry-After` headers automatically. Rate limiting uses the [cache](/docs/packages/cache/) layer to count attempts per key with atomic increments, so it works with every cache driver, including [`marko/cache-redis`](/docs/packages/cache-redis/). When a limit is exceeded, the middleware returns a JSON 429 response. Every response includes `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers so clients can throttle themselves.

## Installation

```bash
composer require marko/ratelimiter
```

Requires [`marko/cache`](/docs/packages/cache/) for the storage backend and [`marko/routing`](/docs/packages/routing/) for the middleware.

## Configuration

Publish the default config and add it to your project:

```php title="config/ratelimiter.php"
return [
    'default_max_attempts' => 60,
    'default_decay_seconds' => 60,
    'trusted_proxies' => [],
    'ipv6_prefix' => 64,
];
```

| Key | Default | Description |
|---|---|---|
| `default_max_attempts` | `60` | Requests allowed per window on a route without a `#[RateLimit]` attribute, or whose attribute leaves `maxAttempts` unset. |
| `default_decay_seconds` | `60` | Window length in seconds on a route without a `#[RateLimit]` attribute, or whose attribute leaves `decaySeconds` unset. |
| `trusted_proxies` | `[]` | Trusted reverse proxies: IP addresses (IPv4 or IPv6) or CIDR ranges such as `10.0.0.0/8` or `2001:db8::/32`. When empty, `REMOTE_ADDR` is always used and `X-Forwarded-For` is ignored. An invalid entry throws `ClientIpException`. |
| `ipv6_prefix` | `64` | Network prefix length (1--128) IPv6 clients are bucketed by. `128` keys by the full address. |

## Usage

### Route Middleware

Apply `RateLimitMiddleware` to the routes that need throttling. With no other settings, each route allows `default_max_attempts` requests per `default_decay_seconds` window for each client:

```php
use Marko\RateLimiter\Middleware\RateLimitMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;

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

Each route keeps its own counter: the bucket key is `{controller}::{action}|{client}`. Login attempts and API reads no longer share one budget.

### Per-Route Limits

Add `#[RateLimit]` to a controller class or method to set that route's limits. A method attribute wins over a class attribute. Any value the attribute leaves unset falls back to config:

```php
use Marko\RateLimiter\Attributes\RateLimit;
use Marko\RateLimiter\Middleware\RateLimitMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Response;

#[Middleware(RateLimitMiddleware::class)]
#[RateLimit(maxAttempts: 600, decaySeconds: 60)]
class AccountController
{
    #[Get('/api/account')]
    public function show(): Response
    {
        return new Response('OK');
    }

    #[Post('/login')]
    #[RateLimit(maxAttempts: 5, decaySeconds: 60, name: 'login')]
    public function login(): Response
    {
        return new Response('OK');
    }
}
```

Give routes the same `name` to make them share one counter (for example, login and password reset). Without a name, each controller action gets its own counter. Setting `maxAttempts` or `decaySeconds` below 1, or `name` to an empty string, throws a `RateLimitException`.

### Client Identity

The middleware asks `RateLimitKeyResolverInterface` who the request belongs to. The default binding, `ClientIpKeyResolver`, returns the real client IP through `ClientIpResolver`. `REMOTE_ADDR` is used directly unless it is a configured trusted proxy. In that case the right-most untrusted hop from `X-Forwarded-For` is used instead. IPv4 and IPv6 clients are both supported.

IPv6 clients are limited per network, not per address. One subscriber is usually handed a whole `/64`, so keying by the full address would let a single client rotate through 2^64 fresh buckets. `ClientIpKeyResolver` masks IPv6 addresses to `ratelimiter.ipv6_prefix` bits (`2001:db8:aa:bb::1` and `2001:db8:aa:bb:dead:beef::7` both become `2001:db8:aa:bb::/64`). IPv4 clients, including IPv4-mapped IPv6 addresses such as `::ffff:203.0.113.9`, are keyed by their full IPv4 address.

Behind a cloud load balancer whose addresses change, trust its subnet with a CIDR range:

```php title="config/ratelimiter.php"
'trusted_proxies' => ['10.0.0.0/8', 'fd00::/8'],
```

To limit by user instead of IP, bind your own resolver in your module's `module.php`:

```php title="app/myapp/module.php"
use App\MyApp\RateLimit\UserKeyResolver;
use Marko\RateLimiter\Contracts\RateLimitKeyResolverInterface;

return [
    'bindings' => [
        RateLimitKeyResolverInterface::class => UserKeyResolver::class,
    ],
];
```

```php title="app/myapp/src/RateLimit/UserKeyResolver.php"
use Marko\Authentication\AuthManager;
use Marko\RateLimiter\ClientIpKeyResolver;
use Marko\RateLimiter\Contracts\RateLimitKeyResolverInterface;
use Marko\Routing\Http\Request;

readonly class UserKeyResolver implements RateLimitKeyResolverInterface
{
    public function __construct(
        private AuthManager $authManager,
        private ClientIpKeyResolver $clientIpKeyResolver,
    ) {}

    public function resolve(
        Request $request,
    ): string {
        $id = $this->authManager->id();

        return $id !== null ? "user:$id" : 'ip:' . $this->clientIpKeyResolver->resolve($request);
    }
}
```

### The 429 Response

When a client exceeds its limit, the next handler is not called and the middleware returns:

```http
HTTP/1.1 429 Too Many Requests
Content-Type: application/json
Retry-After: 42
X-RateLimit-Limit: 5
X-RateLimit-Remaining: 0

{"message":"Too Many Requests"}
```

### Using the Rate Limiter Directly

For custom throttling logic, inject `RateLimiterInterface`. Keys can be any string, such as an email address, an IPv6 address or a route name. The limiter hashes them into cache-safe keys:

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

### Testing with a Frozen Clock

`RateLimiter` reads the current time through the PSR-20 `ClockInterface` from [`marko/clock`](/docs/packages/clock/) to compute `retryAfter()`: the counter's expiry, as reported by the cache, minus the current time. In tests, give the limiter and an [`ArrayCacheDriver`](/docs/packages/cache-array/) the same [`FakeClock`](/docs/packages/testing/#fakeclock), then move it to check Retry-After and the window reset without `sleep()`:

```php
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Core\Path\ProjectPaths;
use Marko\RateLimiter\RateLimiter;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

it('counts retry after down and then resets the window', function (): void {
    $clock = new FakeClock('2026-01-01 12:00:00 UTC');
    $cache = new ArrayCacheDriver(
        new CacheConfig(new FakeConfigRepository(['cache.default_ttl' => 3600]), new ProjectPaths()),
        $clock,
    );
    $rateLimiter = new RateLimiter($cache, $clock);

    $rateLimiter->attempt('login:ada@example.com', 1, 60);
    $clock->travel('+20 seconds');
    expect($rateLimiter->attempt('login:ada@example.com', 1, 60)->retryAfter())->toBe(40);

    $clock->travel('+41 seconds');
    expect($rateLimiter->attempt('login:ada@example.com', 1, 60)->allowed())->toBeTrue();
});
```

Use the same clock for both: the limiter subtracts its own clock's time from the expiry the cache reports. With [`marko/cache-redis`](/docs/packages/cache-redis/), Redis expires counters on the server, so a fake clock can't reset the window.

## Customization

Replace `RateLimiter` via [Preferences](/docs/packages/core/) to change the counting strategy:

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

To change who a request is limited as, bind `RateLimitKeyResolverInterface` (see [Client Identity](#client-identity)).

## API Reference

### RateLimiterInterface

```php
public function attempt(string $key, int $maxAttempts, int $decaySeconds): RateLimitResult;
public function tooManyAttempts(string $key, int $maxAttempts): bool;
public function clear(string $key): void;
```

`attempt()` fails closed: when the cache can't count the attempt (for example, an unwritable or full file cache directory), the driver's `CacheException` propagates instead of the request being allowed.

### RateLimitResult

```php
public function allowed(): bool;
public function remaining(): int;
public function retryAfter(): ?int;
```

### RateLimit (attribute)

Targets classes and methods.

```php
use Marko\RateLimiter\Attributes\RateLimit;

public function __construct(?int $maxAttempts = null, ?int $decaySeconds = null, ?string $name = null);
```

| Parameter | Description |
|-----------|-------------|
| `$maxAttempts` | Requests allowed per window. `null` uses `ratelimiter.default_max_attempts`. |
| `$decaySeconds` | Window length in seconds. `null` uses `ratelimiter.default_decay_seconds`. |
| `$name` | Bucket name. Routes with the same name share a counter. `null` uses `{controller}::{action}`. |

### RateLimitKeyResolverInterface

```php
use Marko\RateLimiter\Contracts\RateLimitKeyResolverInterface;

public function resolve(Request $request): string;
```

Bound to `ClientIpKeyResolver` by default, which returns the client IP from `ClientIpResolver`, with IPv6 addresses masked to `ratelimiter.ipv6_prefix` bits (for example `2001:db8:aa:bb::/64`). Throws `ClientIpException` if `ipv6_prefix` is outside 1--128.

### ClientIpResolver

Resolves the real client IP from a request, respecting the `ratelimiter.trusted_proxies` config (exact addresses and CIDR ranges). It returns the full address; use `ClientIpKeyResolver` for a rate-limit key that buckets IPv6 networks. When `REMOTE_ADDR` is not in the trusted list, `X-Forwarded-For` is ignored entirely, which prevents header forgery. When it is trusted, the right-most untrusted hop in the `X-Forwarded-For` chain is returned.

```php
use Marko\RateLimiter\ClientIpResolver;

public function resolve(Request $request): string;
```

Throws `ClientIpException` if `REMOTE_ADDR` is missing or a `trusted_proxies` entry is not a valid address or CIDR range, and `ConfigNotFoundException` if the config key is absent.

### RateLimiterConfig

```php
use Marko\RateLimiter\Config\RateLimiterConfig;

public function defaultMaxAttempts(): int;
public function defaultDecaySeconds(): int;
public function ipv6Prefix(): int;
```

### RateLimitMiddleware

```php
public function handle(Request $request, callable $next): Response;
```

Constructor parameters (autowired; there are no scalar settings, so limits come from `#[RateLimit]` and config):

| Parameter | Type | Description |
|-----------|------|-------------|
| `$rateLimiter` | `RateLimiterInterface` | Counts attempts |
| `$rateLimitKeyResolver` | `RateLimitKeyResolverInterface` | Resolves the client identity |
| `$rateLimiterConfig` | `RateLimiterConfig` | Supplies default limits |

## Related Packages

- [`marko/cache`](/docs/packages/cache/) --- the storage contract the limiter counts with
- [`marko/cache-redis`](/docs/packages/cache-redis/) --- shared counters across servers
