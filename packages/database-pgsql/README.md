# marko/database-pgsql

PostgreSQL driver for the Marko framework database layer.

## Installation

```bash
composer require marko/database-pgsql
```

This automatically installs `marko/database` (the interface package) as a dependency.

## Quick Example

```php
use Marko\Database\Connection\ConnectionInterface;

class MyService
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function doSomething(): void
    {
        $result = $this->connection->query('SELECT * FROM users');
    }
}
```

## Documentation

Full usage, configuration, and API reference: [marko/database-pgsql](https://marko.build/docs/packages/database-pgsql/)
