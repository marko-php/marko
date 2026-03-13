---
title: marko/session
description: Session interfaces and infrastructure — defines session management, flash messages, and garbage collection without coupling to a storage backend.
---

Session interfaces and infrastructure --- defines session management, flash messages, and garbage collection without coupling to a storage backend. This package provides `SessionInterface` for key-value session storage, `FlashBag` for one-time messages, and `SessionHandlerInterface` for pluggable storage backends. Configuration covers cookie settings, lifetime, and garbage collection.

**This package defines contracts only.** Install a driver for implementation:

- `marko/session-file` --- File-based (default)
- `marko/session-database` --- Database-backed

## Installation

```bash
composer require marko/session
```

Note: You typically install a driver package (like `marko/session-file`) which requires this automatically.

## Configuration

```php title="config/session.php"
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

The `SessionConfig` class provides typed access to all configuration values:

```php
use Marko\Session\Config\SessionConfig;

class MyService
{
    public function __construct(
        private SessionConfig $sessionConfig,
    ) {}

    public function setup(): void
    {
        $driver = $this->sessionConfig->driver();
        $lifetime = $this->sessionConfig->lifetime();
        $expireOnClose = $this->sessionConfig->expireOnClose();
        $cookieName = $this->sessionConfig->cookieName();
    }
}
```

## Usage

### Starting a Session

Inject `SessionInterface` --- it wraps PHP's native session handling with a custom handler:

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
$this->session->get('user_id');            // 42
$this->session->get('missing', 'default'); // 'default'
$this->session->has('user_id');            // true
$this->session->remove('user_id');
$this->session->all();                     // All session data
$this->session->clear();                   // Remove everything
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

### Session Middleware

The `SessionMiddleware` automatically starts the session at the beginning of a request and saves it when the response completes:

```php
use Marko\Session\Middleware\SessionMiddleware;
```

Register it in your route or middleware stack --- it handles `start()` and `save()` so your controllers don't have to.

### Garbage Collection

Run expired session cleanup via CLI:

```bash
marko session:gc
```

The session lifetime configured in `config/session.php` determines when sessions expire. Garbage collection probability is also configurable via `gc_probability` and `gc_divisor` settings.

## Customization

Replace `Session` via [Preferences](/docs/packages/core/) to add custom behavior (e.g., logging, encryption):

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
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;

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
use Marko\Session\Flash\FlashBag;

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
use Marko\Session\Contracts\SessionHandlerInterface;

public function open(string $path, string $name): bool;
public function close(): bool;
public function read(string $id): string|false;
public function write(string $id, string $data): bool;
public function destroy(string $id): bool;
public function gc(int $max_lifetime): int|false;
```

### SessionConfig

```php
use Marko\Session\Config\SessionConfig;

public function driver(): string;
public function lifetime(): int;
public function expireOnClose(): bool;
public function path(): string;
public function cookieName(): string;
public function cookiePath(): string;
public function cookieDomain(): ?string;
public function cookieSecure(): bool;
public function cookieHttpOnly(): bool;
public function cookieSameSite(): string;
public function gcProbability(): int;
public function gcDivisor(): int;
```

### Exceptions

| Exception | Description |
|-----------|-------------|
| `SessionException` | Base exception for all session errors --- includes `getContext()` and `getSuggestion()` methods |
| `SessionNotStartedException` | Thrown when accessing session data before calling `start()` |
| `InvalidSessionIdException` | Thrown when a session ID has an invalid format (must be alphanumeric with hyphens, 32--128 characters) |
