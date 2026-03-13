# marko/config

Type-safe configuration management with dot notation, automatic merging, and multi-tenant scope support.

## Installation

```bash
composer require marko/config
```

## Quick Example

```php
use Marko\Config\ConfigRepositoryInterface;

class DatabaseConnection
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function connect(): PDO
    {
        $host = $this->config->getString('database.host');
        $port = $this->config->getInt('database.port');

        return new PDO("mysql:host={$host};port={$port}");
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/config](https://marko.build/docs/packages/config/)
