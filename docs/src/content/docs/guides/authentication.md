---
title: Authentication
description: Guard-based authentication with sessions, tokens, and custom strategies.
---

Marko's authentication system uses a guard-based architecture. Guards handle the "how" of authentication (sessions, tokens, API keys), while user providers handle the "where" (database, LDAP, external API).

## Setup

```bash
composer require marko/authentication
```

Configure guards in `config/auth.php`:

```php title="config/auth.php"
<?php

declare(strict_types=1);

return [
    'defaults' => [
        'guard' => 'web',
    ],
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'database',
        ],
        'api' => [
            'driver' => 'token',
            'provider' => 'database',
        ],
    ],
    'providers' => [
        'database' => [
            'driver' => 'database',
            'table' => 'users',
        ],
    ],
];
```

## Using Authentication

Inject the `AuthManagerInterface` to check authentication state:

```php title="app/dashboard/Controller/DashboardController.php"
<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use Marko\Authentication\AuthManagerInterface;
use Marko\Routing\Attribute\Get;
use Marko\Routing\Attribute\Middleware;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Http\ResponseInterface;
use Marko\Http\JsonResponse;

class DashboardController
{
    public function __construct(
        private readonly AuthManagerInterface $authManager,
    ) {}

    #[Get('/dashboard')]
    #[Middleware(AuthMiddleware::class)]
    public function index(): ResponseInterface
    {
        $user = $this->authManager->user();

        return new JsonResponse(data: [
            'message' => "Welcome, {$user->name}",
        ]);
    }
}
```

## Guards

### Session Guard

For traditional web applications with cookie-based sessions:

```php
// Login
$this->auth->guard('web')->attempt([
    'email' => $email,
    'password' => $password,
]);

// Check if authenticated
$this->auth->guard('web')->check(); // bool

// Get the authenticated user
$this->auth->guard('web')->user(); // AuthenticatableInterface|null

// Logout
$this->auth->guard('web')->logout();
```

### Token Guard

For APIs using bearer tokens:

```php
// Authenticate from the request's Authorization header
$this->auth->guard('api')->user();
```

## Middleware

Marko provides two authentication middleware classes:

```php
use Marko\Authentication\Middleware\AuthMiddleware;   // Must be logged in
use Marko\Authentication\Middleware\GuestMiddleware;   // Must NOT be logged in

#[Get('/dashboard')]
#[Middleware(AuthMiddleware::class)]
public function dashboard(): ResponseInterface { /* ... */ }

#[Get('/login')]
#[Middleware(GuestMiddleware::class)]
public function loginForm(): ResponseInterface { /* ... */ }
```

## Custom Guard

Create your own authentication strategy by implementing `GuardInterface`:

```php title="app/myapp/Auth/ApiKeyGuard.php"
<?php

declare(strict_types=1);

namespace App\MyApp\Auth;

use Marko\Authentication\GuardInterface;
use Marko\Authentication\AuthenticatableInterface;

class ApiKeyGuard implements GuardInterface
{
    public function user(): ?AuthenticatableInterface
    {
        // Look up user by API key from request header
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function attempt(array $credentials): bool
    {
        // Validate API key credentials
    }

    public function logout(): void
    {
        // Invalidate the API key
    }
}
```

Register it via Preference in your `module.php`:

```php title="module.php"
use Marko\Authentication\GuardInterface;
use App\MyApp\Auth\ApiKeyGuard;

return [
    'bindings' => [
        GuardInterface::class => ApiKeyGuard::class,
    ],
];
```

## Events

The authentication system dispatches events you can observe:

| Event | When |
|---|---|
| `LoginEvent` | Successful login |
| `LogoutEvent` | User logs out |
| `FailedLoginEvent` | Login attempt fails |
| `PasswordResetEvent` | Password is reset |

```php title="module.php"
use Marko\Authentication\Event\FailedLoginEvent;
use App\Security\Observer\LockoutAfterFailures;

return [
    'observers' => [
        FailedLoginEvent::class => [
            LockoutAfterFailures::class,
        ],
    ],
];
```

## Next Steps

- [Routing](/docs/guides/routing/) — protect routes with middleware
- [Authorization](/docs/packages/authorization/) — role-based access control
- [Testing](/docs/guides/testing/) — test authentication flows
- [Authentication package reference](/docs/packages/authentication/) — full API details
