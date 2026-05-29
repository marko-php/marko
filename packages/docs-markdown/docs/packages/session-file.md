---
title: marko/session-file
description: File-based session driver — stores session data as files on disk with file locking for concurrent request safety.
---

File-based session driver --- stores session data as files on disk with file locking for concurrent request safety. Sessions are stored as individual files (`sess_{id}`) in a configurable directory. Reads use shared locks and writes use exclusive locks to prevent corruption under concurrent requests. Garbage collection removes files older than the configured session lifetime. No external dependencies required.

Implements `SessionHandlerInterface` from [`marko/session`](/docs/packages/session/).

## Installation

```bash
composer require marko/session-file
```

This automatically installs `marko/session`.

## Configuration

Set the session file directory in your config:

```php title="config/session.php"
return [
    'driver' => 'file',
    'path' => 'storage/sessions', // Relative to project root, or absolute path
];
```

The `path` directory is created automatically if it does not exist.

## Usage

### Binding the Driver

Register the file handler in your module bindings:

```php title="module.php"
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\File\Handler\FileSessionHandler;

return [
    'bindings' => [
        SessionHandlerInterface::class => FileSessionHandler::class,
    ],
];
```

Then use `SessionInterface` as usual --- the file driver handles storage:

```php
use Marko\Session\Contracts\SessionInterface;

public function __construct(
    private readonly SessionInterface $session,
) {}

public function handle(): void
{
    $this->session->start();
    $this->session->set('key', 'value');
    $this->session->save();
}
```

### Garbage Collection

Expired session files are cleaned up by the `session:gc` command:

```bash
marko session:gc
```

## API Reference

### FileSessionHandler

Implements all methods from `SessionHandlerInterface`. See [`marko/session`](/docs/packages/session/) for the full contract.

| Method | Description |
|---|---|
| `open(string $path, string $name): bool` | Prepare the storage directory, creating it if needed |
| `close(): bool` | Close the session (no-op for file driver) |
| `read(string $id): string\|false` | Read session data using a shared lock (`LOCK_SH`) |
| `write(string $id, string $data): bool` | Write session data using an exclusive lock (`LOCK_EX`) |
| `destroy(string $id): bool` | Delete a session file from disk |
| `gc(int $max_lifetime): int\|false` | Remove session files older than `$max_lifetime` seconds, returns count of deleted files |

### Storage Details

- Each session is stored as `sess_{id}` in the configured path.
- Reads acquire a shared lock (`LOCK_SH`) so multiple requests can read concurrently.
- Writes acquire an exclusive lock (`LOCK_EX`) with truncate-then-write to prevent corruption.
- Garbage collection compares file modification times against the max lifetime and removes expired files.
