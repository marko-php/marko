# Marko Database PostgreSQL

PostgreSQL driver for the Marko framework database layer.

## Installation

```bash
composer require marko/database-pgsql
```

This automatically installs `marko/database` (the interface package) as a dependency.

## Configuration

Create a configuration file at `config/database.php`:

```php
<?php

declare(strict_types=1);

return [
    'driver' => 'pgsql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => (int) ($_ENV['DB_PORT'] ?? 5432),
    'database' => $_ENV['DB_DATABASE'] ?? 'marko',
    'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'schema' => 'public',
];
```

### Environment Variables

Set these in your `.env` file:

```env
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

## Driver-Specific Notes

### PostgreSQL Version

This driver supports PostgreSQL 14+. Older versions may work but are not tested.

### Schema

The default schema is `public`. You can specify a different schema in the configuration:

```php
'schema' => 'my_schema',
```

### Native Types

PostgreSQL has excellent support for advanced data types. Marko leverages these native types:

| PHP Type | PostgreSQL Type |
|----------|-----------------|
| `array` | JSONB |
| `DateTimeImmutable` | TIMESTAMPTZ |
| `BackedEnum` | VARCHAR (enum values as strings) |

### JSONB Columns

PostgreSQL's JSONB type is fully supported and recommended over JSON for better indexing and query performance:

```php
#[Column(type: 'jsonb')]
public array $metadata = [];
```

### UUID Primary Keys

PostgreSQL has native UUID support. Use the `type` parameter:

```php
#[Column(primaryKey: true, type: 'uuid', default: 'gen_random_uuid()')]
public string $id;
```

## Usage

Once configured, the PostgreSQL driver is automatically used when you interact with the database. See the main `marko/database` documentation for entity definition and repository usage.

```php
use Marko\Database\Connection\ConnectionInterface;

class MyService
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function doSomething(): void
    {
        // Connection is automatically PostgreSQL
        $result = $this->connection->query('SELECT * FROM users');
    }
}
```
