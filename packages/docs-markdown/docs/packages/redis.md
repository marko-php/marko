---
title: marko/redis
description: Shared Redis connections --- one configured predis connection per name, used by marko/cache-redis and other Redis-backed packages.
---

Shared Redis connections --- one configured predis connection per name, used by [`marko/cache-redis`](/docs/packages/cache-redis/) and other Redis-backed packages. The package owns the connection and nothing else: it has no cache or queue knowledge. Each consumer names a connection in its own config and resolves it through `RedisConnectionFactoryInterface`, so the scheme validation, TLS peer verification and eager connect with a loud error live in one place.

`marko/pubsub-redis` is separate: it uses the async `amphp/redis` client, not predis, and has its own connection settings.

## Installation

```bash
composer require marko/redis
```

Packages that need Redis, such as `marko/cache-redis`, require it for you. It installs `predis/predis`.

## Configuration

The package ships `config/redis.php` with one connection, `default`, read from the `REDIS_*` environment variables:

```php title="config/redis.php"
use Marko\Config\Env;

return [
    'connections' => [
        'default' => [
            'host' => Env::string('REDIS_HOST', '127.0.0.1'),
            'port' => Env::int('REDIS_PORT', 6379, min: 1, max: 65535),
            'password' => Env::nullableString('REDIS_PASSWORD'),
            'database' => Env::int('REDIS_DATABASE', 0, min: 0),
            'scheme' => Env::string('REDIS_SCHEME', 'tcp'),
        ],
    ],
];
```

| Key | Env var (`default`) | Default | Description |
|---|---|---|---|
| `host` | `REDIS_HOST` | `127.0.0.1` | Redis server host |
| `port` | `REDIS_PORT` | `6379` | Redis server port (1-65535) |
| `password` | `REDIS_PASSWORD` | `null` | Password for `AUTH`; `null`, empty or removed means no authentication |
| `database` | `REDIS_DATABASE` | `0` | Redis database index (0 or higher) |
| `scheme` | `REDIS_SCHEME` | `tcp` | `tcp` for plain text, or `tls` to encrypt the connection and verify the server certificate against `host`. Any other value throws `RedisConnectionException` |

Every connection needs all five keys. The connection class has no built-in defaults, so a missing key throws from the config repository instead of silently connecting somewhere else.

### Named Connections

Add entries under `connections` to put a consumer on its own database or server. Your app's `config/redis.php` is merged over the package file, so `default` stays as it is:

```php title="config/redis.php"
use Marko\Config\Env;

return [
    'connections' => [
        'cache' => [
            'host' => Env::string('REDIS_HOST', '127.0.0.1'),
            'port' => Env::int('REDIS_PORT', 6379, min: 1, max: 65535),
            'password' => Env::nullableString('REDIS_PASSWORD'),
            'database' => Env::int('REDIS_CACHE_DATABASE', 1, min: 0),
            'scheme' => Env::string('REDIS_SCHEME', 'tcp'),
        ],
    ],
];
```

Then point the consumer at it, for example `'connection' => 'cache'` in `config/cache-redis.php`.

### TLS

Use `tls` for any Redis reached over a network you do not control (managed Redis services usually require it). With `tcp`, the `AUTH` password and every value cross the network in plain text. With `tls`, the server certificate must be valid for `host` and signed by a CA in the system trust store.

## Usage

Inject `RedisConnectionFactoryInterface` and ask it for a connection by name. Each name resolves to one shared `RedisConnection` for the life of the process:

```php
use Marko\Redis\RedisConnectionFactoryInterface;

class VisitCounter
{
    public function __construct(
        private RedisConnectionFactoryInterface $redisConnectionFactory,
    ) {}

    public function hit(
        string $page,
    ): int {
        return $this->redisConnectionFactory->connection('default')->client()->incr("visits:$page");
    }
}
```

The connection opens lazily: `connection()` only builds the object, and the first `client()` call connects. A refused connection throws `RedisConnectionException` there, naming the host and port, instead of failing on the first command.

### Building a Redis-backed Package

A package that needs Redis should take a connection name from its own config (defaulting to `'default'`) and resolve it through the factory. It should not build a predis client itself, and it should apply any key prefix to its own keys: the connection has no prefix.

## Errors

| Exception | When |
|---|---|
| `RedisConnectionException` | The scheme is not `tcp` or `tls`; the server refuses the connection; or `connection()` is asked for a name that is not under `redis.connections` (the message lists the configured names) |

## API Reference

### RedisConnectionFactoryInterface

| Method | Description |
|---|---|
| `connection(string $name): RedisConnectionInterface` | The connection configured under `redis.connections.{name}`, one shared instance per name. Throws `RedisConnectionException` for an unknown name |

Bound to `RedisConnectionFactory` as a singleton.

### RedisConnectionInterface

| Method | Description |
|---|---|
| `client(): Predis\ClientInterface` | The Predis client, created and connected on first call; throws `RedisConnectionException` if the connection is refused |
| `disconnect(): void` | Release the client; the next `client()` call connects again |
| `isConnected(): bool` | Whether a client instance is currently held |

### RedisConnection

Implements `RedisConnectionInterface`.

| Member | Description |
|---|---|
| `__construct(string $host, int $port, ?string $password, int $database, string $scheme)` | Every value is required. Throws `RedisConnectionException` for a scheme other than `tcp` or `tls` |
| `$host`, `$port`, `$password`, `$database`, `$scheme` | The settings, as public readonly properties |
| `connectionParameters(): array` | The Predis connection parameters; with `tls` they include `ssl` context options that verify the peer certificate and host name. `password` is left out when it is `null` |

## Related Packages

- [`marko/cache-redis`](/docs/packages/cache-redis/) --- Redis cache driver built on these connections
- [`marko/pubsub-redis`](/docs/packages/pubsub-redis/) --- Redis pub/sub on the async amphp client (separate connection settings)
