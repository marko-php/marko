---
title: marko/session-database
description: Database session driver — stores session data in a SQL table for shared access across multiple application servers.
---

Database session driver --- stores session data in a SQL table for shared access across multiple application servers. Sessions are stored in a `sessions` table with columns for the session ID, serialized payload, and last activity timestamp. This enables session sharing across multiple web servers behind a load balancer. Garbage collection deletes rows where `last_activity` exceeds the configured session lifetime.

Implements `SessionHandlerInterface` from [`marko/session`](/docs/packages/session/) and requires [`marko/database`](/docs/packages/database/) for the database connection.

## Installation

```bash
composer require marko/session-database
```

Requires [`marko/database`](/docs/packages/database/) for the database connection.

## Usage

### Binding the Driver

Register the database handler in your module bindings:

```php title="module.php"
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
marko session:gc
```

This deletes rows where `last_activity` is older than the configured session lifetime.

## API Reference

### DatabaseSessionHandler

Implements `SessionHandlerInterface`. Accepts a `ConnectionInterface` connection.

| Method | Description |
|---|---|
| `open(string $path, string $name): bool` | Open the session store. Returns `true`. |
| `close(): bool` | Close the session store. Returns `true`. |
| `read(string $id): string\|false` | Read session data by ID. Returns an empty string if the session does not exist. |
| `write(string $id, string $data): bool` | Write session data --- deletes any existing row for the ID, then inserts a new row with the current timestamp. |
| `destroy(string $id): bool` | Delete a session by ID. |
| `gc(int $max_lifetime): int\|false` | Delete sessions where `last_activity` is older than `max_lifetime` seconds. Returns the number of deleted rows. |
