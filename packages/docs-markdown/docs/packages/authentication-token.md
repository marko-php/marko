---
title: marko/authentication-token
description: Stateless API token authentication --- issue personal access tokens with scoped abilities for mobile apps, SPAs, and third-party integrations.
---

Stateless API token authentication --- issue personal access tokens with scoped abilities for mobile apps, SPAs, and third-party integrations. This package adds personal access token authentication to the Marko Framework. Each token is stored as a SHA-256 hash; the plain-text value is only available once at creation time. Tokens can be scoped to a list of abilities so you can issue limited-permission tokens for specific integrations. `TokenGuard` serves the `token` guard driver: once the package is installed, every guard configured with `'driver' => 'token'` authenticates API requests from the `Authorization: Bearer` header.

## Installation

```bash
composer require marko/authentication-token
```

## Configuration

Override defaults in `config/authentication-token.php`:

```php title="config/authentication-token.php"
return [
    // Default lifetime, in days, of a token created without expiresAt.
    // A positive integer, or null for tokens that never expire.
    'token_expiration_days' => 365,
];
```

`token_expiration_days` is the default lifetime of a new token. When `createToken()` is called without `expiresAt`, `TokenManager` stores the injected clock's current time plus this many days as the token's `expires_at`, so tokens expire after a year unless you configure otherwise. The expiry is fixed when the token is created: changing the value later does not affect tokens already issued.

To issue tokens that never expire by default, set it to `null`:

```php title="config/authentication-token.php"
return [
    'token_expiration_days' => null,
];
```

Any other value (`0`, a negative number, a numeric string such as `'30'`) makes `createToken()` throw a `ConfigException`, even when the call passes its own `expiresAt`. To drive the value from the environment, read it with `Env::int()`, which returns a real integer and enforces the minimum:

```php title="config/authentication-token.php"
use Marko\Config\Env;

return [
    'token_expiration_days' => Env::int('TOKEN_EXPIRATION_DAYS', 365, min: 1),
];
```

## Usage

### Creating a Token

Inject `TokenManager` and call `createToken()`. Capture `plainTextToken` immediately --- it is never retrievable again.

```php title="ApiTokenController.php"
use DateTimeImmutable;
use Marko\AuthenticationToken\Service\TokenManager;
use Marko\Authentication\AuthenticatableInterface;

class ApiTokenController
{
    public function __construct(
        private TokenManager $tokenManager,
    ) {}

    public function store(
        AuthenticatableInterface $user,
    ): Response {
        $newToken = $this->tokenManager->createToken(
            user: $user,
            name: 'mobile-app',
            abilities: ['posts:read', 'posts:write'],
        );

        // plainTextToken is ONLY available here — store it immediately
        return new Response([
            'token' => $newToken->plainTextToken,
        ]);
    }

    public function storeWithExpiry(
        AuthenticatableInterface $user,
    ): Response {
        $newToken = $this->tokenManager->createToken(
            user: $user,
            name: 'short-lived',
            abilities: ['posts:read'],
            expiresAt: new DateTimeImmutable('+30 days'),
        );

        return new Response([
            'token' => $newToken->plainTextToken,
        ]);
    }
}
```

The `expiresAt` parameter is optional. Without it, the token expires `token_expiration_days` days after creation (365 by default), or never when that config is `null`. An explicit `expiresAt` always overrides the configured default, whether it is shorter or longer. Once the current time is past a token's expiry, `TokenGuard` rejects the token and returns `null` from `user()`. `TokenManager` also sets the token's `createdAt` from the clock.

> **Behaviour change:** before the default lifetime was wired, `token_expiration_days` was never read and a token created without `expiresAt` never expired. Tokens created now get `expires_at` = creation time + 365 days unless you set the config to `null`. Tokens already stored keep their `null` expiry.

> **Behaviour change:** `TokenManager` used to format `expiresAt` in whatever timezone the caller's object carried, and `TokenGuard` read it back in PHP's default timezone, so a UTC expiry on a server running `America/New_York` lived four to five hours longer than asked. Both now use the database timezone. On a server whose PHP default timezone isn't the database timezone, tokens already stored hold local wall-clock expiries; convert `expires_at` and `created_at` with the SQL in [upgrading the queue tables](/docs/packages/queue-database/#upgrading-rows-written-in-the-old-timezone), or accept that their expiry shifts by the offset.

> **Security note:** The plain-text token is available only on `NewAccessToken::$plainTextToken` at creation time. The database stores only a SHA-256 hash. If you lose the plain text, you must revoke and re-issue.

### Wiring the Token Guard

Installing the package registers the `token` guard driver with `AuthManager` (through `GuardDriverRegistry`, from the package's `module.php` `boot` callback). Any guard configured with `'driver' => 'token'` resolves to `Marko\AuthenticationToken\Guard\TokenGuard`, so `AuthManager::guard()`, `GuardInterface`, `AuthMiddleware`, `#[Can]` and the `Gate` all use it, with expiry enforced. Point a guard at the driver in `config/authentication.php`:

```php title="config/authentication.php"
return [
    'default' => [
        'guard' => 'api',
        'provider' => 'users',
    ],
    'guards' => [
        'session' => ['driver' => 'session', 'provider' => 'users'],
        'api' => ['driver' => 'token', 'provider' => 'users'],
    ],
    // ...
];
```

Bind your `TokenRepositoryInterface` implementation (backed by the `personal_access_tokens` table) in your app's `module.php`. The guard resolves it the first time a token guard is built, so commands and requests that never touch a token guard do not need it.

The package also registers `TokenRequestMiddleware` as global middleware. It hands each inbound request to the guard (through the `CurrentRequest` singleton) and clears it when the request ends, so a long-running worker never reuses an earlier request's token. It is sequenced before `marko/authorization`, so `#[Can]` sees the token. Outside an HTTP request (CLI, queue jobs), the token guard has no request and treats the caller as a guest. `TokenRequestMiddleware` is not marked `#[RunsOnUnmatched]`, so it does not run for 404, 405 or automatic OPTIONS responses: no route means nothing authenticates the token (see [Which middleware runs](/docs/packages/routing/#which-middleware-runs)).

### Bearer Token Authentication Flow

Clients send the token in the `Authorization` header on every request:

```
Authorization: Bearer <plain-text-token>
```

`TokenGuard` hashes the token, looks it up in `personal_access_tokens`, rejects it once `expires_at` has passed, and resolves the user through the configured user provider by `tokenable_id`. The resolved user must be an instance of the token's `tokenable_type` (the class the token was issued for, or a subclass of it); otherwise the request is a guest, so a token issued for a `Customer` with id 1 never authenticates an `AdminUser` with id 1. Point each token guard at the provider for the model its tokens are issued to. `expires_at` and `created_at` are written by `TokenManager` and read by `TokenGuard` in the [database timezone](/docs/packages/database/#datetimes-and-timezones) (`database.timezone`, UTC by default), so an expiry passed in any timezone is honoured to the second whatever the server's PHP default timezone. Protect routes with `AuthMiddleware` on the token guard:

```php title="ApiController.php"
use Marko\Authentication\AuthManager;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;

class ApiController
{
    public function __construct(
        private AuthManager $authManager,
    ) {}

    #[Get('/api/me')]
    #[Middleware(AuthMiddleware::class)] // default guard 'api' uses the token driver
    public function me(): Response
    {
        $user = $this->authManager->guard('api')->user(); // resolved from the Bearer token

        return new Response("Hello, user {$user->getAuthIdentifier()}");
    }
}
```

A missing, unknown, revoked or expired token gets a `401` with `WWW-Authenticate: Bearer` from `AuthMiddleware`, never a login redirect, because the token guard implements `StatelessGuardInterface`. A route protected only by [`#[Can]`](/docs/packages/authorization/) answers the same requests with the same `401` and `WWW-Authenticate: Bearer` when the token guard is the authorization guard.

### Stateless Methods

The token guard keeps no login state. `attempt()`, `login()`, `loginById()` and `logout()` throw a `StatelessGuardException` (an `AuthException`) that points you to `TokenManager`: issue a token with `createToken()` and end access with `revokeToken()` or `revokeAllTokens()`.

### Checking Token Abilities

A token's abilities can only narrow what its user may do. Three rules decide what a token grants:

| `abilities` passed to `createToken()` | Grants |
|---|---|
| `[]` (the default), or a `NULL` column | Every ability: the token has the full authority of its user |
| A list containing `'*'` | Every ability |
| Any other list, e.g. `['posts:read']` | Exactly the listed abilities. Matching is exact and case-sensitive: `posts:read` does not grant `posts`, `posts:*` or `POSTS:READ` |

An `abilities` column that is not a JSON array grants nothing.

**`#[Can]` and the `Gate` enforce abilities for you.** `TokenGuard` implements `Marko\Authentication\Contracts\AbilityScopedGuardInterface`, and when the [authorization](/docs/packages/authorization/#api-token-abilities) guard is a token guard, `Gate::allows('x')` (and so `#[Can('x')]`, `denies()` and `authorize()`) first requires the token to grant `x`. A token scoped to `['posts:read']` is denied `#[Can('posts:delete')]` with a `403`, even when the user's own gate closure or policy would allow it. Name your token abilities after your gate abilities and policy methods so the two line up.

For a route that is not behind `#[Can]`, check the ability yourself. `hasAbility()` lives on `TokenGuard`, so narrow the guard first:

```php
use Marko\AuthenticationToken\Guard\TokenGuard;

public function update(
    Request $request,
): Response {
    $guard = $this->authManager->guard('api');

    if (!$guard instanceof TokenGuard || !$guard->hasAbility('posts:write')) {
        return new Response('Forbidden', 403);
    }

    // proceed with update
}
```

`hasAbility()` returns `false` when the request carries no valid token.

### Revoking Tokens

Revoke a single token by its database ID:

```php
$this->tokenManager->revokeToken(
    tokenId: $token->id,
);
```

Revoke all tokens belonging to a user:

```php
$this->tokenManager->revokeAllTokens(
    user: $user,
);
```

## Events

The package dispatches token lifecycle [events](/docs/packages/events/) through core's `EventDispatcherInterface`. It never fires the session `LoginEvent`/`LogoutEvent`, since a token guard has no login. No event carries the token value, plain-text or hashed, so every event is safe to log.

| Event | Dispatched by | Properties |
|---|---|---|
| `TokenCreatedEvent` | `TokenManager::createToken()` | `user`, `tokenId`, `name`, `abilities`, `expiresAt` (`?DateTimeImmutable`: the stored expiry, including the configured default) |
| `TokenRevokedEvent` | `TokenManager::revokeToken()` | `tokenId` |
| `AllTokensRevokedEvent` | `TokenManager::revokeAllTokens()` | `user` |
| `TokenAuthenticationFailedEvent` | `TokenGuard`, when a request presents an unknown, revoked or expired token | `guard`, `reason` (`TokenFailureReason::Invalid` or `::Expired`), `tokenId` (expired tokens only), `ipAddress` |

`TokenAuthenticationFailedEvent` fires at most once per request and guard. A request without a bearer token and a successful authentication fire nothing: authenticating every API request is too noisy to be an event.

All events live in `Marko\AuthenticationToken\Event`.

```php title="NotifyNewTokenObserver.php"
use Marko\AuthenticationToken\Event\TokenCreatedEvent;
use Marko\Core\Attributes\Observer;

#[Observer(TokenCreatedEvent::class)]
class NotifyNewTokenObserver
{
    public function handle(
        TokenCreatedEvent $event,
    ): void {
        // Email $event->user: "A new API token '$event->name' was created"
    }
}
```

```php title="ThrottleBadTokensObserver.php"
use Marko\AuthenticationToken\Event\TokenAuthenticationFailedEvent;
use Marko\Core\Attributes\Observer;

#[Observer(TokenAuthenticationFailedEvent::class)]
class ThrottleBadTokensObserver
{
    public function handle(
        TokenAuthenticationFailedEvent $event,
    ): void {
        // Count failures per $event->ipAddress and block repeat offenders
    }
}
```

## Database

The package uses the `personal_access_tokens` table. Columns:

| Column | Type | Description |
|---|---|---|
| `id` | int | Primary key |
| `tokenable_type` | string | User class name |
| `tokenable_id` | int | User identifier |
| `name` | string | Human-readable token name |
| `token_hash` | string(64) | SHA-256 hash of the plain-text token |
| `abilities` | text | JSON-encoded array of ability strings |
| `last_used_at` | datetime | Last usage timestamp |
| `expires_at` | datetime | Expiry timestamp in the database timezone, set by `TokenManager` from `expiresAt` or `token_expiration_days` (null = never) |
| `created_at` | datetime | Creation timestamp, set by `TokenManager` from the clock |

## API Reference

### TokenManager

```php
use DateTimeInterface;
use Marko\Authentication\AuthenticatableInterface;
use Marko\AuthenticationToken\Config\TokenConfig;
use Marko\AuthenticationToken\Contracts\NewAccessToken;
use Marko\AuthenticationToken\Contracts\TokenRepositoryInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Psr\Clock\ClockInterface;

public function __construct(TokenRepositoryInterface $repository, TokenConfig $config, ClockInterface $clock, DatabaseTimezoneConfig $databaseTimezoneConfig, ?EventDispatcherInterface $eventDispatcher = null);
public function createToken(AuthenticatableInterface $user, string $name, array $abilities = [], ?DateTimeInterface $expiresAt = null): NewAccessToken;
public function revokeToken(int $tokenId): void;
public function revokeAllTokens(AuthenticatableInterface $user): void;
```

`createToken()` throws `ConfigException` for an invalid `token_expiration_days` and `ConfigNotFoundException` when the key is missing.

### TokenConfig

Reads `config/authentication-token.php`:

```php
public function __construct(ConfigRepositoryInterface $config);
public function expirationDays(): ?int; // positive int or null; anything else throws ConfigException
```

### TokenGuard

Implements `StatelessGuardInterface`, `AbilityScopedGuardInterface` and `ResettableInterface`. `AuthManager::reset()` resets it between worker requests, which forgets the token it resolved last:

```php
public function __construct(TokenRepositoryInterface $repository, CurrentRequest $currentRequest, ClockInterface $clock, DatabaseTimezoneConfig $databaseTimezoneConfig, UserProviderInterface $provider, string $name = 'token', ?EventDispatcherInterface $eventDispatcher = null);
public function check(): bool;
public function guest(): bool;
public function user(): ?AuthenticatableInterface;
public function id(): int|string|null;
public function hasAbility(string $ability): bool; // [] / NULL / '*' grant every ability; false without a valid token
public function extractToken(): ?string;
public function getName(): string;
public function getChallenge(): string; // 'Bearer'
public function attempt(array $credentials, bool $remember = false): bool; // throws StatelessGuardException
public function login(AuthenticatableInterface $user): void; // throws StatelessGuardException
public function loginById(int|string $id): ?AuthenticatableInterface; // throws StatelessGuardException
public function logout(): void; // throws StatelessGuardException
public function reset(): void;
```

### TokenGuardFactory

Builds the guard for the `token` driver:

```php
public function create(string $name, UserProviderInterface $provider): TokenGuard;
```

### CurrentRequest

Singleton holding the request being handled, set by `TokenRequestMiddleware`. Implements `ResettableInterface`:

```php
public function set(Request $request): void;
public function get(): ?Request;
public function reset(): void;
```

### NewAccessToken

```php
public readonly PersonalAccessToken $accessToken;
public readonly string $plainTextToken; // available once at creation only
```

### TokenRepositoryInterface

```php
public function find(int $id): ?PersonalAccessToken;
public function findByToken(string $tokenHash): ?PersonalAccessToken;
public function create(PersonalAccessToken $token): PersonalAccessToken;
public function revoke(int $id): void;
public function revokeAllForUser(string $type, int|string $id): void;
```

### HasApiTokensInterface

```php
public function getTokens(): array;
public function createToken(string $name, array $abilities = []): NewAccessToken;
```

### Exceptions

```php
StatelessGuardException::forMethod(string $guard, string $method): self;
```

`StatelessGuardException` extends `Marko\Authentication\Exceptions\AuthException`. An unknown, revoked or expired token never throws: the guard returns `null` and dispatches `TokenAuthenticationFailedEvent` (see [Events](#events)).

## Related Packages

- [marko/authentication](/docs/packages/authentication/) --- Core authentication framework with guards, user providers, middleware, and events.
