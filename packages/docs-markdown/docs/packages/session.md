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

This package is inert on its own --- it defines contracts only and does not register `SessionMiddleware` or bind `SessionInterface`. Session handling activates when you install a driver package, which requires this package automatically.

## Configuration

```php title="config/session.php"
return [
    'driver' => 'file',
    'lifetime' => 120, // minutes
    'expire_on_close' => false,
    'path' => 'storage/sessions', // relative to the project root; never inside public/
    'cookie' => [
        'name' => 'marko_session',
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'lax',
    ],
    'gc_probability' => 2,
    'gc_divisor' => 100,
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

### Next-Request Flash Data

`FlashDataInterface` holds structured data --- arrays, numbers, nested input --- for **exactly the next request**. A value `put()` on one request is readable on the next one and gone on the one after, whether or not anything read it. Reads never consume it.

```php
use Marko\Session\Contracts\FlashDataInterface;

public function __construct(
    private FlashDataInterface $flashData,
) {}

// Request 1
$this->flashData->put('import', ['rows' => 120, 'skipped' => [4, 9]]);

// Request 2: readable any number of times
$this->flashData->get('import');          // ['rows' => 120, 'skipped' => [4, 9]]
$this->flashData->has('import');          // true
$this->flashData->keep('import');         // carry it to request 3 as well

// Request 3 (without keep()): gone
$this->flashData->get('import', []);      // []
```

A value is not visible on the request that put it. Values must be `null`, scalars or arrays of them: flashing a closure or any other object throws `InvalidFlashDataException`. `keep()` with no keys keeps every value.

Which one to use:

| | `FlashBag` (`$session->flash()`) | `FlashDataInterface` |
|---|---|---|
| Holds | String messages by type | Any serialisable data by key |
| Lifetime | Until read with `get()` | Exactly the next request, read or not |
| Reading | `get()` clears, `peek()` doesn't | Never clears |
| Typical use | "Profile updated." notices | Validation errors and old input ([marko/forms](/docs/packages/forms/)) |

`SessionFlashData` implements it under the reserved session key `_flash_data`. The session ages the data once per request, when it loads in `start()` --- including a lazily started session --- so expiry does not depend on anything reading it. A session without flash data is left unchanged.

### Session Lifecycle

```php
// Regenerate ID (e.g., after login)
$this->session->regenerate();

// Destroy session entirely (e.g., on logout)
$this->session->destroy();

// Save and close
$this->session->save();

// Close without writing anything
$this->session->discard();

// Did this request change the session?
$this->session->isModified();

// Get current session ID
$id = $this->session->getId();
```

### Session Middleware

The `SessionMiddleware` prepares the session at the beginning of a request and closes it when the response completes. It is registered globally by the session driver package (e.g., `marko/session-file`, `marko/session-database`) --- no manual registration is needed. Your controllers only need to inject `SessionInterface`; starting and saving are handled automatically.

The middleware runs on every matched route, but not on requests that match no route (404, 405, automatic OPTIONS): those never start a session. See [Which middleware runs](/docs/packages/routing/#which-middleware-runs).

#### Lazy start

How the middleware prepares the session depends on the request:

- **With a session cookie**, it starts the session before the controller runs. The handler is asked whether it knows the ID (see [Strict session IDs](#strict-session-ids)) and reads the stored data, and a resumed session's expiry slides forward on every request, whether or not the controller uses it.
- **Without a session cookie** (or with a malformed one), there is nothing to resume, so it only **arms** the session. The session starts on the first call that uses it: `get()`, `has()`, `set()`, `flash()`, a guard check, a CSRF token. A request that never touches the session makes no handler call at all: no file check, no `SELECT`, no write, no garbage collection.

Starting on first use is limited to the session `SessionMiddleware` prepared. Outside the middleware (a [stateless route](#stateless-routes), a CLI command), the data methods still throw `SessionNotStartedException`; nothing starts a session behind your back.

`$session->started` only becomes `true` once the session has actually started. To ask whether the session can be used on this request, check `isAvailable()`, which is also `true` for an armed session. The session guard from [`marko/authentication`](/docs/packages/authentication/) does this, so logging in on a cookieless request works.

#### Lazy persistence

A session is only stored when the request actually used it. When the response completes, the middleware:

- **saves** the session when the request resumed a session the store knows, or when the request **modified** it (`set()`, `remove()`, `clear()`, a flash message, `regenerate()`). Saving an unmodified resumed session calls the handler's `updateTimestamp()` instead of `write()`, so its expiry slides forward without rewriting the payload.
- **discards** it otherwise: `discard()` closes the session without calling the handler's `write()`, and no `Set-Cookie` is sent.

Reading never counts as a modification. A visitor without a session cookie who only views public pages gets no session row or file and no cookie, even if the page calls `get()` or `has()` or checks for a logged-in user. The first write (logging in, adding to a cart, issuing a CSRF token, flashing a message) persists the session and sends the cookie. A page that only reads the session (checking for a logged-in user, showing flash messages) still starts it, which reads the handler once, but stores nothing.

#### Strict session IDs

A session cookie only resumes a session the store already knows. `Session` runs PHP with `session.use_strict_mode=1`, so before resuming an inbound ID PHP asks the handler's `validateId()`. An ID the store has never issued, or one whose session is older than the configured `lifetime`, is discarded: the request gets a fresh ID, starts with an empty session and stores nothing unless it writes to the session. A client can never choose its own session ID, which closes off session fixation and stops replayed or made-up cookies from creating a stored session on every request.

When the inbound cookie resumed nothing (an unknown, expired, malformed or tampered ID) and the request stored nothing, the response carries an expired session cookie so the client stops sending it. If the request did write to the session, the new session's cookie replaces the old one instead.

The session cookie is attached to the `Response` rather than emitted directly by PHP --- `Session::configure()` disables PHP's built-in cookie handling, so `SessionMiddleware` reads the inbound cookie off the `Request`, seeds the session ID before `start()`, and attaches an outbound cookie only when a saved session's ID changed (a new session that was written to, a regenerated ID, or an expired cookie after `destroy()` of a session the client already had), or when an inbound cookie was rejected (an expired cookie, see above). A repeat visitor whose session ID is unchanged gets no `Set-Cookie` header. An invalid or tampered inbound cookie never raises an error --- the middleware falls through to a fresh session.

This matters for [`marko/page-cache`](/docs/packages/page-cache/): responses carrying any cookie are never cached, so attaching the session cookie unconditionally would silently disable page caching on every session-enabled route.

### Stateless Routes

Because the session middleware is global, every matched route starts a session by default. It reads the session file or row, and writes it back for visitors who already have a session. A JSON API, a webhook receiver or a health check doesn't need any of that. Skip the middleware with [`#[WithoutMiddleware]`](/docs/packages/routing/#skipping-middleware), on one route or on a whole controller:

```php title="app/api/src/Controller/ShowApiController.php"
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\RoutePrefix;
use Marko\Routing\Attributes\WithoutMiddleware;
use Marko\Routing\Http\Response;
use Marko\Session\Middleware\SessionMiddleware;

#[RoutePrefix('/api/v1', namePrefix: 'api.v1.')]
#[WithoutMiddleware(SessionMiddleware::class)]
class ShowApiController
{
    #[Get('/shows/{id:\d+}', name: 'shows.show')]
    public function show(int $id): Response
    {
        return Response::json($this->showRepository->find($id)->toArray());
    }
}

class HealthController
{
    #[Get('/health')]
    #[WithoutMiddleware(SessionMiddleware::class)]
    public function health(): Response
    {
        return Response::json(['status' => 'ok']);
    }
}
```

On these routes the session is never started. The session handler is never called, so no file or row is read or written, and the response carries no session cookie. Every other route keeps its session.

Code that needs the session fails loudly on a stateless route. It never starts a session behind your back:

- `SessionInterface::get()`, `set()` and the other data methods throw `SessionNotStartedException`.
- The session guard from [`marko/authentication`](/docs/packages/authentication/) throws `AuthException` ("Session not started"). Authenticate stateless APIs with a bearer token instead: [`marko/authentication-token`](/docs/packages/authentication-token/). The same applies to `#[Can]` checks on these routes.
- CSRF protection that keeps its token in the session can't work there either. That's expected for token-authenticated APIs and signed webhooks. With [`marko/security`](/docs/packages/security/) installed, `CsrfMiddleware` is global, so a stateless route that accepts `POST`, `PUT`, `PATCH` or `DELETE` must exclude it too --- `#[WithoutMiddleware([SessionMiddleware::class, CsrfMiddleware::class])]` --- or the request fails with `CsrfSessionUnavailableException`.

Excluding `SessionMiddleware` requires a session driver (`marko/session-file` or `marko/session-database`) to be installed, because the driver registers the middleware. Without a driver, the exclusion names a middleware that isn't in the stack, and boot fails with a `RouteException`.

### Long-Running Processes

`Session` implements `Marko\Core\Contracts\ResettableInterface`. In a long-running worker (e.g. Swoole, RoadRunner), call `reset()` between requests to clear the cached session ID, data, and flash bag so one request's session state is never reused for the next:

```php
use Marko\Core\Contracts\ResettableInterface;
use Marko\Session\Contracts\SessionInterface;

if ($this->session instanceof ResettableInterface) {
    $this->session->reset();
}
```

`save()` does not clear session state on its own --- `SessionMiddleware` reads `getId()` after `save()` runs to decide whether to attach a cookie, so clearing happens explicitly via `reset()` instead.

### Garbage Collection

Run expired session cleanup via CLI:

```bash
marko session:gc
```

The session lifetime configured in `config/session.php` determines when sessions expire. Garbage collection probability is also configurable via `gc_probability` and `gc_divisor` settings.

PHP rolls the `gc_probability`/`gc_divisor` dice only when a session starts. Because cookieless requests that never touch the session no longer start one (see [Lazy start](#lazy-start)), anonymous traffic no longer triggers garbage collection. On a busy site where most traffic is anonymous, expired sessions can pile up, so schedule `marko session:gc` (for example from cron every hour) instead of relying on the probability settings.

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
public function arm(): void;          // start on first access; SessionMiddleware calls this for cookieless requests
public function isAvailable(): bool;  // started, or armed to start on first access
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
public function isModified(): bool; // data or ID changed since start(); reads don't count
public function discard(): void;    // close without writing to the handler
```

`arm()` and `isAvailable()` are what `SessionMiddleware` uses for [lazy start](#lazy-start), and `isModified()` and `discard()` are what it uses for [lazy persistence](#lazy-persistence). A custom `SessionInterface` implementation must provide all four. An armed session starts on its first data access, and `save()`, `discard()`, `destroy()` and `reset()` end the armed state. Code that checked `$session->started` to decide whether the session can be used should check `isAvailable()` instead.

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

### FlashDataInterface

```php
use Marko\Session\Contracts\FlashDataInterface;

public function put(string $key, mixed $value): void;           // next request only; throws InvalidFlashDataException for objects
public function get(string $key, mixed $default = null): mixed; // what the previous request flashed; never consumes
public function has(string $key): bool;
public function keep(string ...$keys): void;                    // carry values one more request; no keys = all
```

`marko/session` binds it to `SessionFlashData`. `SessionFlashData::age()` is the aging step `Session::start()` runs on freshly loaded data; a custom `SessionInterface` implementation should run it the same way.

### SessionHandlerInterface

Extends PHP's native `SessionHandlerInterface` and `SessionUpdateTimestampHandlerInterface`:

```php
use Marko\Session\Contracts\SessionHandlerInterface;

public function open(string $path, string $name): bool;
public function close(): bool;
public function read(string $id): string|false;
public function write(string $id, string $data): bool;
public function destroy(string $id): bool;
public function gc(int $max_lifetime): int|false;
public function validateId(string $id): bool;
public function updateTimestamp(string $id, string $data): bool;
```

### Custom Session Handlers

To store sessions somewhere else, implement `SessionHandlerInterface` and bind it in your module's `module.php`. Besides PHP's six handler methods, a handler must implement the two methods strict session IDs depend on:

- `validateId(string $id): bool` --- return `true` only when the store holds a session with this ID that is still within the configured lifetime. Return `false` for an unknown or expired ID; PHP then starts a fresh session instead of adopting the ID.
- `updateTimestamp(string $id, string $data): bool` --- refresh the expiry of an existing session without rewriting its payload. PHP calls it instead of `write()` when the data didn't change. It must never create a session that doesn't exist; return `true` when there's nothing to update.

```php title="app/sessions/src/Handler/RedisSessionHandler.php"
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;

class RedisSessionHandler implements SessionHandlerInterface
{
    public function __construct(
        private readonly Redis $redis,
        private readonly SessionConfig $sessionConfig,
    ) {}

    public function validateId(string $id): bool
    {
        return $this->redis->exists('session:' . $id) === 1;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $this->redis->expire('session:' . $id, $this->sessionConfig->lifetime() * 60, 'XX');

        return true;
    }

    // open(), close(), read(), write(), destroy() and gc() ...
}
```

A handler that returns `true` from `validateId()` for every ID turns strict mode off: any well-formed cookie would be adopted and written back.

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
| `InvalidFlashDataException` | Thrown when `FlashDataInterface::put()` gets a closure or other object, naming the nested path |
