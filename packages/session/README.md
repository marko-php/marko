# Marko Session

Session interfaces and infrastructure--defines session management, flash messages, and garbage collection without coupling to a storage backend.

## Overview

This package provides `SessionInterface` for key-value session storage, `FlashBag` for one-time messages, and `SessionHandlerInterface` for pluggable storage backends. Configuration covers cookie settings, lifetime, and garbage collection. Install a driver package (`marko/session-file`, `marko/session-database`) for the actual storage.

## Installation

```bash
composer require marko/session
```

Note: You also need a driver package. See `marko/session-file` or `marko/session-database`.

## Usage

### Starting a Session

Inject `SessionInterface`--it wraps PHP's native session handling with a custom handler:

```php
use Marko\Session\Contracts\SessionInterface;

public function __construct(
    private readonly SessionInterface $session,
) {}

public function handle(): void
{
    $this->session->start();
}
```

### Getting and Setting Values

```php
$this->session->set('user_id', 42);
$this->session->get('user_id');         // 42
$this->session->get('missing', 'default'); // 'default'
$this->session->has('user_id');         // true
$this->session->remove('user_id');
$this->session->all();                  // All session data
$this->session->clear();                // Remove everything
```

### Flash Messages

Flash messages persist for exactly one read, then are cleared:

```php
// Set a flash message
$this->session->flash()->add('success', 'Profile updated.');

// Read and clear (typically in the next request)
$messages = $this->session->flash()->get('success');
// ['Profile updated.']

// Peek without clearing
$messages = $this->session->flash()->peek('success');

// Check if messages exist
$this->session->flash()->has('error'); // false
```

### Session Lifecycle

```php
// Regenerate ID (e.g., after login)
$this->session->regenerate();

// Destroy session entirely (e.g., on logout)
$this->session->destroy();

// Save and close
$this->session->save();

// Get current session ID
$id = $this->session->getId();
```

### Garbage Collection

Run expired session cleanup via CLI:

```bash
php marko session:gc
```

The session lifetime is configured in `config/session.php`:

```php
return [
    'lifetime' => 120, // minutes
    'expire_on_close' => false,
    'cookie' => [
        'name' => 'marko_session',
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'lax',
    ],
];
```

## Customization

Replace `Session` via Preferences to add custom behavior (e.g., logging, encryption):

```php
use Marko\Core\Attributes\Preference;
use Marko\Session\Session;

#[Preference(replaces: Session::class)]
class AuditedSession extends Session
{
    public function set(
        string $key,
        mixed $value,
    ): void {
        // Log session writes...
        parent::set($key, $value);
    }
}
```

## API Reference

### SessionInterface

```php
public function start(): void;
public bool $started { get; }
public function get(string $key, mixed $default = null): mixed;
public function set(string $key, mixed $value): void;
public function has(string $key): bool;
public function remove(string $key): void;
public function clear(): void;
public function all(): array;
public function regenerate(bool $deleteOldSession = true): void;
public function destroy(): void;
public function getId(): string;
public function setId(string $id): void;
public function flash(): FlashBag;
public function save(): void;
```

### FlashBag

```php
public function add(string $type, string $message): void;
public function set(string $type, array $messages): void;
public function get(string $type, array $default = []): array;
public function peek(string $type, array $default = []): array;
public function all(): array;
public function has(string $type): bool;
public function clear(): array;
```

### SessionHandlerInterface

Extends PHP's native `SessionHandlerInterface`:

```php
public function open(string $path, string $name): bool;
public function close(): bool;
public function read(string $id): string|false;
public function write(string $id, string $data): bool;
public function destroy(string $id): bool;
public function gc(int $max_lifetime): int|false;
```
