# marko/cache-array

In-memory cache driver — stores data for the duration of a single request with zero I/O overhead.

## Installation

```bash
composer require marko/cache-array
```

## Quick Example

```php
use Marko\Cache\Contracts\CacheInterface;

class ExpensiveService
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function compute(string $key): array
    {
        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }

        $result = $this->doExpensiveWork($key);
        $this->cache->set($key, $result);

        return $result;
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/cache-array](https://marko.build/docs/packages/cache-array/)
