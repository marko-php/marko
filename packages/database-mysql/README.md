# marko/database-mysql

MySQL and MariaDB driver for the Marko framework database layer.

## Installation

```bash
composer require marko/database-mysql
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

Full configuration, driver notes, and API reference: [marko/database-mysql](https://marko.build/docs/packages/database-mysql/)
