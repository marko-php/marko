# marko/database-readwrite

Routes reads to replicas, writes to primary --- drop-in decorator for any Marko database driver.

## Installation

```bash
composer require marko/database-readwrite
```

## Quick Example

```php title="config/database.php"
<?php

declare(strict_types=1);

return [
    'driver' => 'readwrite',
    'connections' => [
        'write' => [
            'driver'   => 'pgsql',
            'host'     => $_ENV['DB_WRITE_HOST'] ?? 'localhost',
            'port'     => (int) ($_ENV['DB_WRITE_PORT'] ?? 5432),
            'database' => $_ENV['DB_DATABASE'] ?? 'marko',
            'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
        ],
        'read' => [
            [
                'driver'   => 'pgsql',
                'host'     => $_ENV['DB_READ_HOST'] ?? 'replica-1',
                'port'     => (int) ($_ENV['DB_READ_PORT'] ?? 5432),
                'database' => $_ENV['DB_DATABASE'] ?? 'marko',
                'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
            ],
        ],
        'read_strategy' => 'random',
    ],
];
```

## Documentation

Full usage, configuration, API reference, and examples: [marko/database-readwrite](https://marko.build/docs/packages/database-readwrite/)
