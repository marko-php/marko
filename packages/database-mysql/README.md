# Marko Database MySQL

MySQL and MariaDB driver for the Marko framework database layer.

## Installation

```bash
composer require marko/database-mysql
```

This automatically installs `marko/database` (the interface package) as a dependency.

## Configuration

Create a configuration file at `config/database.php`:

```php
<?php

declare(strict_types=1);

return [
    'driver' => 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
    'database' => $_ENV['DB_DATABASE'] ?? 'marko',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
```

### Environment Variables

Set these in your `.env` file:

```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

## Driver-Specific Notes

### MySQL vs MariaDB

This driver supports both MySQL 8.0+ and MariaDB 10.5+. Both are tested and fully supported.

### Character Set

The default charset is `utf8mb4` which supports the full Unicode range including emojis. This is the recommended setting for new applications.

### Strict Mode

Marko enables MySQL strict mode by default. This ensures data integrity by rejecting invalid data rather than silently truncating or coercing values.

### JSON Columns

MySQL's native JSON type is fully supported. Use the `type: 'json'` parameter in your `#[Column]` attribute:

```php
#[Column(type: 'json')]
public array $metadata = [];
```

## Usage

Once configured, the MySQL driver is automatically used when you interact with the database. See the main `marko/database` documentation for entity definition and repository usage.

```php
use Marko\Database\Connection\ConnectionInterface;

class MyService
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function doSomething(): void
    {
        // Connection is automatically MySQL
        $result = $this->connection->query('SELECT * FROM users');
    }
}
```
