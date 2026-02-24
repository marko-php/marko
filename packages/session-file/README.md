# Marko Session File

File-based session driver--stores session data as files on disk with file locking for concurrent request safety.

## Overview

Sessions are stored as individual files (`sess_{id}`) in a configurable directory. Reads use shared locks and writes use exclusive locks to prevent corruption under concurrent requests. Garbage collection removes files older than the configured session lifetime. No external dependencies required.

## Installation

```bash
composer require marko/session-file
```

## Usage

### Binding the Driver

Register the file handler in your module bindings:

```php
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\File\Handler\FileSessionHandler;

return [
    'bindings' => [
        SessionHandlerInterface::class => FileSessionHandler::class,
    ],
];
```

Then use `SessionInterface` as usual--the file driver handles storage:

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

### Configuring the Storage Path

Set the session file directory in `config/session.php`:

```php
return [
    'driver' => 'file',
    'path' => 'storage/sessions', // Relative to project root, or absolute path
];
```

The directory is created automatically if it does not exist.

### Garbage Collection

Expired session files are cleaned up by the `session:gc` command:

```bash
php marko session:gc
```

## API Reference

### FileSessionHandler

```php
public function open(string $path, string $name): bool;
public function close(): bool;
public function read(string $id): string|false;
public function write(string $id, string $data): bool;
public function destroy(string $id): bool;
public function gc(int $max_lifetime): int|false;
```
