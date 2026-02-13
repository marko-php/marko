# Plan: Session Package

## Created
2026-01-21

## Status
completed

## Objective
Implement the session layer for Marko framework with a clean interface/implementation split pattern, providing `marko/session` (interfaces, session management, flash messages, and session middleware) and `marko/session-file` (file-based driver implementation using PHP's native session handling).

## Scope

### In Scope
- `marko/session` package with interfaces, session management, flash messages, and exceptions
  - `SessionInterface` - primary session contract (get, set, has, remove, regenerate, destroy)
  - `SessionHandlerInterface` - storage backend contract (maps to PHP's `SessionHandlerInterface`)
  - `FlashBag` - flash message storage (data persists only for next request)
  - `SessionConfig` - configuration loaded from config/session.php
  - `SessionMiddleware` - routing middleware for automatic session start/save
  - `SessionException` hierarchy (SessionException, SessionNotStartedException, InvalidSessionIdException)
  - CLI commands: `session:gc` (garbage collection)
- `marko/session-file` package with file-based session handler implementation
  - `FileSessionHandler` - implements SessionHandlerInterface using filesystem storage
  - `FileSessionHandlerFactory` - factory for creating handler with config
  - Configurable session directory (default: `storage/sessions`)
  - Session file locking for concurrent access safety
  - Garbage collection support

### Out of Scope
- Database session driver (future package: `marko/session-database`)
- Redis session driver (future package: `marko/session-redis`)
- Session encryption at rest (PHP's built-in encryption can be used via config)
- CSRF token management (separate security concern, future package)
- Remember me functionality (authentication concern)
- Session clustering/sharing between servers

## Success Criteria
- [ ] `SessionInterface` provides clean get/set/has/remove/regenerate/destroy contract
- [ ] `SessionConfig` loads configuration from `config/session.php`
- [ ] `SessionMiddleware` automatically starts session and saves on response
- [ ] `FlashBag` stores messages that are cleared after being read
- [ ] `FileSessionHandler` implements all session operations using filesystem
- [ ] `session:gc` command triggers garbage collection
- [ ] Loud error when no session driver is installed
- [ ] Driver conflict handling if multiple drivers installed
- [ ] Session ID regeneration works for security (prevent session fixation)
- [ ] Session cookie configuration respected (name, path, domain, secure, httponly, samesite)
- [ ] All tests passing
- [ ] Code follows project standards (strict types, no final, etc.)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding (composer.json files for both packages) | - | pending |
| 002 | SessionException hierarchy | 001 | pending |
| 003 | SessionHandlerInterface contract | 001 | pending |
| 004 | SessionInterface contract | 003 | pending |
| 005 | SessionConfig class | 001 | pending |
| 006 | FlashBag implementation | 004 | pending |
| 007 | Session implementation | 004, 005, 006 | pending |
| 008 | SessionMiddleware | 007 | pending |
| 009 | session package module.php with bindings | 005, 008 | pending |
| 010 | FileSessionHandler implementation | 003 | pending |
| 011 | FileSessionHandlerFactory | 005, 010 | pending |
| 012 | session-file module.php with bindings | 011 | pending |
| 013 | CLI: session:gc command | 003 | pending |
| 014 | Unit tests for session package | 002-008 | pending |
| 015 | Unit tests for session-file package | 010, 011 | pending |
| 016 | Integration tests | 012, 013 | pending |

## Architecture Notes

### Package Structure
```
packages/
  session/                     # Interfaces + shared code
    src/
      Contracts/
        SessionInterface.php
        SessionHandlerInterface.php
      Config/
        SessionConfig.php
      Flash/
        FlashBag.php
      Middleware/
        SessionMiddleware.php
      Exceptions/
        SessionException.php
        SessionNotStartedException.php
        InvalidSessionIdException.php
      Session.php
      Command/
        GarbageCollectCommand.php
    tests/
    composer.json
    module.php
  session-file/                # File-based implementation
    src/
      Handler/
        FileSessionHandler.php
      Factory/
        FileSessionHandlerFactory.php
    tests/
    composer.json
    module.php
```

### Config Location
```php
// config/session.php
return [
    'driver' => 'file',
    'lifetime' => 120, // minutes
    'expire_on_close' => false,
    'path' => $_ENV['SESSION_PATH'] ?? 'storage/sessions',

    // Cookie configuration
    'cookie' => [
        'name' => 'marko_session',
        'path' => '/',
        'domain' => $_ENV['SESSION_DOMAIN'] ?? null,
        'secure' => (bool) ($_ENV['SESSION_SECURE'] ?? true),
        'httponly' => true,
        'samesite' => 'lax', // 'lax', 'strict', or 'none'
    ],

    // Garbage collection
    'gc_probability' => 2,
    'gc_divisor' => 100,
];
```

### Interface Design
```php
// SessionInterface - clean, focused contract
interface SessionInterface
{
    /**
     * Start the session.
     */
    public function start(): void;

    /**
     * Check if session has been started.
     */
    public function isStarted(): bool;

    /**
     * Get a value from the session.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a value in the session.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Check if session has a key.
     */
    public function has(string $key): bool;

    /**
     * Remove a value from the session.
     */
    public function remove(string $key): void;

    /**
     * Clear all session data.
     */
    public function clear(): void;

    /**
     * Get all session data.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Regenerate the session ID.
     *
     * @param bool $deleteOldSession Whether to delete the old session file
     */
    public function regenerate(bool $deleteOldSession = true): void;

    /**
     * Invalidate and destroy the session.
     */
    public function destroy(): void;

    /**
     * Get the session ID.
     */
    public function getId(): string;

    /**
     * Set the session ID (before start).
     */
    public function setId(string $id): void;

    /**
     * Get the flash bag for flash messages.
     */
    public function flash(): FlashBag;

    /**
     * Save session data and close.
     */
    public function save(): void;
}
```

```php
// SessionHandlerInterface - mirrors PHP's SessionHandlerInterface
interface SessionHandlerInterface extends \SessionHandlerInterface
{
    /**
     * Perform garbage collection.
     *
     * @param int $maxLifetime Sessions older than this (in seconds) will be deleted
     * @return int Number of sessions deleted
     */
    public function gc(int $maxLifetime): int|false;
}
```

```php
// FlashBag - messages that persist for one request only
class FlashBag
{
    public function add(string $type, string $message): void;

    public function set(string $type, array $messages): void;

    public function get(string $type, array $default = []): array;

    public function peek(string $type, array $default = []): array;

    public function all(): array;

    public function has(string $type): bool;

    public function clear(): array;
}
```

### Middleware Integration
```php
// SessionMiddleware - automatic session handling
#[Middleware(priority: 100)] // High priority, runs early
class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionInterface $session,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        // Start session before controller
        $this->session->start();

        // Process request
        $response = $next($request);

        // Save session after response generated
        $this->session->save();

        return $response;
    }
}
```

### Session Usage in Controllers
```php
class LoginController
{
    public function __construct(
        private readonly SessionInterface $session,
    ) {}

    #[Post('/login')]
    public function login(Request $request): Response
    {
        // Authenticate user...

        // Regenerate session ID for security (prevent session fixation)
        $this->session->regenerate();

        // Store user ID
        $this->session->set('user_id', $user->id);

        // Add flash message
        $this->session->flash()->add('success', 'Login successful!');

        return Response::redirect('/dashboard');
    }

    #[Post('/logout')]
    public function logout(): Response
    {
        // Destroy entire session
        $this->session->destroy();

        return Response::redirect('/');
    }
}
```

### Flash Message Usage
```php
// In controller - set flash messages
$session->flash()->add('success', 'Your changes have been saved.');
$session->flash()->add('error', 'Please fix the errors below.');
$session->flash()->add('warning', 'Your subscription expires soon.');

// In view/template - read flash messages (auto-cleared after read)
$messages = $session->flash()->get('success'); // Returns and clears
$all = $session->flash()->all(); // Returns all and clears

// Peek without clearing
$messages = $session->flash()->peek('success'); // Returns but keeps
```

### File Session Storage Format
```
// Storage: storage/sessions/sess_{session_id}
// Format: PHP serialized session data (standard PHP session format)
user_id|i:42;user_name|s:4:"John";_flash|a:1:{s:7:"success";a:1:{i:0;s:5:"Hello";}}
```

### Session ID Security
Session IDs are validated to prevent injection attacks:
```php
private function validateId(string $id): bool
{
    // Only alphanumeric and hyphen allowed
    // Length between 32 and 128 characters
    return preg_match('/^[a-zA-Z0-9-]{32,128}$/', $id) === 1;
}
```

### Driver Conflict Handling
Only one driver package can be installed. If both `marko/session-file` and `marko/session-database` are installed, the framework throws a loud error during boot:

```
BindingConflictException: Multiple implementations bound for SessionHandlerInterface.

Context: Both FileSessionHandler and DatabaseSessionHandler are attempting to bind.

Suggestion: Install only one session driver package. Remove one with:
  composer remove marko/session-file
  or
  composer remove marko/session-database
```

### No Driver Installed Handling
If `marko/session` is installed without a driver, attempting to use session features throws:

```
SessionException: No session driver installed.

Context: Attempted to resolve SessionHandlerInterface but no implementation is bound.

Suggestion: Install a session driver package:
  composer require marko/session-file
  or
  composer require marko/session-database
```

### CLI Commands

**session:gc** - Manual garbage collection
```
$ marko session:gc
Garbage collection complete. Removed 42 expired sessions.
```

### Module Bindings

**session/module.php**
```php
return [
    'enabled' => true,
    'bindings' => [
        SessionConfig::class => SessionConfig::class,
        SessionInterface::class => function (ContainerInterface $container): SessionInterface {
            return new Session(
                $container->get(SessionHandlerInterface::class),
                $container->get(SessionConfig::class),
            );
        },
    ],
];
```

**session-file/module.php**
```php
return [
    'enabled' => true,
    'bindings' => [
        SessionHandlerInterface::class => function (ContainerInterface $container): SessionHandlerInterface {
            return $container->get(FileSessionHandlerFactory::class)->create();
        },
    ],
];
```

### Concurrent Access Safety
The file handler uses file locking to prevent race conditions:
```php
public function read(string $id): string|false
{
    $path = $this->getPath($id);

    if (!file_exists($path)) {
        return '';
    }

    $handle = fopen($path, 'r');
    flock($handle, LOCK_SH); // Shared lock for reading
    $data = fread($handle, filesize($path) ?: 1);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $data;
}

public function write(string $id, string $data): bool
{
    $path = $this->getPath($id);

    $handle = fopen($path, 'c');
    flock($handle, LOCK_EX); // Exclusive lock for writing
    ftruncate($handle, 0);
    fwrite($handle, $data);
    flock($handle, LOCK_UN);
    fclose($handle);

    return true;
}
```

### PHP Session Configuration
The Session class configures PHP's session settings before calling `session_start()`:
```php
private function configure(): void
{
    ini_set('session.save_handler', 'user');
    ini_set('session.gc_maxlifetime', (string) ($this->config->lifetime * 60));
    ini_set('session.gc_probability', (string) $this->config->gcProbability);
    ini_set('session.gc_divisor', (string) $this->config->gcDivisor);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');

    session_name($this->config->cookieName);

    session_set_cookie_params([
        'lifetime' => $this->config->expireOnClose ? 0 : $this->config->lifetime * 60,
        'path' => $this->config->cookiePath,
        'domain' => $this->config->cookieDomain ?? '',
        'secure' => $this->config->cookieSecure,
        'httponly' => $this->config->cookieHttpOnly,
        'samesite' => ucfirst($this->config->cookieSameSite),
    ]);

    session_set_save_handler($this->handler, true);
}
```

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Filesystem permissions | Clear error messages when session directory is not writable, with suggestion to check permissions |
| Session fixation attacks | `regenerate()` method provided; documentation emphasizes regenerating after authentication |
| Session hijacking | Secure cookie defaults (httponly, secure, samesite); documentation on HTTPS requirement |
| Concurrent access | File locking on read/write; atomic operations where possible |
| Session ID collision | PHP's `session_create_id()` provides cryptographically secure IDs |
| Flash message loss | Flash data stored in session; cleared only after successful retrieval |
| Large session data | Document size limits; recommend storing only essential data in session |
| PHP session already started | Check `session_status()` before operations; throw clear exception if conflict |
