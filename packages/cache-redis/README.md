# marko/cache-redis

Redis cache driver --- fast, persistent caching backed by Redis for production workloads.

## Installation

```bash
composer require marko/cache-redis
```

## Quick Example

```php
use Marko\Cache\Contracts\CacheInterface;

class SessionStore
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function getSession(string $token): ?array
    {
        return $this->cache->get("session.$token");
    }

    public function saveSession(string $token, array $data): void
    {
        $this->cache->set("session.$token", $data, ttl: 1800);
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/cache-redis](https://marko.build/docs/packages/cache-redis/)
