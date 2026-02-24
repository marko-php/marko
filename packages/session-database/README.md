# Marko Session Database

Database session driver--stores session data in a SQL table for shared access across multiple application servers.

## Overview

Sessions are stored in a `sessions` table with columns for the session ID, serialized payload, and last activity timestamp. This enables session sharing across multiple web servers behind a load balancer. Garbage collection deletes rows where `last_activity` exceeds the configured session lifetime.

## Installation

```bash
composer require marko/session-database
```

Requires `marko/database` for the database connection.

## Usage

### Binding the Driver

Register the database handler in your module bindings:

```php
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Database\Handler\DatabaseSessionHandler;

return [
    'bindings' => [
        SessionHandlerInterface::class => DatabaseSessionHandler::class,
    ],
];
```

Then use `SessionInterface` as usual:

```php
use Marko\Session\Contracts\SessionInterface;

public function __construct(
    private readonly SessionInterface $session,
) {}

public function handle(): void
{
    $this->session->start();
    $this->session->set('cart_id', $cartId);
    $this->session->save();
}
```

### Creating the Sessions Table

Create the required table via migration or manually:

```sql
CREATE TABLE sessions (
    id VARCHAR(128) PRIMARY KEY,
    payload TEXT NOT NULL,
    last_activity INT NOT NULL
);
```

### Garbage Collection

Remove expired sessions via CLI:

```bash
php marko session:gc
```

This deletes rows where `last_activity` is older than the configured session lifetime.

## API Reference

### DatabaseSessionHandler

```php
public function open(string $path, string $name): bool;
public function close(): bool;
public function read(string $id): string|false;
public function write(string $id, string $data): bool;
public function destroy(string $id): bool;
public function gc(int $max_lifetime): int|false;
```
