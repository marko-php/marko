---
title: marko/cache-redis
description: Redis cache driver — fast, persistent caching backed by Redis for production workloads.
---

Redis cache driver --- fast, persistent caching backed by Redis for production workloads. Stores serialized data in Redis with automatic TTL expiration. Supports key prefixing to isolate cache namespaces, configurable host/port/database, and optional authentication. Uses Predis as the Redis client library.

Implements `CacheInterface` from [`marko/cache`](/docs/packages/cache/).

## Installation

```bash
composer require marko/cache-redis
```

This automatically installs `marko/cache` and `predis/predis`.

## Configuration

Set the cache driver to `redis` in your config:

```php title="config/cache.php"
return [
    'driver' => 'redis',
    'default_ttl' => 3600,
    'path' => 'storage/cache',
];
```

Redis connection is configured via `RedisConnection`:

```php title="module.php"
use Marko\Cache\Redis\RedisConnection;
use Psr\Container\ContainerInterface;

'bindings' => [
    RedisConnection::class => RedisConnection::class,
],
'boot' => function (ContainerInterface $container): void {
    $container->bind(
        RedisConnection::class,
        fn () => new RedisConnection(
            host: $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            port: (int) ($_ENV['REDIS_PORT'] ?? 6379),
            password: $_ENV['REDIS_PASSWORD'] ?? null,
            database: (int) ($_ENV['REDIS_DATABASE'] ?? 0),
            prefix: 'marko:cache:',
        ),
    );
},
```

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

All keys are automatically prefixed (default: `marko:cache:`) to prevent collisions with other Redis data. The prefix is configurable via the `RedisConnection` constructor.

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

### RedisConnection

| Method | Description |
|---|---|
| `__construct(string $host, int $port, ?string $password, int $database, string $prefix)` | Create a connection with host (`127.0.0.1`), port (`6379`), optional password, database index (`0`), and key prefix (`marko:cache:`) |
| `client(): ClientInterface` | Get the Predis client instance --- lazily connected on first call |
| `disconnect(): void` | Disconnect and release the client instance |
| `isConnected(): bool` | Check whether a client instance is currently active |

### Storage Details

- Values are serialized with PHP's `serialize()` and stored as Redis strings.
- A TTL greater than `0` uses Redis `SETEX` for native expiration. A TTL of `0` or `null` means the entry never expires.
- `clear()` removes only keys matching the configured prefix --- other Redis data is not affected.
