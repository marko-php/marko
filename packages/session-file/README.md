# marko/session-file

File-based session driver--stores session data as files on disk with file locking for concurrent request safety.

## Installation

```bash
composer require marko/session-file
```

## Quick Example

```php
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\File\Handler\FileSessionHandler;

return [
    'bindings' => [
        SessionHandlerInterface::class => FileSessionHandler::class,
    ],
];
```

## Documentation

Full usage, API reference, and examples: [marko/session-file](https://marko.build/docs/packages/session-file/)
