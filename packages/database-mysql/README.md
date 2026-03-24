# marko/database-mysql

MySQL and MariaDB driver for the Marko framework database layer.

## Installation

```bash
composer require marko/database-mysql
```

This automatically installs `marko/database` (the interface package) as a dependency.

## Configuration

Publish or create `config/database.php` and set your connection details:

```php
return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => env('DB_HOST', '127.0.0.1'),
            'database' => env('DB_DATABASE', 'marko'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
        ],
    ],
];
```

Set the corresponding values in your `.env` file:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=marko
DB_USERNAME=root
DB_PASSWORD=secret
```

## Driver Notes

This driver supports **MySQL** 8.0+ and **MariaDB** 10.6+. Both are fully supported via the same driver key (`mysql`).

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
