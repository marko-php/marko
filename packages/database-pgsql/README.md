# marko/database-pgsql

PostgreSQL driver for the Marko framework database layer.

## Installation

```bash
composer require marko/database-pgsql
```

This automatically installs `marko/database` (the interface package) as a dependency.

## Configuration

Publish or create `config/database.php` and set your connection details:

```php
return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        'pgsql' => [
            'driver'   => 'pgsql',
            'host'     => env('DB_HOST', '127.0.0.1'),
            'database' => env('DB_DATABASE', 'marko'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
        ],
    ],
];
```

Set the corresponding values in your `.env` file:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=marko
DB_USERNAME=postgres
DB_PASSWORD=secret
```

## Driver Notes

This driver supports **PostgreSQL** 14+.

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
