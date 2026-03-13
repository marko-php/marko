# marko/cache

Interfaces for caching --- defines how data is stored, retrieved, and expired, not how the backend works.

## Installation

```bash
composer require marko/cache
```

Note: You typically install a driver package (like `marko/cache-file`) which requires this automatically.

## Quick Example

```php
use Marko\Cache\Contracts\CacheInterface;

class ProductService
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function getProduct(int $id): Product
    {
        $key = "product.$id";

        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }

        $product = $this->repository->find($id);
        $this->cache->set($key, $product, ttl: 3600);

        return $product;
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/cache](https://marko.build/docs/packages/cache/)
