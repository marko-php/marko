# Marko Authentication

Session and token-based authentication---guards protect routes, events track activity, middleware controls access.

## Installation

```bash
composer require marko/authentication
```

## Configuration

Publish the configuration file to `config/authentication.php`:

```php
return [
    'default' => [
        'guard' => 'web',
        'provider' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'token',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    'password' => [
        'bcrypt' => [
            'cost' => 12,
        ],
    ],

    'remember' => [
        'lifetime' => 604800,
    ],
];
```

## Usage

Use `AuthManager` to interact with the authentication system:

```php
use Marko\Authentication\AuthManager;

class LoginController
{
    public function __construct(
        private AuthManager $authManager,
    ) {}

    public function login(array $credentials): bool
    {
        if ($this->authManager->attempt($credentials)) {
            return true;
        }

        return false;
    }

    public function dashboard(): Response
    {
        if ($this->authManager->check()) {
            $user = $this->authManager->user();
            return new Response("Welcome, {$user->getName()}");
        }

        return Response::redirect('/login');
    }

    public function logout(): void
    {
        $this->authManager->logout();
    }
}
```

## Guards

Guards define how users are authenticated for each request. The `Guard` interface is implemented by all guard drivers.

### SessionGuard

The `SessionGuard` authenticates users via session storage. It is the default guard for web requests:

```php
// Resolved automatically when using the 'session' driver in config
$guard = $this->authManager->guard('web'); // returns SessionGuard
```

### TokenGuard

The `TokenGuard` authenticates users via a token sent with each request. Useful for API authentication:

```php
// Resolved automatically when using the 'token' driver in config
$guard = $this->authManager->guard('api'); // returns TokenGuard
```

## Middleware

### AuthMiddleware

`AuthMiddleware` ensures a request is made by an authenticated user. Unauthenticated requests are redirected to the login page:

```php
use Marko\Authentication\Middleware\AuthMiddleware;

// In your route or middleware stack
$middleware = [AuthMiddleware::class];
```

### GuestMiddleware

`GuestMiddleware` ensures a request is made by a guest (unauthenticated user). Authenticated users are redirected away from guest-only routes such as login and register:

```php
use Marko\Authentication\Middleware\GuestMiddleware;

// In your route or middleware stack
$middleware = [GuestMiddleware::class];
```

## Events

Authentication events are dispatched automatically during the authentication lifecycle.

### LoginEvent

Dispatched when a user successfully logs in:

```php
use Marko\Authentication\Event\LoginEvent;

// Dispatched automatically on successful login
```

### LogoutEvent

Dispatched when a user logs out:

```php
use Marko\Authentication\Event\LogoutEvent;

// Dispatched automatically on logout
```

### FailedLoginEvent

Dispatched when a login attempt fails:

```php
use Marko\Authentication\Event\FailedLoginEvent;

// Dispatched automatically on failed login attempt
```

## Documentation

Full usage, API reference, and examples: [marko/authentication](https://marko.build/docs/packages/authentication/)
