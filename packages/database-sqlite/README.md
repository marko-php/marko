# marko/database-sqlite

SQLite driver for the Marko framework database layer --- a zero-server database in a single file, or in memory.

## Installation

```bash
composer require marko/database-sqlite
```

This automatically installs `marko/database` (the interface package) as a dependency. It needs `ext-pdo_sqlite` and SQLite 3.39 or newer.

## Configuration

Create `config/database.php`. Only `driver` and `database` are required:

```php title="config/database.php"
<?php

declare(strict_types=1);

return [
    'driver' => 'sqlite',
    'database' => 'storage/database.sqlite',
];
```

A relative `database` path resolves against the project root. Use `:memory:` for a throwaway database.

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

Full usage, configuration, and API reference: [marko/database-sqlite](https://marko.build/docs/packages/database-sqlite/)
