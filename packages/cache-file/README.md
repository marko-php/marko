# marko/cache-file

File-based cache driver — persists cached data to disk with automatic expiration and atomic writes.

## Installation

```bash
composer require marko/cache-file
```

## Quick Example

```php
use Marko\Cache\Contracts\CacheInterface;

class SettingsService
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function getAll(): array
    {
        if ($this->cache->has('settings.all')) {
            return $this->cache->get('settings.all');
        }

        $settings = $this->loadFromDatabase();
        $this->cache->set('settings.all', $settings, ttl: 7200);

        return $settings;
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/cache-file](https://marko.build/docs/packages/cache-file/)
