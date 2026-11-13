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
    'default' => [
        'guard' => 'web',
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
        'users' => [], // no 'class': your UserProviderInterface binding
    ],
];
```

Each guard loads its users through the provider it names. A provider entry without a `class` uses the `UserProviderInterface` your module binds; give an entry a `class` to back a guard with its own provider. See [User Providers](/docs/packages/authentication/#user-providers).

The `session` driver is built in. The `token` driver comes from [marko/authentication-token](/docs/packages/authentication-token/) (`composer require marko/authentication-token`); until it is installed, resolving the `api` guard throws an error telling you so. Other drivers can be registered with `GuardDriverRegistry`. See [Guard Drivers](/docs/packages/authentication/#guard-drivers).

## Using Authentication

Inject `AuthManager` to check authentication state:

```php title="app/dashboard/src/Controller/DashboardController.php"
<?php

declare(strict_types=1);

namespace App\Dashboard\Controller;

use Marko\Authentication\AuthManager;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Routing\Http\Response;

readonly class DashboardController
{
    public function __construct(
        private AuthManager $authManager,
    ) {}

    #[Get('/dashboard')]
    #[Middleware(AuthMiddleware::class)]
    public function index(): Response
    {
        $user = $this->authManager->user();

        return Response::json(data: [
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

The token guard is stateless: `login()`, `logout()` and `attempt()` throw, and you issue or revoke tokens with `TokenManager` instead. `AuthMiddleware` answers an unauthenticated token request with a `401` and `WWW-Authenticate: Bearer`, never a login redirect. A `#[Can]` route on the token guard sends the same `401` and header.

## Middleware

Marko provides two authentication middleware classes:

```php
use Marko\Authentication\Middleware\AuthMiddleware;   // Must be logged in
use Marko\Authentication\Middleware\GuestMiddleware;   // Must NOT be logged in

#[Get('/dashboard')]
#[Middleware(AuthMiddleware::class)]
public function dashboard(): Response { /* ... */ }

#[Get('/login')]
#[Middleware(GuestMiddleware::class)]
public function loginForm(): Response { /* ... */ }
```

## Custom Guard

Create your own authentication strategy by implementing `GuardInterface`, or `StatelessGuardInterface` for a guard that authenticates each request from credentials it carries:

```php title="app/myapp/src/Auth/ApiKeyGuard.php"
<?php

declare(strict_types=1);

namespace App\MyApp\Auth;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\StatelessGuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Exceptions\AuthException;

class ApiKeyGuard implements StatelessGuardInterface
{
    public function __construct(
        public UserProviderInterface $provider {
            set {
                $this->provider = $value;
            }
        },
        private string $name = 'api-key',
    ) {}

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
        // Look up user by API key from request header
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function attempt(array $credentials, bool $remember = false): bool
    {
        throw new AuthException(
            message: "Cannot call attempt() on API key guard '$this->name': it is stateless",
            suggestion: 'Issue an API key to the client instead',
        );
    }

    // login(), loginById() and logout() throw the same way

    public function getName(): string
    {
        return $this->name;
    }

    public function getChallenge(): string
    {
        return 'ApiKey';
    }
}
```

Register it as a guard driver from your module's `boot` callback, then point a guard at it in your authentication config (`'partner' => ['driver' => 'api-key']`):

```php title="module.php"
use App\MyApp\Auth\ApiKeyGuard;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Guard\GuardDriverRegistry;

return [
    'boot' => function (GuardDriverRegistry $guardDriverRegistry): void {
        $guardDriverRegistry->extend(
            'api-key',
            fn (string $name, array $config, UserProviderInterface $provider): GuardInterface => new ApiKeyGuard(
                provider: $provider,
                name: $name,
            ),
        );
    },
];
```

Every framework `401` for a guest on a stateless guard (from `AuthMiddleware`, `#[Can]` or `AdminAuthMiddleware`) carries its `getChallenge()` value as `WWW-Authenticate`, because all of them throw `UnauthenticatedException::forGuard()`. See [Guard Drivers](/docs/packages/authentication/#guard-drivers).

## Events

The authentication system dispatches events you can observe:

| Event | When |
|---|---|
| `LoginEvent` | Successful login |
| `LogoutEvent` | User logs out |
| `FailedLoginEvent` | Login attempt fails |
| `PasswordResetEvent` | Password is reset |

Token guards have no login or logout. [marko/authentication-token](/docs/packages/authentication-token/#events) dispatches token lifecycle events instead (`TokenCreatedEvent`, `TokenRevokedEvent`, `AllTokensRevokedEvent`, `TokenAuthenticationFailedEvent`).

```php title="app/security/src/Observer/LockoutAfterFailures.php"
use Marko\Authentication\Event\FailedLoginEvent;
use Marko\Core\Attributes\Observer;

#[Observer(event: FailedLoginEvent::class)]
class LockoutAfterFailures
{
    public function handle(FailedLoginEvent $event): void
    {
        // Lock the account after repeated failures...
    }
}
```

## Next Steps

- [Routing](/docs/guides/routing/) — protect routes with middleware
- [Authorization](/docs/packages/authorization/) — role-based access control
- [Testing](/docs/guides/testing/) — test authentication flows
- [Authentication package reference](/docs/packages/authentication/) — full API details
