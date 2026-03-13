# marko/session-database

Database session driver — stores session data in a SQL table for shared access across multiple application servers.

## Installation

```bash
composer require marko/session-database
```

Requires `marko/database` for the database connection.

## Quick Example

```php
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Database\Handler\DatabaseSessionHandler;

return [
    'bindings' => [
        SessionHandlerInterface::class => DatabaseSessionHandler::class,
    ],
];
```

## Documentation

Full usage, API reference, and examples: [marko/session-database](https://marko.build/docs/packages/session-database/)
