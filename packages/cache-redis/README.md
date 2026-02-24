# Marko Cache Redis

Redis cache driver--fast, persistent caching backed by Redis for production workloads.

## Overview

The Redis cache driver stores serialized data in Redis with automatic TTL expiration. Supports key prefixing to isolate cache namespaces, configurable host/port/database, and optional authentication. Uses Predis as the Redis client library.

Implements `CacheInterface` from `marko/cache`.

## Installation

```bash
composer require marko/cache-redis
```

This automatically installs `marko/cache` and `predis/predis`.

## Usage

### Configuration

Set the cache driver to `redis` and configure connection details:

```php
// config/cache.php
return [
    'driver' => 'redis',
    'default_ttl' => 3600,
    'path' => 'storage/cache',
];
```

Redis connection is configured via `RedisConnection`:

```php
// In your module.php bindings
use Marko\Cache\Redis\RedisConnection;

'bindings' => [
    RedisConnection::class => RedisConnection::class,
],
'boot' => function ($container) {
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

### How It Works

Once configured, inject `CacheInterface` as usual--the Redis driver is used automatically:

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

Implements all methods from `CacheInterface`. See `marko/cache` for the full contract.

### RedisConnection

```php
public function __construct(string $host = '127.0.0.1', int $port = 6379, ?string $password = null, int $database = 0, string $prefix = 'marko:cache:');
public function client(): ClientInterface;
public function disconnect(): void;
public function isConnected(): bool;
```
