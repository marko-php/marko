# marko/redis

Shared Redis connections --- one configured predis connection per name, used by `marko/cache-redis` and other Redis-backed packages.

## Installation

```bash
composer require marko/redis
```

## Quick Example

```php
use Marko\Redis\RedisConnectionFactoryInterface;

class VisitCounter
{
    public function __construct(
        private RedisConnectionFactoryInterface $redisConnectionFactory,
    ) {}

    public function hit(string $page): int
    {
        return $this->redisConnectionFactory->connection('default')->client()->incr("visits:$page");
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/redis](https://marko.build/docs/packages/redis/)
