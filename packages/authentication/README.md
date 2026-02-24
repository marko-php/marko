# Marko Authentication

Session and token-based authentication—guards protect routes, events track activity, middleware controls access.

## Overview

The auth package provides flexible authentication with two built-in guards: `SessionGuard` for web applications and `TokenGuard` for APIs. Configure multiple guards, implement custom user providers, and react to authentication events via observers.

## Installation

```bash
composer require marko/authentication
```

## Configuration

The package reads configuration from `config/authentication.php`:

```php
return [
    'default' => [
        'guard' => 'session',
        'provider' => 'users',
    ],

    'guards' => [
        'session' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'token' => [
            'driver' => 'token',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'database',
            'table' => 'users',
        ],
    ],

    'password' => [
        'driver' => 'bcrypt',
        'bcrypt' => [
            'cost' => 12,
        ],
    ],

    'remember' => [
        'expiration' => 43200, // 30 days in minutes
        'cookie' => 'remember_token',
    ],
];
```

## Usage

### Checking Authentication

Inject `AuthManager` to check if a user is authenticated:

```php
use Marko\Authentication\AuthManager;

class DashboardController
{
    public function __construct(
        private AuthManager $auth,
    ) {}

    public function index(): Response
    {
        if ($this->auth->check()) {
            $user = $this->auth->user();
            return new Response("Welcome, user {$this->auth->id()}");
        }

        return Response::redirect('/login');
    }
}
```

### Logging In

Use `attempt()` to authenticate with credentials:

```php
public function login(
    Request $request,
): Response {
    $credentials = [
        'email' => $request->get('email'),
        'password' => $request->get('password'),
    ];

    if ($this->auth->attempt($credentials)) {
        return Response::redirect('/dashboard');
    }

    return new Response('Invalid credentials', 401);
}
```

### Logging Out

```php
public function logout(): Response
{
    $this->auth->logout();

    return Response::redirect('/');
}
```

### Making Users Authenticatable

Your user model must implement `AuthenticatableInterface`:

```php
use Marko\Authentication\AuthenticatableInterface;

class User implements AuthenticatableInterface
{
    public function __construct(
        private int $id,
        private string $email,
        private string $password,
        private ?string $rememberToken = null,
    ) {}

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function setRememberToken(
        ?string $token,
    ): void {
        $this->rememberToken = $token;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
```

## Guards

Guards define how users are authenticated. The package includes two built-in guards.

### SessionGuard

The default guard for web applications. Stores user ID in the session and supports "remember me" functionality:

```php
// Use specific guard
$guard = $this->auth->guard('session');

// Login with remember me
$guard->login($user, remember: true);

// Check via session
if ($guard->check()) {
    $user = $guard->user();
}
```

### TokenGuard

For API authentication via Bearer tokens in the `Authorization` header:

```php
$guard = $this->auth->guard('token');

// TokenGuard extracts token from Authorization header
// Authorization: Bearer your-api-token
if ($guard->check()) {
    $user = $guard->user();
}
```

### Custom Guards

Implement `GuardInterface` to create custom guards:

```php
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;

class JwtGuard implements GuardInterface
{
    private ?UserProviderInterface $provider = null;

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?AuthenticatableInterface
    {
        // Decode JWT and retrieve user
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function attempt(
        array $credentials,
    ): bool {
        // JWT guards typically don't use attempt()
        return false;
    }

    public function login(
        AuthenticatableInterface $user,
    ): void {
        // Generate and return JWT
    }

    public function loginById(
        int|string $id,
    ): ?AuthenticatableInterface {
        return null;
    }

    public function logout(): void {
        // Invalidate token
    }

    public function setProvider(
        UserProviderInterface $provider,
    ): void {
        $this->provider = $provider;
    }

    public function getName(): string
    {
        return 'jwt';
    }
}
```

## Middleware

### AuthMiddleware

Protects routes by requiring authentication:

```php
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;

class DashboardController
{
    #[Get('/dashboard')]
    #[Middleware(AuthMiddleware::class)]
    public function index(): Response
    {
        // Only authenticated users reach here
    }
}
```

For web routes, configure a redirect:

```php
// In module.php bindings
new AuthMiddleware(
    auth: $auth,
    redirectTo: '/login',
);
```

For API routes using TokenGuard, unauthenticated requests receive a 401 JSON response:

```json
{"error": "Unauthorized"}
```

### GuestMiddleware

Restricts routes to unauthenticated users (e.g., login pages):

```php
use Marko\Authentication\Middleware\GuestMiddleware;

class LoginController
{
    #[Get('/login')]
    #[Middleware(GuestMiddleware::class)]
    public function show(): Response
    {
        // Only guests can view the login page
    }
}
```

Authenticated users are redirected to a configured path (default: `/`).

## Events

The auth package dispatches events during authentication lifecycle. Create observers to react to these events.

### LoginEvent

Dispatched when a user successfully logs in:

```php
use Marko\Authentication\Event\LoginEvent;
use Marko\Core\Attributes\Observer;

#[Observer(LoginEvent::class)]
class LogLoginObserver
{
    public function handle(
        LoginEvent $event,
    ): void {
        $user = $event->getUser();
        $guard = $event->getGuard();
        $remember = $event->getRemember();

        // Log the login, update last_login timestamp, etc.
    }
}
```

### LogoutEvent

Dispatched when a user logs out:

```php
use Marko\Authentication\Event\LogoutEvent;
use Marko\Core\Attributes\Observer;

#[Observer(LogoutEvent::class)]
class LogLogoutObserver
{
    public function handle(
        LogoutEvent $event,
    ): void {
        $user = $event->getUser();
        $guard = $event->getGuard();

        // Clean up user session data, log activity, etc.
    }
}
```

### FailedLoginEvent

Dispatched when authentication fails (credentials not found or invalid):

```php
use Marko\Authentication\Event\FailedLoginEvent;
use Marko\Core\Attributes\Observer;

#[Observer(FailedLoginEvent::class)]
class LogFailedLoginObserver
{
    public function handle(
        FailedLoginEvent $event,
    ): void {
        $credentials = $event->getCredentials(); // Password removed for security
        $guard = $event->getGuard();

        // Log failed attempt, implement rate limiting, alert on suspicious activity
    }
}
```

### PasswordResetEvent

Dispatched when a user's password is reset:

```php
use Marko\Authentication\Event\PasswordResetEvent;
use Marko\Core\Attributes\Observer;

#[Observer(PasswordResetEvent::class)]
class NotifyPasswordResetObserver
{
    public function handle(
        PasswordResetEvent $event,
    ): void {
        $user = $event->getUser();

        // Send confirmation email, invalidate other sessions, etc.
    }
}
```

## API Reference

### AuthManager

```php
public function guard(?string $name = null): GuardInterface;
public function check(): bool;
public function user(): ?AuthenticatableInterface;
public function id(): int|string|null;
public function attempt(array $credentials): bool;
public function logout(): void;
```

### GuardInterface

```php
public function check(): bool;
public function guest(): bool;
public function user(): ?AuthenticatableInterface;
public function id(): int|string|null;
public function attempt(array $credentials): bool;
public function login(AuthenticatableInterface $user): void;
public function loginById(int|string $id): ?AuthenticatableInterface;
public function logout(): void;
public function setProvider(UserProviderInterface $provider): void;
public function getName(): string;
```

### AuthenticatableInterface

```php
public function getAuthIdentifier(): int|string;
public function getAuthIdentifierName(): string;
public function getAuthPassword(): string;
public function getRememberToken(): ?string;
public function setRememberToken(?string $token): void;
public function getRememberTokenName(): string;
```

### UserProviderInterface

```php
public function retrieveById(int|string $identifier): ?AuthenticatableInterface;
public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface;
public function validateCredentials(AuthenticatableInterface $user, array $credentials): bool;
public function retrieveByRememberToken(int|string $identifier, string $token): ?AuthenticatableInterface;
public function updateRememberToken(AuthenticatableInterface $user, ?string $token): void;
```
