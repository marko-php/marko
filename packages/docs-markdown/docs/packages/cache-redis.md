---
title: marko/cache-redis
description: Redis cache driver — fast, persistent caching backed by Redis for production workloads.
---

Redis cache driver --- fast, persistent caching backed by Redis for production workloads. Stores HMAC-signed, serialized data in Redis with automatic TTL expiration. Values are tamper-evident: reads verify the signature before deserializing, so modified entries are rejected loudly. Supports key prefixing to isolate cache namespaces, configurable host/port/database, and optional authentication. Uses Predis as the Redis client library.

Implements `CacheInterface` from [`marko/cache`](/docs/packages/cache/).

## Installation

```bash
composer require marko/cache-redis
```

This automatically installs `marko/cache`, `predis/predis`, and [`marko/encryption`](/docs/packages/encryption/). A non-empty `encryption.key` is required; reads and writes throw `Marko\Cache\Exceptions\TamperedCacheValueException` if the key is empty or a stored value's HMAC does not verify. Values are signed by the shared [`CacheValueSigner`](/docs/packages/cache/#cachevaluesigner) from `marko/cache`.

```php title="config/encryption.php"
use Marko\Config\Env;

return [
    'key' => Env::string('APP_KEY', ''),
];
```

## Configuration

Set the cache driver to `redis` in your config:

```php title="config/cache.php"
return [
    'driver' => 'redis',
    'default_ttl' => 3600,
    'path' => 'storage/cache',
];
```

The package ships `config/cache-redis.php`, and its module binding builds a single shared `RedisConnection` from it. No hand-written binding is needed. Set the environment variables, or override the file in your app:

```php title="config/cache-redis.php"
use Marko\Config\Env;

return [
    'host' => Env::string('REDIS_HOST', '127.0.0.1'),
    'port' => Env::int('REDIS_PORT', 6379, min: 1, max: 65535),
    'password' => Env::nullableString('REDIS_PASSWORD'),
    'database' => Env::int('REDIS_CACHE_DATABASE', 0, min: 0),
    'prefix' => Env::string('CACHE_PREFIX', 'marko:cache:'),
    'scheme' => Env::string('REDIS_SCHEME', 'tcp'),
];
```

| Key | Env var | Default | Description |
|---|---|---|---|
| `host` | `REDIS_HOST` | `127.0.0.1` | Redis server host |
| `port` | `REDIS_PORT` | `6379` | Redis server port (1-65535) |
| `password` | `REDIS_PASSWORD` | `null` | Password for `AUTH`; `null` or empty means no authentication |
| `database` | `REDIS_CACHE_DATABASE` | `0` | Redis database index (0 or higher) |
| `prefix` | `CACHE_PREFIX` | `marko:cache:` | Prefix added to every cache key |
| `scheme` | `REDIS_SCHEME` | `tcp` | `tcp` for plain text, or `tls` to encrypt the connection and verify the server certificate against `host`. Any other value throws `RedisConnectionException` |

The connection opens on first use. If Redis refuses it, a `RedisConnectionException` names the host and port and points back to this config file.

Use `tls` for any Redis reached over a network you do not control (managed Redis services usually require it). With `tcp`, the `AUTH` password and every cached value cross the network in plain text. With `tls`, the server certificate must be valid for `host` and signed by a CA in the system trust store.

## Usage

Once configured, inject `CacheInterface` as usual --- the Redis driver is used automatically:

```php
use Marko\Cache\Contracts\CacheInterface;

class SessionStore
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function getSession(
        string $token,
    ): ?array {
        return $this->cache->get("session.$token");
    }

    public function saveSession(
        string $token,
        array $data,
    ): void {
        $this->cache->set("session.$token", $data, ttl: 1800);
    }
}
```

### When to Use

- **Production workloads** with high read/write throughput
- **Multi-server deployments** where cache must be shared
- **Session storage** and other latency-sensitive data
- **TTL-managed expiration** handled natively by Redis

### Key Prefixing

All keys are automatically prefixed (default: `marko:cache:`) to prevent collisions with other Redis data. Change it with the `prefix` key in `config/cache-redis.php` (or `CACHE_PREFIX`).

## API Reference

Implements all methods from `CacheInterface`. See [`marko/cache`](/docs/packages/cache/) for the full contract.

### Key Methods

| Method | Description |
|---|---|
| `get(string $key, mixed $default = null): mixed` | Retrieve a value, returning `$default` on miss or expiration |
| `set(string $key, mixed $value, ?int $ttl = null): bool` | Store a value with optional TTL (falls back to `default_ttl`) |
| `has(string $key): bool` | Check if a non-expired entry exists |
| `delete(string $key): bool` | Remove a single entry |
| `clear(): bool` | Remove all prefixed entries from Redis |
| `getItem(string $key): CacheItemInterface` | Get a `CacheItem` with hit/miss status and expiration metadata |
| `getMultiple(array $keys, mixed $default = null): iterable` | Retrieve multiple values at once |
| `setMultiple(array $values, ?int $ttl = null): bool` | Store multiple key-value pairs at once |
| `deleteMultiple(array $keys): bool` | Remove multiple entries at once |
| `increment(string $key, int $ttl): int` | Atomically increment an integer counter and apply its TTL in one step; an existing TTL is never reset |
| `add(string $key, mixed $value, ?int $ttl = null): bool` | Atomically store a value only if the key is absent (`SET ... NX`); `false` when it exists |
| `remember(string $key, ?int $ttl, callable $callback): mixed` | Return the cached value, or compute and store it on a miss (delegates to [`CacheOperations`](/docs/packages/cache/#cacheoperations)) |
| `pull(string $key, mixed $default = null): mixed` | Get a value and delete it (delegates to `CacheOperations`) |
| `forever(string $key, mixed $value): bool` | Store a value with no expiry (delegates to `CacheOperations`) |

### RedisConnection

| Method | Description |
|---|---|
| `__construct(string $host, int $port, ?string $password, int $database, string $prefix, string $scheme)` | Create a connection with host (`127.0.0.1`), port (`6379`), optional password, database index (`0`), key prefix (`marko:cache:`) and scheme (`tcp` or `tls`, default `tcp`). Throws `RedisConnectionException` for an unknown scheme |
| `connectionParameters(): array` | The Predis connection parameters; with `tls` they include `ssl` context options that verify the peer certificate and host name |
| `client(): ClientInterface` | Get the Predis client instance --- connected on first call; throws `RedisConnectionException` if the connection is refused |
| `disconnect(): void` | Disconnect and release the client instance |
| `isConnected(): bool` | Check whether a client instance is currently active |

### Storage Details

- Values are serialized with PHP's `serialize()`, wrapped in an HMAC-SHA256 envelope, and stored as Redis strings. Reads verify the HMAC before deserializing; tampered or corrupted entries throw `TamperedCacheValueException`.
- The HMAC covers the prefixed Redis key as well as the value, so someone with write access to Redis can't move a validly signed value from one key to another: the moved value throws `TamperedCacheValueException`. Values signed by a release before key binding and [HKDF subkeys](/docs/packages/cache/#cachevaluesigner) throw too, so run `marko cache:clear` when you deploy that upgrade.
- A `null` TTL falls back to `default_ttl` from config. A TTL greater than `0` uses Redis `SETEX` for native expiration. A TTL of `0` or less means the entry never expires.
- `add()` sends one `SET key envelope NX EX ttl` (no `EX` for a TTL of `0`), writing the same signed envelope as `set()` under the prefixed key. Redis does the check and the write atomically, so of any number of clients exactly one gets `true`.
- `increment()` runs `INCR` and `EXPIRE` together in one Lua script (`EVAL`), so a crash can never leave a counter without an expiry. The TTL is set when the key is created (count reaches `1`), and is restored if the key somehow has none. Subsequent increments do not reset it, so the window stays fixed.
- Counters are plain integers, not signed envelopes, because Redis writes them itself. `get()`, `getItem()` and `getMultiple()` return them as `int`, which is what [`marko/ratelimiter`](/docs/packages/ratelimiter/) relies on. Only values that are entirely an optional `-` followed by digits are read this way. They are never passed to `unserialize()`, and every other value must still carry a valid HMAC.
- `clear()` removes only keys matching the configured prefix --- other Redis data is not affected. It walks the keyspace with `SCAN` in batches rather than `KEYS`, so clearing a large database does not block Redis. Glob characters in the prefix are escaped, so the prefix always matches literally.
- `getItem()` reports `expiresAt()` as the current time from the PSR-20 `ClockInterface` ([`marko/clock`](/docs/packages/clock/)) plus the key's remaining Redis TTL. Redis enforces the expiry itself, so moving a [`FakeClock`](/docs/packages/testing/#fakeclock) changes the reported time but never expires a key. Use [`marko/cache-array`](/docs/packages/cache-array/) to test expiry with a fake clock.
