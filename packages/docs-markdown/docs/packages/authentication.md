---
title: marko/authentication
description: Session and token-based authentication — guards protect routes, events track activity, middleware controls access.
---

Session and token-based authentication — guards protect routes, events track activity, middleware controls access. The auth package ships `SessionGuard` for web applications and a guard driver registry for everything else: install [marko/authentication-token](/docs/packages/authentication-token/) for API tokens, or register your own driver. Configure multiple guards, implement custom user providers, and react to authentication events via observers.

## Installation

```bash
composer require marko/authentication
```

## Configuration

The package reads configuration from `config/authentication.php`:

```php title="config/authentication.php"
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
        'users' => [], // no 'class': the app's UserProviderInterface binding
    ],

    'password' => [
        'driver' => 'bcrypt',
        'bcrypt' => [
            'cost' => 12,
        ],
        // App-wide password rules for AuthRules::password()
        'min' => 8,              // at least this many characters (1 or more)
        'mixed_case' => false,   // an uppercase and a lowercase letter
        'letters' => false,      // at least one letter
        'numbers' => false,      // at least one number
        'symbols' => false,      // at least one symbol
    ],

    'remember' => [
        'lifetime' => 43200, // minutes (30 days)
        'cookie' => [
            'prefix' => 'remember_',
            'path' => '/',
            'domain' => '',
            'secure' => null, // null follows session.cookie.secure
            'http_only' => true,
            'same_site' => 'Lax',
        ],
    ],

    'throttle' => [
        'enabled' => true,
        'max_attempts' => 5,          // failures allowed within decay_seconds
        'decay_seconds' => 60,
        'lockout_seconds' => 60,       // first lockout; each further one doubles
        'max_lockout_seconds' => 3600,
    ],

    'password_reset' => [
        'provider' => 'users',      // the provider reset requests look users up in
        'expire_seconds' => 3600,   // how long a reset token stays valid
        'throttle_seconds' => 60,   // at most one reset link per user in this window
    ],

    'verification' => [
        'verify_route' => 'verification.verify', // the route NAME verification links are signed for
        'notice_route' => '/email/verify',        // where unverified browsers are redirected
        'expire_seconds' => 3600,                // how long a verification link stays valid
        'throttle_seconds' => 60,                // at most one verification link per user in this window
    ],

    'password_confirmation' => [
        'timeout_seconds' => 10800,       // a confirmation stays valid for 3 hours
        'route' => '/confirm-password',   // where unconfirmed browsers are redirected
        'throttle' => [
            'max_attempts' => 5,          // attempts per user within decay_seconds
            'decay_seconds' => 60,
        ],
    ],
];
```

Each guard's `driver` picks how it is built. `session` is built in. The shipped `token` guard needs [marko/authentication-token](/docs/packages/authentication-token/); until it is installed, resolving that guard throws an `AuthException` that tells you to install it. See [Guard Drivers](#guard-drivers).

Each guard's `provider` names an entry in `providers`. See [User Providers](#user-providers).

See [Remember Me](#remember-me) for what each `remember` option controls, [Login Throttling](#login-throttling) for `throttle`, [Password Reset](#password-reset) for `password_reset`, [Email Verification](#email-verification) for `verification`, [Password Confirmation](#password-confirmation) for `password_confirmation`, and [Validation Rules](#validation-rules) for the `password` rule keys.

### Password Hashing

`PasswordHasherInterface` is bound to `BcryptPasswordHasher`, using `password.bcrypt.cost`. When [marko/hashing](/docs/packages/hashing/) is installed, the binding switches to `HashManagerPasswordHasher` instead, and `config/hashing.php` (`HASH_DRIVER`, `BCRYPT_COST`, the Argon2id settings) governs login passwords; `authentication.password.driver` and `.bcrypt` are then ignored. The password rule keys (`min`, `mixed_case`, ...) always apply.

After a successful `attempt()`, `SessionGuard` calls `UserProviderInterface::rehashPasswordIfNeeded()`, which rehashes and saves the password when `needsRehash()` says the stored hash is out of date. Raising the bcrypt cost, or switching `HASH_DRIVER` from `bcrypt` to `argon2id`, therefore upgrades each account on its next login. Under any driver, existing bcrypt hashes keep verifying, with the same 72-byte and NUL-byte rejection as `BcryptPasswordHasher`.

## Usage

### Checking Authentication

Inject `AuthManager` to check if a user is authenticated:

```php title="DashboardController.php"
use Marko\Authentication\AuthManager;

class DashboardController
{
    public function __construct(
        private AuthManager $authManager,
    ) {}

    public function index(): Response
    {
        if ($this->authManager->check()) {
            $user = $this->authManager->user();
            return new Response("Welcome, user {$this->authManager->id()}");
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

    if ($this->authManager->attempt($credentials)) {
        return Response::redirect('/dashboard');
    }

    return new Response('Invalid credentials', 401);
}
```

`attempt()` is [throttled](#login-throttling): once a login identifier is locked out for a client, it throws `TooManyLoginAttemptsException` instead of returning `false`.

### Login Throttling

`SessionGuard::attempt()` throttles password guessing on every login path, so routes that call it need no extra middleware:

1. Before the user is looked up, it checks for a lockout and throws `TooManyLoginAttemptsException` if there is one, even when the password is right.
2. Every failed attempt counts, whether the user exists or the password is wrong. After `max_attempts` failures within `decay_seconds`, the identifier is locked out for `lockout_seconds`.
3. Each further lockout doubles, up to `max_lockout_seconds`. The lockout history lasts twice `max_lockout_seconds` from the first lockout.
4. A successful login clears the failures and the lockout history.

The throttle keys on the login identifier and the client IP together. The identifier is every non-password credential, trimmed and lower-cased, so `Admin@Example.com ` and `admin@example.com` share one counter. Because the client IP is part of the key, an attacker can only lock an account out for their own network, never for the real user. When [marko/ratelimiter](/docs/packages/ratelimiter/) is installed, the client IP comes from its `RateLimitKeyResolverInterface` binding, which honours `ratelimiter.trusted_proxies` and groups IPv6 clients by /64. Without it, the throttle uses `REMOTE_ADDR`. Outside an HTTP request (CLI, queue jobs), it keys on the identifier alone.

Counters live in the cache, so login throttling needs a cache driver such as [marko/cache-file](/docs/packages/cache-file/) or, across several servers, [marko/cache-redis](/docs/packages/cache-redis/). Without one, `attempt()` throws an `AuthException` that says which driver to install. Cache errors propagate, so the throttle fails closed. Set `authentication.throttle.enabled` to `false` to turn throttling off, for example when you throttle logins yourself from a [`FailedLoginEvent`](#failedloginevent) observer or at a reverse proxy.

`TooManyLoginAttemptsException` implements `HttpExceptionInterface`, so if you don't catch it, the routing pipeline renders it as `429 Too Many Requests` with a `Retry-After` header. Catch it to re-render your own login form:

```php
use Marko\Authentication\Exceptions\TooManyLoginAttemptsException;

try {
    $loggedIn = $this->authManager->attempt($credentials);
} catch (TooManyLoginAttemptsException $exception) {
    return $this->view->render('auth/login', [
        'error' => "Too many attempts. Try again in {$exception->getRetryAfter()} seconds.",
    ])->withStatus(429)->withHeaders($exception->getHeaders());
}
```

The throttle limits guesses against one account. To also cap how many accounts one IP can try, add [`#[RateLimit]`](/docs/packages/ratelimiter/) with `RateLimitMiddleware` to your login route.

### Login Challenges

A login challenge is a step a user must complete after their password checks out and before they are logged in, such as a two-factor code. [marko/authentication-two-factor](/docs/packages/authentication-two-factor/) is built on this hook, and passkey-first or other step-up flows can use it too.

List a session guard's challenges in its config, in the order they run:

```php title="config/authentication.php"
use Marko\AuthenticationTwoFactor\TwoFactorLoginChallenge;

return [
    'guards' => [
        'session' => [
            'driver' => 'session',
            'provider' => 'users',
            'challenges' => [TwoFactorLoginChallenge::class],
        ],
    ],
];
```

Each entry is a class implementing `LoginChallengeInterface`, resolved from the container. When the password is right, `attempt()` asks each challenge whether the user needs it (`requiresChallenge()`). If one does:

1. The user is **not** logged in. The guard stores the pending user ID and the challenge name in the session, under a fresh session ID, and `attempt()` returns `false`.
2. No `LoginEvent` fires and no remember-me cookie is issued yet.
3. Your challenge handler calls `completeChallenge()` once the user passes. The guard moves on to the next challenge that requires itself or, when none is left, logs the user in. Only then does `LoginEvent` fire, and only then can a remember cookie be issued (`completeChallenge(remember: true)`).

`SessionGuard` implements `ChallengeableGuardInterface`, so a login controller can tell a wrong password from a pending challenge:

```php
use Marko\Authentication\AuthManager;
use Marko\Authentication\Contracts\ChallengeableGuardInterface;

if ($this->authManager->attempt($credentials)) {
    return Response::redirect('/dashboard');
}

$guard = $this->authManager->guard();

if ($guard instanceof ChallengeableGuardInterface && $guard->hasPendingChallenge()) {
    return Response::redirect('/login/challenge');
}

return new Response('Invalid credentials', 401);
```

`cancelChallenge()` forgets the pending user (for a "cancel" link), and `logout()` clears a pending challenge too. Guards without `challenges` behave exactly as before.

Calling `login()` or `loginById()` directly bypasses challenges by design: app code that calls them has already decided the user is authenticated (for example right after registration). Challenges only apply to `attempt()`. A passkey sign-in through [marko/authentication-passkey](/docs/packages/authentication-passkey/) is already multi-factor, so it logs in directly, skips the guard's challenges, and cancels a challenge left pending by an abandoned password login. A guard built by a driver whose guards don't implement `ChallengeableGuardInterface` (such as the token guard) fails loudly if its config lists challenges.

### Logging Out

```php
public function logout(): Response
{
    $this->authManager->logout();

    return Response::redirect('/');
}
```

`logout()` removes the user from the session, clears the remember token (with [per-device tokens](#per-device-tokens), only the current device's), and regenerates the session ID (deleting the old session), so the logged-out session ID cannot be reused on a shared machine. Other session data (for example, another guard's login) is kept; call `$session->destroy()` yourself if you want to discard everything. With [marko/security](/docs/packages/security/) installed, the CSRF token is also rotated on every login and logout.

### Logging Out Other Devices

After a password change or [reset](#password-reset), sign the user out everywhere except the device they're using:

```php
$this->authManager->logoutOtherDevices($user);          // the default guard
$this->authManager->logoutOtherDevices($user, 'admin'); // a named guard
```

`SessionGuard` implements `OtherDeviceLogoutInterface`, and `AuthManager::logoutOtherDevices()` throws an `AuthException` for a guard that doesn't (stateless guards keep no sessions; revoke their tokens instead). When you hold a guard directly, check `$guard instanceof OtherDeviceLogoutInterface` first. The current session and remember-me cookie are kept when they belong to `$user`; it then dispatches [`OtherDevicesLoggedOutEvent`](#otherdevicesloggedoutevent).

**Remember-me devices** are always ended. With [per-device tokens](#per-device-tokens), every token the guard issued to the user is deleted except the current device's (`RememberTokenStorageInterface::deleteAllForUser()`). Without them, the user's single column token is rotated when the current device holds it (keeping its expiry), or cleared otherwise, so no other device's cookie matches it again.

**Sessions** are ended only when the user entity implements `HasSessionVersionInterface`. Session handlers have no per-user index, so the guard versions sessions instead: it copies the user's session version into the session at login (`auth_{guard}_session_version`) and logs out a session whose copy no longer matches, on that session's next request. `logoutOtherDevices()` stores a random new version on the user, saves it through the [user persister](#saving-changed-users) of the guard's provider, and moves the current session to it. This works with every session driver:

```php title="app/web/Entity/User.php"
use Marko\Authentication\HasSessionVersionInterface;
use Marko\Database\Attributes\Column;

class User implements HasSessionVersionInterface
{
    #[Column('session_version')]
    public string $sessionVersion = '';

    // ... AuthenticatableInterface methods

    public function getSessionVersion(): string
    {
        return $this->sessionVersion;
    }

    public function setSessionVersion(
        string $version,
    ): void {
        $this->sessionVersion = $version;
    }
}
```

An empty version means it was never changed, so sessions started before you added the column stay valid until other devices are first logged out. A user without the interface only has its remember-me devices ended: its other sessions stay logged in until they expire.

### Making Users Authenticatable

Your user model must implement `AuthenticatableInterface`:

```php title="User.php"
use DateTimeImmutable;
use Marko\Authentication\AuthenticatableInterface;

class User implements AuthenticatableInterface
{
    public function __construct(
        private int $id,
        private string $email,
        private string $password,
        private ?string $rememberToken = null,
        private ?DateTimeImmutable $rememberTokenExpiresAt = null,
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

    public function getRememberTokenExpiresAt(): ?DateTimeImmutable
    {
        return $this->rememberTokenExpiresAt;
    }

    public function setRememberTokenExpiresAt(
        ?DateTimeImmutable $expiresAt,
    ): void {
        $this->rememberTokenExpiresAt = $expiresAt;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
```

## Guards

Guards define how users are authenticated. `AuthManager::guard($name)` builds the guard configured under `authentication.guards.{name}` with its `driver`, and caches it for the rest of the request. Called without a name, it builds `authentication.default.guard`.

A guard name that isn't a key of `authentication.guards` throws an `AuthException` naming the guard and listing the configured ones. A misspelt `authentication.default.guard` (or a name passed to `guard()`) never falls back to a session guard. A guard entry without a `driver` throws too:

```php
$this->authManager->guard('admni');
// AuthException: Guard 'admni' is not defined in authentication.guards
// Context: AuthManager was asked for guard 'admni'. Configured guards: session, token
```

`GuardInterface` is bound to `AuthManager::guard()` with the default name, so a bad `authentication.default.guard` fails every consumer of `GuardInterface` the same way. When routes use `#[Can]`, the [authorization boot check](/docs/packages/authorization/#cost-on-routes-without-can) catches it before the first request.

### SessionGuard

The default guard for web applications. Stores user ID in the session and supports "remember me" functionality:

```php
// Use specific guard
$guard = $this->authManager->guard('session');

// Login with remember me
$guard->login($user, remember: true);

// Check via session
if ($guard->check()) {
    $user = $guard->user();
}
```

Resolve guards through `AuthManager` (or inject `GuardInterface`, which is bound to `AuthManager::guard()`). Guards built this way receive the event dispatcher, the cookie jar, and the remember token manager, so remember-me and [events](#events) work out of the box. If you construct a `SessionGuard` by hand without a cookie jar and token manager, `login($user, remember: true)` throws an `AuthException` instead of silently ignoring the flag.

`SessionGuard` also implements `Marko\Core\Contracts\ResettableInterface`. In a long-running worker (e.g. Swoole, RoadRunner), call `reset()` between requests to clear the guard's cached user so one request's authenticated user is never served to the next (`marko/roadrunner` does this automatically for every resolved `ResettableInterface` service):

```php
use Marko\Core\Contracts\ResettableInterface;

if ($guard instanceof ResettableInterface) {
    $guard->reset();
}
```

`reset()` only clears the cached user --- it does not call `logout()` or otherwise touch the session. The next call to `user()` re-reads the authenticated user from the session as normal.

### Token Guards

API token authentication lives in [marko/authentication-token](/docs/packages/authentication-token/). Installing it registers the `token` driver, so the `token` guard in the default config (or any guard with `'driver' => 'token'`) resolves to `Marko\AuthenticationToken\Guard\TokenGuard`:

```php
$guard = $this->authManager->guard('token');

// Authorization: Bearer your-api-token
if ($guard->check()) {
    $user = $guard->user();
}
```

### Stateless Guards

A guard that authenticates each request from credentials the request carries implements `Marko\Authentication\Contracts\StatelessGuardInterface`. It adds one method to `GuardInterface`, `getChallenge()`, which returns the `WWW-Authenticate` challenge (for example `Bearer`). Neither `AuthMiddleware` nor [`AdminAuthMiddleware`](/docs/packages/admin-auth/#protecting-admin-routes) redirects a guest on a stateless guard. Every `401` the framework sends for a guest (from `AuthMiddleware`, from `#[Can]` in [marko/authorization](/docs/packages/authorization/#failure-responses), and from `AdminAuthMiddleware`) carries the guard's challenge, because each one is built by `UnauthenticatedException::forGuard()`. Throw the same exception from your own middleware so its `401` looks identical:

```php title="ApiKeyMiddleware.php"
use Marko\Authentication\Exceptions\UnauthenticatedException;

if (!$guard->check()) {
    throw UnauthenticatedException::forGuard($guard); // 401, plus WWW-Authenticate for a stateless guard
}
```

A stateless guard's `attempt()`, `login()`, `loginById()` and `logout()` must throw and explain what to do instead, never silently do nothing.

### Guard Drivers

`AuthManager` builds guards through `Marko\Authentication\Guard\GuardDriverRegistry`, a singleton that maps driver names to factories. Every guard entry must set `driver`; a missing, empty or non-string `driver` throws an `AuthException` naming the guard (there is no implicit `session` default). It checks the registry first, then the built-in `session` driver. Any other driver name throws an `AuthException` that lists the drivers available.

Register a driver from your module's `boot` callback. The factory receives the guard name, that guard's config array and the user provider, and must return a guard whose `getName()` is the guard name (`AuthManager` throws otherwise). Resolve heavy dependencies inside the factory so booting stays cheap:

```php title="module.php"
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Guard\GuardDriverRegistry;
use Marko\Core\Container\ContainerInterface;

return [
    'boot' => function (GuardDriverRegistry $guardDriverRegistry, ContainerInterface $container): void {
        $guardDriverRegistry->extend(
            'jwt',
            fn (string $name, array $config, UserProviderInterface $provider): GuardInterface => new JwtGuard(
                decoder: $container->get(JwtDecoder::class),
                provider: $provider,
                name: $name,
                header: $config['header'] ?? 'Authorization',
            ),
        );
    },
];
```

```php title="config/authentication.php"
'guards' => [
    'partner-api' => ['driver' => 'jwt', 'provider' => 'users', 'header' => 'X-Partner-Token'],
],
```

Registering a driver name again replaces the earlier factory, so a later module (your app, for example) can override `token` or even `session`.

### Custom Guards

A guard whose credentials can be scoped to a subset of what the user may do (an API token issued with abilities, for example) implements `Marko\Authentication\Contracts\AbilityScopedGuardInterface`, which adds `hasAbility(string $ability): bool`. The [authorization Gate](/docs/packages/authorization/#api-token-abilities) denies an authenticated user any ability the guard's `hasAbility()` rejects, so `#[Can]` respects token scopes.

Implement `GuardInterface` to create custom guards, then register them as a driver. A stateless guard implements `StatelessGuardInterface` instead and throws from the stateful methods:

```php title="JwtGuard.php"
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\StatelessGuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Exceptions\AuthException;

class JwtGuard implements StatelessGuardInterface
{
    public function __construct(
        private JwtDecoder $decoder,
        public UserProviderInterface $provider {
            set {
                $this->provider = $value;
            }
        },
        private string $name = 'jwt',
        private string $header = 'Authorization',
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
        // Decode JWT and retrieve user
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function attempt(
        array $credentials,
    ): bool {
        throw new AuthException(
            message: "Cannot call attempt() on JWT guard '$this->name': it is stateless",
            suggestion: 'Issue a JWT from your login endpoint instead',
        );
    }

    // login(), loginById() and logout() throw the same way

    public function getName(): string
    {
        return $this->name;
    }

    public function getChallenge(): string
    {
        return 'Bearer';
    }
}
```

## User Providers

A user provider (`UserProviderInterface`) loads users for a guard. Each guard gets its own provider, so one application can authenticate different kinds of users, for example customers on the frontend and admins in the admin panel, without one store standing in for the other.

`AuthManager` picks a guard's provider with `UserProviderResolver`:

1. The guard's `provider` key names an entry in `authentication.providers`. A guard without one uses `authentication.default.provider`.
2. The entry's `class` key names a `UserProviderInterface` implementation, resolved from the container.
3. A guard with no provider name, a provider entry without a `class`, or an application with no `authentication.providers` at all uses the container's `UserProviderInterface` binding.

A single-provider application therefore needs nothing more than the binding:

```php title="app/web/module.php"
use App\Web\Auth\CustomerProvider;
use Marko\Authentication\Contracts\UserProviderInterface;

return [
    'bindings' => [
        UserProviderInterface::class => CustomerProvider::class,
    ],
];
```

Give a guard its own provider with a `class`:

```php title="config/authentication.php"
return [
    'guards' => [
        'session' => ['driver' => 'session', 'provider' => 'users'],
        'partner' => ['driver' => 'session', 'provider' => 'partners'],
    ],
    'providers' => [
        'users' => [],
        'partners' => ['class' => App\Partner\Auth\PartnerProvider::class],
    ],
];
```

Each provider is built once and shared by every guard that names it. A guard naming a provider missing from `authentication.providers`, or a `class` that does not implement `UserProviderInterface`, throws an `AuthException`:

```php
// AuthException: User provider 'partners' is not defined in authentication.providers
// Context: Guard 'partner' uses provider 'partners'. Configured providers: users
```

Every session guard keeps its user in its own session key, `auth_{guard}_user_id`, and its own remember-me cookie, `remember_{guard}`. Logging in on one guard never authenticates another. [marko/admin-auth](/docs/packages/admin-auth/#the-admin-guard) uses this to ship a separate `admin` guard backed by `AdminUserProvider`.

### Saving Changed Users

Features that change a user, such as [password reset](#password-reset), save it through `UserPersisterInterface`. The user provider only reads users; the persister writes them:

```php title="app/web/Auth/UserPersister.php"
use App\Web\Entity\User;
use App\Web\Repository\UserRepository;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\UserPersisterInterface;

class UserPersister implements UserPersisterInterface
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function persist(
        AuthenticatableInterface $user,
    ): void {
        /** @var User $user */
        $this->userRepository->save($user);
    }
}
```

Bind it once for every provider, or give one provider its own with a `persister` key, the same way `class` picks the provider:

```php title="config/authentication.php"
return [
    'providers' => [
        'users' => [], // the app's UserPersisterInterface binding
        'partners' => [
            'class' => App\Partner\Auth\PartnerProvider::class,
            'persister' => App\Partner\Auth\PartnerPersister::class,
        ],
    ],
];
```

`UserPersisterResolver::forProvider()` picks it. With no `persister` key and no `UserPersisterInterface` binding, it throws a `UserPersisterException` naming the provider instead of dropping the change:

```php
// UserPersisterException: No user persister is configured for user provider 'users'
```

A feature changes the user through a small interface the entity implements, such as `CanResetPasswordInterface`. `UserPersisterResolver::assertUserSupports()` throws a `UserPersisterException` naming the provider, the user class and the missing interface when the entity doesn't implement it.

## Remember Me

Pass `remember: true` when logging a user in to keep them signed in after their session expires:

```php title="LoginController.php"
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

class LoginController
{
    public function __construct(
        private GuardInterface $guard,
        private UserProviderInterface $userProvider,
    ) {}

    public function login(
        Request $request,
    ): Response {
        $credentials = [
            'email' => $request->post('email'),
            'password' => $request->post('password'),
        ];
        $user = $this->userProvider->retrieveByCredentials($credentials);

        if ($user === null || !$this->userProvider->validateCredentials($user, $credentials)) {
            return new Response('Invalid credentials', 401);
        }

        $this->guard->login($user, remember: (bool) $request->post('remember'));

        return Response::redirect('/dashboard');
    }
}
```

### Per-Device Tokens

When a `RememberTokenStorageInterface` implementation is bound, every session guard issues **one token per device** instead of a single token on the user. [marko/admin-auth](/docs/packages/admin-auth/#remember-me-tokens) binds one backed by a `remember_tokens` table, which serves the admin guard and your app's own session guards alike.

- `login($user, remember: true)` stores a new row (guard, user ID, a random selector, the SHA-256 hash of a random validator, the expiry, and the browser's `User-Agent`) and sends a `remember_{guard}` cookie holding `{selector}:{validator}`.
- A later request looks the row up by guard and selector and compares the validator's hash with `hash_equals()`. An unknown selector clears the cookie; a wrong validator (a tampered cookie) is rejected and the row is left alone.
- On success only the validator is rotated, as a compare-and-swap on the stored hash: of two requests racing with the same cookie, one logs in and the other is treated as a guest. The selector and the expiry are kept, so the [lifetime is fixed](#how-it-works) from the original login.
- An expired row, or one whose user no longer exists, is deleted along with the cookie.
- `logout()` deletes only the current device's row. The user's other devices stay remembered, and a remember-me login on one device never rotates another device's token. To end them too, use [`logoutOtherDevices()`](#logging-out-other-devices).

Without a binding, guards fall back to the single-column token described below. A cookie issued by that path (`{user id}|{token}`) is still honored after you install per-device storage: on its next use the column token is cleared and the device moves to its own row, so remembered users are not logged out by the upgrade.

#### Clearing Expired Tokens

Expired rows are deleted when they are presented, but rows from devices that never return remain. Purge them on a schedule:

```bash
marko auth:clear-tokens          # delete expired remember tokens
marko auth:clear-tokens --force  # delete every remember token (logs every remembered device out)
```

The command calls `RememberTokenStorageInterface::clearExpiredTokens()` (or `clearAllTokens()` with `--force`), so it needs a storage binding such as marko/admin-auth's.

### How It Works

These steps describe the single-column fallback, used when no `RememberTokenStorageInterface` is bound:

1. `login($user, remember: true)` generates a random token, stores its SHA-256 hash and an expiry (`remember.lifetime` minutes from now) through `UserProviderInterface::updateRememberToken()`, and queues a `remember_{guard}` cookie (e.g. `remember_session`) holding `{user id}|{plain token}`.
2. `QueuedCookiesMiddleware` attaches the queued cookie to the response with `Response::withCookie()`. The package registers this middleware as global middleware and orders it after the session driver modules, so there is nothing to wire up. Cookies never go through `setcookie()`, so behavior is identical under PHP-FPM and RoadRunner.
3. On a later request with no authenticated session, `user()` reads the cookie, calls `retrieveByRememberToken($id, $hash)` with the **hash** of the cookie's token, and verifies it against `getRememberToken()` with a constant-time comparison.
4. The stored expiry is enforced server-side: the cookie's own `Expires` is controlled by the client, so a token whose `getRememberTokenExpiresAt()` has passed (or is `null`) is rejected, and the stored token and cookie are cleared.
5. A valid cookie logs the user in exactly like a password login: the user ID is written to the session, the session ID is regenerated, and `LoginEvent` is dispatched with `remember: true`. Later requests authenticate from the session, so the cookie is consumed once per session. The token is rotated (a new cookie is sent) to prevent replay, keeping its original expiry: a remember-me login lasts at most `remember.lifetime` however often it is used.
6. `logout()` clears the stored token (`updateRememberToken($user, null, null)`) and sends an expired remember cookie.

### User Provider Requirements

Remember-me stores tokens through your user provider, so the provider and user must persist them:

- `updateRememberToken()` must call `$user->setRememberToken($token)` and `$user->setRememberTokenExpiresAt($expiresAt)` and save both (typically nullable `remember_token` and `remember_token_expires_at` columns). If the user's `getRememberToken()` and `getRememberTokenExpiresAt()` do not return the new hash and expiry afterwards, `login(..., remember: true)` throws an `AuthException` rather than issuing a cookie that can never be honored.
- `retrieveByRememberToken()` receives the stored hash. Compare it to the stored value with `hash_equals()`.

```php title="UserProvider.php"
public function retrieveByRememberToken(
    int|string $identifier,
    string $token,
): ?AuthenticatableInterface {
    $user = $this->userRepository->find((int) $identifier);
    $storedToken = $user?->getRememberToken();

    if ($storedToken === null || !hash_equals($storedToken, $token)) {
        return null;
    }

    return $user;
}

public function updateRememberToken(
    AuthenticatableInterface $user,
    ?string $token,
    ?DateTimeImmutable $expiresAt,
): void {
    $user->setRememberToken($token);
    $user->setRememberTokenExpiresAt($expiresAt);
    $this->userRepository->save($user);
}
```

### Cookie Configuration

All remember-me options live under `remember` in `config/authentication.php`:

| Key | Default | Description |
|-----|---------|-------------|
| `lifetime` | `43200` | Cookie and token lifetime in minutes (30 days), enforced server-side from the stored expiry |
| `cookie.prefix` | `'remember_'` | Cookie name prefix; the guard name is appended (`remember_session`) |
| `cookie.path` | `'/'` | Cookie `Path` attribute |
| `cookie.domain` | `''` | Cookie `Domain` attribute; an empty string omits it |
| `cookie.secure` | `null` | Cookie `Secure` flag; `null` follows `session.cookie.secure` |
| `cookie.http_only` | `true` | Cookie `HttpOnly` flag |
| `cookie.same_site` | `'Lax'` | Cookie `SameSite` attribute (`Lax`, `Strict` or `None`; `None` requires `secure`) |

### Cookie Jar

The guard reads and writes cookies through `CookieJarInterface`, which is bound as a singleton to `Marko\Authentication\Cookie\RequestCookieJar`. The jar reads from the current request and queues writes until `QueuedCookiesMiddleware` attaches them to the response. Writing a cookie when no HTTP request is being handled (for example, `login(..., remember: true)` from a CLI command) throws an `AuthException`. The jar implements `ResettableInterface`, so long-running workers clear its request and queue between requests.

### Time and Testing

Remember-token expiry (`RememberTokenManager`) and remember-cookie expiry (`RequestCookieJar`) read the current time through the PSR-20 `Psr\Clock\ClockInterface` from [`marko/clock`](/docs/packages/clock/), never `time()`. Both take the clock as a constructor parameter and the container injects it. In tests, pass a [`FakeClock`](/docs/packages/testing/#fakeclock) to check the lifetime boundary exactly, without sleeping:

```php
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Testing\Fake\FakeClock;

$clock = new FakeClock('2026-01-01 12:00:00 UTC');
$manager = new RememberTokenManager($clock, lifetimeMinutes: 60);
$expiresAt = $manager->expiresAt(); // 13:00:00

$clock->travel('+59 minutes');
$manager->hasExpired($expiresAt); // false

$clock->travel('+1 minute');
$manager->hasExpired($expiresAt); // true
```

## Password Reset

`PasswordResetBrokerInterface` runs the security-sensitive half of "forgot password": it issues, stores, checks and expires reset tokens. Your app supplies the routes, the forms, the email, and the code that saves a user.

The broker:

- creates a token from 64 random bytes, sends it to the user in hex, and stores only its SHA-256 hash with the time it was created;
- checks a submitted token with `hash_equals()`, rejects it once it is `expire_seconds` old, and deletes it when it is used, so each token works once;
- returns `ResetLinkStatus::Sent` whether or not the email is registered, and never calls the notifier for an unknown email;
- throttles link requests to one per `throttle_seconds` through [marko/ratelimiter](/docs/packages/ratelimiter/)'s `RateLimiterInterface`. A request for a known user is throttled per user, one for an unknown email per credentials, so `Throttled` doesn't reveal which emails exist either;
- hashes the new password with `PasswordHasherInterface`, saves the user through [`UserPersisterInterface`](#saving-changed-users), and dispatches [`PasswordResetEvent`](#passwordresetevent).

Tokens are keyed by the user's auth identifier, not their email, so changing an email can't orphan or redirect a token.

### Setting Up Password Reset

1. Implement `CanResetPasswordInterface` on your user entity. It extends `AuthenticatableInterface` with `setAuthPassword()` and `getEmailForPasswordReset()`.
2. Bind a [`UserPersisterInterface`](#saving-changed-users) that saves the entity.
3. Bind a `ResetLinkNotifierInterface` that delivers the link.
4. Install a persistent cache driver, such as [marko/cache-file](/docs/packages/cache-file/) or [marko/cache-redis](/docs/packages/cache-redis/). Both the default token repository, `CachePasswordResetTokenRepository`, and the throttle keep their data in the cache. To keep reset tokens in a database table instead, so they survive cache flushes, install [marko/authentication-database](/docs/packages/authentication-database/).

```php title="app/web/Entity/User.php"
use Marko\Authentication\CanResetPasswordInterface;
use Marko\Notification\Contracts\NotifiableInterface;

class User implements CanResetPasswordInterface, NotifiableInterface
{
    // ...the AuthenticatableInterface methods (see Making Users Authenticatable)

    public function setAuthPassword(
        string $hash,
    ): void {
        $this->password = $hash;
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }

    public function routeNotificationFor(
        string $channel,
    ): mixed {
        return $this->email;
    }

    public function getNotifiableId(): string|int
    {
        return $this->id;
    }

    public function getNotifiableType(): string
    {
        return self::class;
    }
}
```

### Sending the Reset Link

The broker never sends email itself. It hands the user and the plain token to your `ResetLinkNotifierInterface`, so you own the message and the URL. This one uses [marko/notification](/docs/packages/notification/) and queues the email: an unknown email returns at once, so sending inline would let the response time reveal which emails are registered.

```php title="app/web/Auth/ResetPasswordNotification.php"
use Marko\Mail\Message;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;

class ResetPasswordNotification implements NotificationInterface
{
    public function __construct(
        private string $url,
    ) {}

    public function channels(
        NotifiableInterface $notifiable,
    ): array {
        return ['mail'];
    }

    public function toMail(
        NotifiableInterface $notifiable,
    ): Message {
        return Message::create()
            ->subject('Reset your password')
            ->html("<p><a href=\"$this->url\">Choose a new password</a>. The link expires in an hour.</p>");
    }

    public function toDatabase(
        NotifiableInterface $notifiable,
    ): array {
        return [];
    }
}
```

```php title="app/web/Auth/QueuedResetLinkNotifier.php"
use App\Web\Entity\User;
use Marko\Authentication\CanResetPasswordInterface;
use Marko\Authentication\Contracts\ResetLinkNotifierInterface;
use Marko\Notification\Contracts\NotificationSenderInterface;

class QueuedResetLinkNotifier implements ResetLinkNotifierInterface
{
    public function __construct(
        private NotificationSenderInterface $notificationSender,
    ) {}

    public function send(
        CanResetPasswordInterface $user,
        string $token,
    ): void {
        $url = 'https://example.com/reset-password?' . http_build_query([
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]);

        /** @var User $user */
        $this->notificationSender->queue($user, new ResetPasswordNotification($url));
    }
}
```

```php title="app/web/module.php"
use App\Web\Auth\QueuedResetLinkNotifier;
use App\Web\Auth\UserPersister;
use Marko\Authentication\Contracts\ResetLinkNotifierInterface;
use Marko\Authentication\Contracts\UserPersisterInterface;

return [
    'bindings' => [
        UserPersisterInterface::class => UserPersister::class,
        ResetLinkNotifierInterface::class => QueuedResetLinkNotifier::class,
    ],
];
```

### Handling the Requests

```php title="app/web/Controller/PasswordResetController.php"
use Marko\Authentication\Contracts\PasswordResetBrokerInterface;
use Marko\Authentication\Middleware\GuestMiddleware;
use Marko\Authentication\PasswordReset\ResetLinkStatus;
use Marko\Authentication\PasswordReset\ResetStatus;
use Marko\Authentication\Validation\AuthRules;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Validation\Contracts\ValidatorInterface;

#[Middleware(GuestMiddleware::class)]
class PasswordResetController
{
    public function __construct(
        private PasswordResetBrokerInterface $passwordResetBroker,
        private ValidatorInterface $validator,
        private AuthRules $authRules,
    ) {}

    #[Post('/forgot-password')]
    public function sendLink(
        Request $request,
    ): Response {
        $status = $this->passwordResetBroker->sendResetLink([
            'email' => (string) $request->post('email'),
        ]);

        return match ($status) {
            ResetLinkStatus::Sent => Response::redirect('/forgot-password?sent=1'),
            ResetLinkStatus::Throttled => Response::redirect('/forgot-password?throttled=1'),
        };
    }

    #[Post('/reset-password')]
    public function reset(
        Request $request,
    ): Response {
        // The same password policy as registration and password change
        $this->validator->validateOrFail($request->post(), [
            'password' => ['required', 'confirmed', $this->authRules->password()],
        ]);

        $status = $this->passwordResetBroker->reset(
            ['email' => (string) $request->post('email')],
            (string) $request->post('token'),
            (string) $request->post('password'),
        );

        return match ($status) {
            ResetStatus::PasswordReset => Response::redirect('/login?reset=1'),
            ResetStatus::InvalidToken, ResetStatus::InvalidUser => Response::redirect('/forgot-password?invalid=1'),
        };
    }
}
```

The broker stores whatever password it is given, so validate it first. `AuthRules::password()` applies the app-wide [password rules](#password-strength).

The credentials array goes straight to `UserProviderInterface::retrieveByCredentials()` on the provider named by `password_reset.provider`, so use whatever keys your provider looks users up by.

### Clearing Expired Reset Tokens

`CachePasswordResetTokenRepository` stores each token with `expire_seconds` as its cache lifetime, so the cache drops expired tokens by itself. A repository that keeps tokens elsewhere, such as the `password_reset_tokens` table of [marko/authentication-database](/docs/packages/authentication-database/), needs the cleanup command run on a schedule:

```bash
marko auth:clear-reset-tokens
```

It calls `PasswordResetTokenRepositoryInterface::deleteExpired()` with the current time minus `expire_seconds`. Installing marko/authentication-database swaps the cache repository for its database one. To store tokens anywhere else, bind your own `PasswordResetTokenRepositoryInterface` in an app module. It only stores data: expiry, hash comparison and single use stay in the broker, so every storage behaves the same.

### Misconfiguration

Resolving the persister happens on every request, before the user is looked up, so a missing one fails the same way for registered and unknown emails:

```php
// UserPersisterException: No user persister is configured for user provider 'users'
// UserPersisterException: User class 'App\Web\Entity\User' from user provider 'users' does not implement Marko\Authentication\CanResetPasswordInterface
```

`expire_seconds` or `throttle_seconds` below `1` throws an `AuthException` when the broker reads it.

## Email Verification

Email verification lets a route require a verified email address. `EmailVerifierInterface` (bound to `EmailVerifier`) sends and checks verification links, and `EnsureEmailVerifiedMiddleware` keeps unverified users out. Your app supplies the routes, the pages and the email.

`EmailVerifier` doesn't add any crypto or storage of its own. It reuses:

- [routing](/docs/packages/routing/)'s `UrlSignerInterface` to sign links, so `routing.signing.key` must be set;
- [`UserPersisterInterface`](#saving-changed-users) to save the verified user;
- [marko/ratelimiter](/docs/packages/ratelimiter/)'s `RateLimiterInterface` to throttle resends, which needs a cache driver.

`sendVerificationLink($user)` signs a link to the route named `verification.verify_route`. The link expires after `verification.expire_seconds` and carries two query parameters:

- `id`: the user's auth identifier;
- `hash`: the SHA-256 hash of `getEmailForVerification()`, so links sent before an email change stop working.

Each user gets at most one link every `verification.throttle_seconds`. The method returns a `VerificationLinkStatus`:

- `Sent`;
- `Throttled`, when a link was sent too recently and nothing was sent;
- `AlreadyVerified`, when the user is already verified and nothing was sent.

`verify(Request $request)` acts on the logged-in user of the default guard, so put the verify route behind `AuthMiddleware`. In order, it:

1. checks the URL signature with `UrlSignerInterface::assertValidSignature()`;
2. compares `id` and `hash` with the user using `hash_equals()`;
3. calls `markEmailAsVerified()` with the time from `ClockInterface`;
4. saves the user through the persister of the guard's user provider;
5. dispatches [`EmailVerifiedEvent`](#emailverifiedevent).

It returns `VerificationStatus::Verified`. A link that fails any check throws routing's `InvalidSignatureException`, which renders as a 403. That covers a tampered or expired link (`isExpired()` tells the two apart), a link sent to another user, and a link sent before the email changed. Verifying a user who is already verified returns `VerificationStatus::AlreadyVerified` without saving or dispatching anything.

### Setting Up Email Verification

1. Implement `MustVerifyEmailInterface` on your user entity. It extends `AuthenticatableInterface` with `hasVerifiedEmail()`, `markEmailAsVerified()` and `getEmailForVerification()`. A nullable `email_verified_at` column is the usual backing.
2. Bind a [`UserPersisterInterface`](#saving-changed-users) that saves the entity.
3. Bind a `VerificationNotifierInterface` that delivers the link. No default ships, so resolving `EmailVerifierInterface` without one fails loudly.
4. Set `routing.signing.key` (`URL_SIGNING_KEY`, at least 32 bytes), and install a cache driver for the throttle.

```php title="app/web/Entity/User.php"
use DateTimeImmutable;
use Marko\Authentication\MustVerifyEmailInterface;
use Marko\Notification\Contracts\NotifiableInterface;

class User implements MustVerifyEmailInterface, NotifiableInterface
{
    public ?DateTimeImmutable $emailVerifiedAt = null;

    // ...the AuthenticatableInterface and NotifiableInterface methods

    public function hasVerifiedEmail(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function markEmailAsVerified(
        DateTimeImmutable $verifiedAt,
    ): void {
        $this->emailVerifiedAt = $verifiedAt;
    }

    public function getEmailForVerification(): string
    {
        return $this->email;
    }
}
```

### Sending the Verification Link

`EmailVerifier` hands the user and the finished signed URL to your `VerificationNotifierInterface`, so you own the message. This one queues the email through [marko/notification](/docs/packages/notification/):

```php title="app/web/Auth/VerifyEmailNotification.php"
use Marko\Mail\Message;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;

class VerifyEmailNotification implements NotificationInterface
{
    public function __construct(
        private string $url,
    ) {}

    public function channels(
        NotifiableInterface $notifiable,
    ): array {
        return ['mail'];
    }

    public function toMail(
        NotifiableInterface $notifiable,
    ): Message {
        return Message::create()
            ->subject('Verify your email address')
            ->html("<p><a href=\"$this->url\">Verify your email address</a>. The link expires in an hour.</p>");
    }

    public function toDatabase(
        NotifiableInterface $notifiable,
    ): array {
        return [];
    }
}
```

```php title="app/web/Auth/QueuedVerificationNotifier.php"
use App\Web\Entity\User;
use Marko\Authentication\Contracts\VerificationNotifierInterface;
use Marko\Authentication\MustVerifyEmailInterface;
use Marko\Notification\Contracts\NotificationSenderInterface;

class QueuedVerificationNotifier implements VerificationNotifierInterface
{
    public function __construct(
        private NotificationSenderInterface $notificationSender,
    ) {}

    public function send(
        MustVerifyEmailInterface $user,
        string $url,
    ): void {
        /** @var User $user */
        $this->notificationSender->queue($user, new VerifyEmailNotification($url));
    }
}
```

```php title="app/web/module.php"
use App\Web\Auth\QueuedVerificationNotifier;
use App\Web\Auth\UserPersister;
use Marko\Authentication\Contracts\UserPersisterInterface;
use Marko\Authentication\Contracts\VerificationNotifierInterface;

return [
    'bindings' => [
        UserPersisterInterface::class => UserPersister::class,
        VerificationNotifierInterface::class => QueuedVerificationNotifier::class,
    ],
];
```

Send the first link right after registration, e.g. from your registration controller or an observer on your own "user registered" event:

```php
$this->emailVerifier->sendVerificationLink($user);
```

### Handling the Verification Requests

The verify route must have the name in `verification.verify_route`. Its path must not have `{id}` or `{hash}` placeholders, because `verify()` reads both from the query string. The notice page, at `verification.notice_route`, tells the user to check their inbox and offers a resend button:

```php title="app/web/Controller/EmailVerificationController.php"
use Marko\Authentication\Contracts\EmailVerifierInterface;
use Marko\Authentication\EmailVerification\CurrentVerifiableUser;
use Marko\Authentication\EmailVerification\VerificationLinkStatus;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Middleware(AuthMiddleware::class)]
class EmailVerificationController
{
    public function __construct(
        private EmailVerifierInterface $emailVerifier,
        private CurrentVerifiableUser $currentUser,
    ) {}

    #[Get('/email/verify')]
    public function notice(): Response
    {
        // Render "Check your inbox" with a resend form posting to /email/verification-notification
    }

    #[Post('/email/verification-notification')]
    public function resend(): Response
    {
        $status = $this->emailVerifier->sendVerificationLink($this->currentUser->get());

        return match ($status) {
            VerificationLinkStatus::Sent => Response::redirect('/email/verify?sent=1'),
            VerificationLinkStatus::Throttled => Response::redirect('/email/verify?throttled=1'),
            VerificationLinkStatus::AlreadyVerified => Response::redirect('/dashboard'),
        };
    }

    #[Get('/email/verify/confirm', name: 'verification.verify')]
    public function verify(
        Request $request,
    ): Response {
        // Verified or AlreadyVerified; a bad link throws InvalidSignatureException (403)
        $this->emailVerifier->verify($request);

        return Response::redirect('/dashboard?verified=1');
    }
}
```

`CurrentVerifiableUser::get()` returns the default guard's logged-in user as a `MustVerifyEmailInterface`. To show your own page for a bad link, catch `InvalidSignatureException` and check `isExpired()`.

### Requiring a Verified Email

Add `EnsureEmailVerifiedMiddleware` after `AuthMiddleware`:

```php title="app/web/Controller/DashboardController.php"
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Authentication\Middleware\EnsureEmailVerifiedMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;

class DashboardController
{
    #[Get('/dashboard')]
    #[Middleware([AuthMiddleware::class, EnsureEmailVerifiedMiddleware::class])]
    public function index(): Response
    {
        // Only users with a verified email reach here
    }
}
```

For a user who isn't verified:

- a browser request is redirected to `verification.notice_route`;
- a request that wants JSON gets a `403` `EmailNotVerifiedException`: `{"message": "Your email address is not verified."}`.

Misconfiguration is never let through quietly:

- a logged-in user whose class doesn't implement `MustVerifyEmailInterface` throws an `AuthException`;
- a guest throws `UnauthenticatedException`;
- `expire_seconds` or `throttle_seconds` below `1` throws an `AuthException` when it is read.

## Password Confirmation

Sensitive pages, such as two-factor setup or account deletion, can ask a signed-in user to re-enter their password when they haven't done so recently. Three interfaces make this up, each bound by default:

- `PasswordVerifierInterface` (`PasswordVerifier`) is the framework's one password check. `verify(?AuthenticatableInterface $user, string $password): bool` compares the password with the user's hash through `PasswordHasherInterface` and does nothing else. With a `null` user it still runs `verifyDummy()` and returns `false`, so timing doesn't reveal whether the user exists. Use it for any "is this the user's password?" check instead of calling the hasher yourself.
- `PasswordConfirmationInterface` (`PasswordConfirmation`) keeps the proof. `confirm($user, $password)` verifies the password and, only when it matches, stamps the session key `auth.password_confirmed_at` with the time from `ClockInterface`. `isRecentlyConfirmed()` is true while that stamp is younger than `timeout_seconds`. `markConfirmed()` sets the stamp without a password, for other proofs of presence such as a passkey.
- `IntendedUrlInterface` (`IntendedUrl`) remembers where the user was going. `remember(Request $request)` stores the request's path and query string in the session; `pull(string $default)` returns it once, or `$default`.

`confirm()` is throttled per user through [marko/ratelimiter](/docs/packages/ratelimiter/)'s `RateLimiterInterface`: after `throttle.max_attempts` attempts within `throttle.decay_seconds`, it throws `TooManyPasswordConfirmationAttemptsException` (429 with `Retry-After`). A correct password resets the count. The counters live in the cache, so a cache driver is required.

The stamp is removed on every `LoginEvent` and `LogoutEvent`, so a confirmation never carries over to whoever signs in next on the same session.

### Protecting Routes

Add `RequirePasswordConfirmationMiddleware` after `AuthMiddleware`:

```php title="app/web/Controller/AccountController.php"
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Authentication\Middleware\RequirePasswordConfirmationMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;

class AccountController
{
    #[Get('/account/delete')]
    #[Middleware([AuthMiddleware::class, RequirePasswordConfirmationMiddleware::class])]
    public function confirmDeletion(): Response
    {
        // Only users who confirmed their password in the last timeout_seconds reach here
    }
}
```

When the session isn't recently confirmed:

- a browser request is redirected to `password_confirmation.route`, with its URL remembered through `IntendedUrlInterface` (only GET and HEAD URLs are remembered, since a redirect can only revisit those);
- a request that wants JSON gets a `423` `PasswordConfirmationRequiredException`: `{"message": "Password confirmation required."}`.

`#[Middleware]` takes only class names, so the timeout and the route are global config, not per-route arguments.

The middleware needs a session guard. When the default guard is stateless, such as the token guard, it throws an `AuthException` instead of letting the request through, because there is no session to hold the confirmation.

### The Confirmation Page

Your app serves `password_confirmation.route`. On a correct password, send the user back to the intended URL:

```php title="app/web/Controller/ConfirmPasswordController.php"
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\IntendedUrlInterface;
use Marko\Authentication\Contracts\PasswordConfirmationInterface;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Middleware(AuthMiddleware::class)]
class ConfirmPasswordController
{
    public function __construct(
        private GuardInterface $guard,
        private PasswordConfirmationInterface $passwordConfirmation,
        private IntendedUrlInterface $intendedUrl,
    ) {}

    #[Post('/confirm-password')]
    public function confirm(
        Request $request,
    ): Response {
        $confirmed = $this->passwordConfirmation->confirm(
            $this->guard->user(),
            (string) $request->post('password'),
        );

        if (!$confirmed) {
            return Response::redirect('/confirm-password?invalid=1');
        }

        return Response::redirect($this->intendedUrl->pull('/account'));
    }
}
```

`TooManyPasswordConfirmationAttemptsException` renders as a 429 wherever it is thrown; catch it to show your own message. `timeout_seconds`, `throttle.max_attempts` or `throttle.decay_seconds` below `1` throws an `AuthException` when it is read.

## Validation Rules

Two [validation](/docs/packages/validation/) rules need authentication services, so they live here. `AuthRules` builds them; inject it where you declare rules and pass the rule objects alongside string rules. Neither has a string form.

### Current Password

`currentPassword(?string $guard = null)` returns a `CurrentPassword` rule that passes when the value is the authenticated user's password, for example before changing an email address or password:

```php title="app/web/Controller/PasswordController.php"
use Marko\Authentication\Validation\AuthRules;
use Marko\Validation\Contracts\ValidatorInterface;

class PasswordController
{
    public function __construct(
        private ValidatorInterface $validator,
        private AuthRules $authRules,
    ) {}

    public function update(
        array $input,
    ): void {
        $this->validator->validateOrFail($input, [
            'current_password' => ['required', $this->authRules->currentPassword()],
            'password' => ['required', 'confirmed', $this->authRules->password()],
        ]);

        // Hash and save the new password
    }
}
```

The check goes through [`PasswordVerifierInterface`](#password-confirmation), the framework's one password check. With no authenticated user the rule fails, after the verifier's dummy hash check. Pass a guard name (`currentPassword('admin')`) to check that guard's user instead of the default guard's. A non-string value fails without being checked.

Like most rules it is skipped for an empty value, so pair it with `required`. A failure reads `The current_password is incorrect.`

### Password Strength

`password()` returns a `Password` rule built from the `authentication.password` config keys, so registration, password change and password reset share one policy:

| Key | Requirement |
|-----|-------------|
| `min` | At least this many characters, counted as UTF-8 characters (must be 1 or more) |
| `mixed_case` | At least one uppercase and one lowercase letter |
| `letters` | At least one letter |
| `numbers` | At least one number |
| `symbols` | At least one symbol: punctuation, a symbol or a space character |

Every key must be present in `config/authentication.php`; a missing key throws a `ConfigNotFoundException` and a `min` below 1 throws an `AuthException`. Letters, cases and numbers include non-ASCII ones (`Ñ`, `ß`, `٣`).

The builder methods `min(int)`, `mixedCase()`, `letters()`, `numbers()` and `symbols()` each return a new rule, so you can tighten the defaults for one form without changing them for any other:

```php
$this->validator->validateOrFail($input, [
    'password' => ['required', 'confirmed', $this->authRules->password()->min(16)->symbols()],
]);
```

`min()` replaces the configured minimum. There is no global setter: change the policy for the whole app in config.

`Password` implements [`MultiMessageRuleInterface`](/docs/packages/validation/#multimessageruleinterface), so each failed requirement is its own error:

```php
// With min 10 and numbers required, "short" fails with:
// The password field must be at least 10 characters.
// The password field must contain at least one number.
```

A non-string value fails with `The password field must be a string.` only. Checking a password against known breaches is not built in.

## Middleware

### AuthMiddleware

Protects routes by requiring authentication:

```php title="DashboardController.php"
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

When the request is not authenticated, `AuthMiddleware` does one of two things:

- **Redirects** to `redirectTo` (default `/login`) when the guard is stateful, such as `SessionGuard`, and the request does not want JSON. A redirect is a real response, not an error.
- **Throws a `401` `UnauthenticatedException`** (an `HttpException`) otherwise: when `redirectTo` is `null`, when the request wants JSON (`Request::wantsJson()`, whatever the guard), and always for a [stateless guard](#stateless-guards) such as the token guard, because API clients can't follow a login redirect. A stateless guard's `401` also carries its `WWW-Authenticate` challenge (`WWW-Authenticate: Bearer` for the token guard).

The routing pipeline renders the thrown `401` through [`ExceptionRenderer`](/docs/packages/routing/#errors-and-http-exceptions), so the format comes from the request, not from the guard. It is JSON when the `Accept` header asks for `application/json` or a `+json` type (or when the request has a JSON `Content-Type` and no `Accept`), and a minimal HTML page otherwise:

```json
{"message": "Unauthorized."}
```

To disable the redirect for a web guard, register a binding with `redirectTo: null`:

```php title="module.php"
use Marko\Authentication\AuthManager;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Core\Container\ContainerInterface;

// In 'bindings'
AuthMiddleware::class => fn (ContainerInterface $container): AuthMiddleware => new AuthMiddleware(
    auth: $container->get(AuthManager::class),
    redirectTo: null,
),
```

To change how the `401` looks (a branded page, a different JSON shape), replace `ExceptionRenderer` with a `#[Preference]`. See [Custom error pages](/docs/packages/routing/#custom-error-pages). Authorization failures from `#[Can]` and `Gate::authorize()` use the same renderer; see [Failure Responses](/docs/packages/authorization/#failure-responses).

### GuestMiddleware

Restricts routes to unauthenticated users (e.g., login pages):

```php title="LoginController.php"
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

### RequirePasswordConfirmationMiddleware

Asks for the password again before sensitive routes. See [Password Confirmation](#protecting-routes).

### EnsureEmailVerifiedMiddleware

Keeps users without a verified email out of a route. See [Email Verification](#requiring-a-verified-email).

## Events

The auth package dispatches [events](/docs/packages/events/) during the authentication lifecycle. Create observers to react to these events.

`SessionGuard` dispatches `LoginEvent`, `LogoutEvent` and `FailedLoginEvent` through core's `EventDispatcherInterface`, which `AuthManager` passes to every session guard it builds. Any guard you get from `AuthManager::guard()` or by injecting `GuardInterface` fires them. Stateless guards have no login or logout, so they never fire these events. The token guard has its own token lifecycle events instead; see [marko/authentication-token events](/docs/packages/authentication-token/#events).

### LoginEvent

Dispatched when a user successfully logs in, including a login from a remember-me cookie (with `getRemember()` returning `true`):

```php title="LogLoginObserver.php"
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

Dispatched when a user logs out, after the session ID has been regenerated:

```php title="LogLogoutObserver.php"
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

```php title="LogFailedLoginObserver.php"
use Marko\Authentication\Event\FailedLoginEvent;
use Marko\Core\Attributes\Observer;

#[Observer(FailedLoginEvent::class)]
class LogFailedLoginObserver
{
    public function handle(
        FailedLoginEvent $event,
    ): void {
        $credentials = $event->getCredentials(); // Secret-looking keys removed
        $guard = $event->getGuard();

        // Log failed attempt, alert on suspicious activity, add custom lockout logic
    }
}
```

[Login throttling](#login-throttling) is built in. `FailedLoginEvent` is the hook for anything beyond it, such as alerting or a custom lockout policy.

Before the credentials are stored on the event, every key matching `FailedLoginEvent::SENSITIVE_KEY_PATTERN` (`/password|secret|token|otp|pin|passcode/i`) is removed, at any nesting depth. So `password`, `password_confirmation`, `current_password`, `otp`, `api_token` and similar never reach listeners or logs, while identifiers such as `email` or `username` are kept.

### PasswordResetEvent

Dispatched by `PasswordResetBroker::reset()` after the new password is saved (see [Password Reset](#password-reset)):

```php title="NotifyPasswordResetObserver.php"
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

### EmailVerifiedEvent

Dispatched by `EmailVerifier::verify()` after the verified user is saved (see [Email Verification](#email-verification)). Verifying an already verified user doesn't dispatch it again:

```php title="WelcomeVerifiedUserObserver.php"
use Marko\Authentication\Event\EmailVerifiedEvent;
use Marko\Core\Attributes\Observer;

#[Observer(EmailVerifiedEvent::class)]
class WelcomeVerifiedUserObserver
{
    public function handle(
        EmailVerifiedEvent $event,
    ): void {
        $user = $event->getUser(); // MustVerifyEmailInterface

        // Send a welcome email, unlock features, etc.
    }
}
```

### OtherDevicesLoggedOutEvent

Dispatched by `SessionGuard::logoutOtherDevices()` after the user's other sessions and remember-me devices are ended (see [Logging Out Other Devices](#logging-out-other-devices)):

```php title="NotifyOtherDevicesLoggedOutObserver.php"
use Marko\Authentication\Event\OtherDevicesLoggedOutEvent;
use Marko\Core\Attributes\Observer;

#[Observer(OtherDevicesLoggedOutEvent::class)]
class NotifyOtherDevicesLoggedOutObserver
{
    public function handle(
        OtherDevicesLoggedOutEvent $event,
    ): void {
        $user = $event->getUser();
        $guard = $event->getGuard();

        // Tell the user their other devices were signed out
    }
}
```

## API Reference

### AuthManager

```php
public function guard(?string $name = null): GuardInterface;
public function useGuard(string $name, GuardInterface $guard): void;
public function check(): bool;
public function user(): ?AuthenticatableInterface;
public function id(): int|string|null;
public function attempt(array $credentials): bool;
public function logout(): void;
public function logoutOtherDevices(AuthenticatableInterface $user, ?string $guard = null): void; // throws AuthException for a guard without OtherDeviceLogoutInterface
public function reset(): void;
```

`AuthManager` implements `ResettableInterface`: `reset()` clears the per-request state (the resolved user) of every guard it has built or been given, so a long-running worker never serves one request with the previous request's user. `useGuard()` puts a guard instance in place for a guard name, replacing any guard already built for it; `guard($name)` returns it from then on, even for a name that isn't in `authentication.guards`. The [marko/testing](/docs/packages/testing/) HTTP test client's `actingAs()` uses it to authenticate a user without a login request.

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
public UserProviderInterface $provider { set; }
public function getName(): string;
```

### StatelessGuardInterface

Extends `GuardInterface`:

```php
public function getChallenge(): string;
```

### ChallengeableGuardInterface

Extends `GuardInterface`; implemented by `SessionGuard`. See [Login Challenges](#login-challenges).

```php
public function hasPendingChallenge(): bool;
public function pendingChallengeUser(): ?AuthenticatableInterface;
public function pendingChallengeName(): ?string;
public function completeChallenge(bool $remember = false): void;
public function cancelChallenge(): void;
```

`completeChallenge()` throws `AuthException` when no challenge is pending (or the pending user no longer exists).

### LoginChallengeInterface

```php
public function requiresChallenge(AuthenticatableInterface $user): bool;
public function getName(): string;
```

`LoginChallengeResolver` resolves the classes listed in `authentication.guards.<name>.challenges` from the container. An entry that is not a `LoginChallengeInterface` class throws `AuthException`.

### OtherDeviceLogoutInterface

Implemented by `SessionGuard`:

```php
public function logoutOtherDevices(AuthenticatableInterface $user): void;
```

### HasSessionVersionInterface

Extends `AuthenticatableInterface`:

```php
public function getSessionVersion(): string; // '' when never changed
public function setSessionVersion(string $version): void;
```

### UnauthenticatedException

Extends `Marko\Routing\Exceptions\HttpException`. Its message is `Unauthorized.`, and the guard name goes in the log-only context:

```php
public static function forGuard(GuardInterface $guard): self; // 401; adds WWW-Authenticate: getChallenge() for a StatelessGuardInterface
```

### GuardDriverRegistry

```php
public function extend(string $driver, Closure $factory): void; // Closure(string $name, array $config, UserProviderInterface $provider): GuardInterface
public function has(string $driver): bool;
public function drivers(): array; // list<string>
public function create(string $driver, string $name, array $config, UserProviderInterface $provider): ?GuardInterface;
```

### AuthenticatableInterface

```php
public function getAuthIdentifier(): int|string;
public function getAuthIdentifierName(): string;
public function getAuthPassword(): string;
public function getRememberToken(): ?string;
public function setRememberToken(?string $token): void;
public function getRememberTokenExpiresAt(): ?DateTimeImmutable;
public function setRememberTokenExpiresAt(?DateTimeImmutable $expiresAt): void;
public function getRememberTokenName(): string;
```

### UserProviderResolver

```php
public function forGuard(string $guard, array $guardConfig): UserProviderInterface;
public function forProvider(string $providerName): UserProviderInterface;
```

### UserPersisterInterface

```php
public function persist(AuthenticatableInterface $user): void;
```

### UserPersisterResolver

```php
public function forProvider(string $providerName): UserPersisterInterface;
public function forGuardProvider(string $guard, ?string $providerName): UserPersisterInterface; // null: the UserPersisterInterface binding
public function assertUserSupports(AuthenticatableInterface $user, string $interface, string $providerName): void;
```

### CanResetPasswordInterface

Extends `AuthenticatableInterface`:

```php
public function setAuthPassword(string $hash): void;
public function getEmailForPasswordReset(): string;
```

### PasswordResetBrokerInterface

```php
public function sendResetLink(array $credentials): ResetLinkStatus; // Sent | Throttled
public function reset(array $credentials, string $token, string $newPassword): ResetStatus; // PasswordReset | InvalidToken | InvalidUser
```

### PasswordResetTokenRepositoryInterface

```php
public function store(int|string $userId, string $tokenHash, DateTimeImmutable $createdAt): void;
public function find(int|string $userId): ?PasswordResetTokenRecord; // ->tokenHash, ->createdAt
public function delete(int|string $userId): void;
public function deleteExpired(DateTimeImmutable $before): int;
```

### ResetLinkNotifierInterface

```php
public function send(CanResetPasswordInterface $user, string $token): void;
```

### MustVerifyEmailInterface

Extends `AuthenticatableInterface`:

```php
public function hasVerifiedEmail(): bool;
public function markEmailAsVerified(DateTimeImmutable $verifiedAt): void;
public function getEmailForVerification(): string;
```

### EmailVerifierInterface

```php
public function sendVerificationLink(MustVerifyEmailInterface $user): VerificationLinkStatus; // Sent | Throttled | AlreadyVerified
public function verify(Request $request): VerificationStatus; // Verified | AlreadyVerified; throws InvalidSignatureException
```

### VerificationNotifierInterface

```php
public function send(MustVerifyEmailInterface $user, string $url): void;
```

### CurrentVerifiableUser

```php
public function get(): MustVerifyEmailInterface; // throws UnauthenticatedException for a guest, AuthException for another user type
public function guardName(): string;
```

### PasswordVerifierInterface

```php
public function verify(?AuthenticatableInterface $user, string $password): bool; // null user: verifyDummy(), false
```

### PasswordConfirmationInterface

```php
public const string SESSION_KEY = 'auth.password_confirmed_at';
public function confirm(AuthenticatableInterface $user, string $password): bool; // throws TooManyPasswordConfirmationAttemptsException
public function markConfirmed(): void;
public function isRecentlyConfirmed(): bool;
```

### AuthRules

```php
public function currentPassword(?string $guard = null): CurrentPassword;
public function password(): Password; // the authentication.password defaults
```

### Password

```php
public function min(int $min): self;
public function mixedCase(): self;
public function letters(): self;
public function numbers(): self;
public function symbols(): self;
```

Each method returns a new rule. `Password` implements `MultiMessageRuleInterface`; `CurrentPassword` implements `RuleInterface`. See [Validation Rules](#validation-rules).

### IntendedUrlInterface

```php
public function remember(Request $request): void; // GET/HEAD only; other methods forget the URL
public function pull(string $default): string;
```

### PasswordConfirmationRequiredException

Extends `HttpException` (423). Thrown by `RequirePasswordConfirmationMiddleware` for a request that wants JSON.

### EmailNotVerifiedException

Extends `HttpException` (403). Thrown by `EnsureEmailVerifiedMiddleware` for a request that wants JSON.

### TooManyPasswordConfirmationAttemptsException

Extends `AuthException` and implements `HttpExceptionInterface` (429, `Retry-After`).

```php
public static function lockedOut(int $retryAfter): self;
public function getRetryAfter(): int;
```

### UserProviderInterface

```php
public function retrieveById(int|string $identifier): ?AuthenticatableInterface;
public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface;
public function validateCredentials(AuthenticatableInterface $user, array $credentials): bool;
public function rehashPasswordIfNeeded(AuthenticatableInterface $user, array $credentials): void;
public function retrieveByRememberToken(int|string $identifier, string $token): ?AuthenticatableInterface;
public function updateRememberToken(AuthenticatableInterface $user, ?string $token, ?DateTimeImmutable $expiresAt): void;
```

`retrieveByRememberToken()` receives the SHA-256 hash of the cookie's token, the same value previously passed to `updateRememberToken()`.

`retrieveByCredentials()` and `validateCredentials()` receive request input as-is, so a provider must treat non-string `email` or `password` values (such as `email[]=x`) as a failed login rather than passing them to typed methods. When `retrieveByCredentials()` finds no usable account (unknown or inactive), it should call `PasswordHasherInterface::verifyDummy()` before returning `null`, so a failed login costs one password check either way and response timing does not reveal which accounts exist. `AdminUserProvider` in [admin-auth](/docs/packages/admin-auth/) does both.

`SessionGuard` calls `rehashPasswordIfNeeded()` only after `validateCredentials()` accepted the same credentials. Check `PasswordHasherInterface::needsRehash()` against the stored hash and, when it returns `true`, hash the plain password and save it:

```php
public function rehashPasswordIfNeeded(
    AuthenticatableInterface $user,
    array $credentials,
): void {
    $password = $credentials['password'] ?? null;

    if (!is_string($password) || !$this->hasher->needsRehash($user->getAuthPassword())) {
        return;
    }

    $user->password = $this->hasher->hash($password);
    $this->users->save($user);
}
```

### PasswordHasherInterface

```php
public function hash(string $password): string;
public function verify(string $password, string $hash): bool;
public function needsRehash(string $hash): bool;
public function verifyDummy(string $password): void;
```

`verifyDummy()` checks the password against a fixed dummy hash of the configured cost and discards the result. `BcryptPasswordHasher` builds that dummy hash at its own cost, so the check takes as long as verifying a real stored password.

Bcrypt only uses the first 72 bytes of a password and cannot hash a NUL (`\0`) byte. Rather than silently truncate, `BcryptPasswordHasher::hash()` throws `InvalidPasswordException` for a password longer than 72 bytes (`BcryptPasswordHasher::MAX_PASSWORD_BYTES`) or containing a NUL byte. `verify()` returns `false` for such a password and never throws; it still runs the dummy check first, so rejecting an oversize password at login takes as long as checking a real one. The limit is in bytes, not characters, so validate passwords with `strlen()` on registration and password change to show a form error instead of an exception:

```php
use Marko\Authentication\Hashing\BcryptPasswordHasher;

if (strlen($password) > BcryptPasswordHasher::MAX_PASSWORD_BYTES || str_contains($password, "\0")) {
    // Reject with a validation error
}
```

### SessionGuard

```php
public function login(AuthenticatableInterface $user, bool $remember = false): void;
public function logoutOtherDevices(AuthenticatableInterface $user): void;
```

If you construct a `SessionGuard` by hand, pass `userPersisterResolver` (and the guard's `providerName`) for `logoutOtherDevices()` to save a new session version; without it, the call throws an `AuthException` for a user implementing `HasSessionVersionInterface`.

### LoginThrottleInterface

Bound to `LoginThrottle` (or `NullLoginThrottle` when `authentication.throttle.enabled` is `false`). Replace the binding with a Preference to change the policy.

```php
public function ensureNotLockedOut(string $guard, array $credentials): void; // throws TooManyLoginAttemptsException
public function recordFailure(string $guard, array $credentials): void;
public function clear(string $guard, array $credentials): void;
```

### TooManyLoginAttemptsException

Extends `AuthException` and implements `HttpExceptionInterface` (429, `Retry-After`).

```php
public static function lockedOut(string $guard, int $retryAfter): self;
public function getRetryAfter(): int;
public function getStatusCode(): int; // 429
public function getHeaders(): array; // ['Retry-After' => '...']
public function getResponseData(): array; // ['message' => ..., 'retry_after' => ...]
```

### CookieJarInterface

```php
public function get(string $name): ?string;
public function set(string $name, string $value, int $minutes = 0): void;
public function delete(string $name): void;
```

### RememberTokenManager

```php
public function __construct(ClockInterface $clock, ?int $lifetimeMinutes = null);
public function generate(): string;
public function generateSelector(): string;
public function hash(string $token): string;
public function validate(string $token, string $storedHash): bool;
public function expiresAt(): DateTimeImmutable;
public function hasExpired(DateTimeImmutable $expiresAt): bool;
public function minutesUntil(DateTimeImmutable $expiresAt): int;
public function isExpired(DateTimeImmutable $createdAt): bool;
```

### RememberTokenStorageInterface

Per-device remember-me tokens. Bound by [marko/admin-auth](/docs/packages/admin-auth/#remember-me-tokens) (`RememberTokenRepository`); bind your own implementation to store them elsewhere. See [Per-Device Tokens](#per-device-tokens).

```php
public function store(RememberTokenRecord $token): void;
public function findBySelector(string $guard, string $selector): ?RememberTokenRecord;
public function rotateValidator(string $guard, string $selector, string $currentValidatorHash, string $newValidatorHash): bool;
public function deleteBySelector(string $guard, string $selector): void;
public function deleteAllForUser(string $guard, int|string $userId, ?string $exceptSelector = null): int;
public function clearExpiredTokens(): int;
public function clearAllTokens(): int;
```

`RememberTokenRecord` is a readonly value object with `guard`, `userId`, `selector`, `validatorHash`, `expiresAt` and `userAgent`.

### RequestCookieJar

```php
public function __construct(AuthConfig $config, ClockInterface $clock);
public function setRequest(Request $request): void;
public function pullQueuedCookies(): array; // array<int, Cookie>
public function reset(): void;
```
