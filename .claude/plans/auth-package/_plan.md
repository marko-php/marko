# Plan: Authentication Package (marko/auth)

## Created
2026-01-21

## Status
completed

## Objective
Implement a flexible authentication system for Marko framework with multiple guard support (session-based web auth, API token auth), user providers, authentication middleware, and authentication events - following the established interface/implementation split pattern where `marko/auth` provides interfaces and core infrastructure.

## Scope

### In Scope
- `marko/auth` package with interfaces, guards, user providers, middleware, and events
  - `AuthenticatableInterface` - Contract for entities that can be authenticated
  - `Authenticatable` trait - Default implementation for user entities
  - `UserProviderInterface` - Contract for fetching/validating users
  - `GuardInterface` - Contract for authentication strategies
  - `SessionGuard` - Session-based authentication (requires marko/session)
  - `TokenGuard` - API token authentication (stateless)
  - `AuthManager` - Manages multiple guards, resolves current guard
  - `AuthMiddleware` - Protects routes, redirects unauthenticated users
  - `AuthConfig` - Configuration loaded from config/auth.php
  - `PasswordHasherInterface` - Contract for password hashing/verification
  - `BcryptPasswordHasher` - Default bcrypt implementation
  - Authentication events: `LoginEvent`, `LogoutEvent`, `FailedLoginEvent`, `PasswordResetEvent`
  - `RememberTokenManager` - "Remember me" token generation and validation
  - `AuthException` hierarchy (AuthException, AuthenticationException, AuthorizationException, InvalidCredentialsException)
  - CLI commands: `auth:clear-tokens` (clear expired remember tokens)
- Integration points
  - Session package for session-based authentication state
  - Database package for user storage via UserProviderInterface
  - Core event system for authentication events
  - Routing middleware for protected routes

### Out of Scope
- User registration (application concern, not auth framework)
- Email verification (separate package: `marko/verification`)
- Password reset flow (separate package: `marko/password-reset`)
- Two-factor authentication (future enhancement)
- OAuth/social login (separate package: `marko/socialite`)
- Role-based access control / permissions (separate package: `marko/authorization`)
- Rate limiting for login attempts (middleware concern)
- JWT tokens (separate package: `marko/jwt`)
- API key management (application concern)

## Success Criteria
- [ ] `AuthenticatableInterface` defines contract for authenticatable entities
- [ ] `UserProviderInterface` provides clean retrieveById/retrieveByCredentials/validateCredentials contract
- [ ] `GuardInterface` defines check/user/login/logout/attempt contract
- [ ] `SessionGuard` stores authentication state in session, supports "remember me"
- [ ] `TokenGuard` validates bearer tokens from Authorization header
- [ ] `AuthManager` supports multiple guards with configurable default
- [ ] `AuthMiddleware` protects routes, returns 401/403 or redirects based on guard type
- [ ] `PasswordHasherInterface` provides hash/verify/needsRehash contract
- [ ] `BcryptPasswordHasher` uses PHP's `password_hash()`/`password_verify()`
- [ ] `RememberTokenManager` generates secure tokens and validates them
- [ ] Authentication events dispatched on login, logout, and failed attempts
- [ ] `auth:clear-tokens` CLI command removes expired remember tokens
- [ ] Loud error when session package not installed for SessionGuard
- [ ] Configuration supports multiple guards and user providers
- [ ] All tests passing
- [ ] Code follows project standards (strict types, no final, etc.)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding (composer.json, directories) | - | pending |
| 002 | AuthException hierarchy | 001 | pending |
| 003 | AuthenticatableInterface and trait | 001 | pending |
| 004 | PasswordHasherInterface and BcryptPasswordHasher | 001 | pending |
| 005 | UserProviderInterface contract | 003 | pending |
| 006 | GuardInterface contract | 003 | pending |
| 007 | AuthConfig class | 001 | pending |
| 008 | SessionGuard implementation | 005, 006, 007 | pending |
| 009 | TokenGuard implementation | 005, 006, 007 | pending |
| 010 | AuthManager (multi-guard support) | 008, 009 | pending |
| 011 | RememberTokenManager | 004 | pending |
| 012 | Session guard "remember me" integration | 008, 011 | pending |
| 013 | Authentication events | 001 | pending |
| 014 | Event dispatching in guards | 010, 013 | pending |
| 015 | AuthMiddleware | 006, 010 | pending |
| 016 | module.php with bindings | 010, 015 | pending |
| 017 | CLI: auth:clear-tokens command | 011 | pending |
| 018 | Unit tests for password hasher | 004 | pending |
| 019 | Unit tests for guards | 008, 009 | pending |
| 020 | Unit tests for AuthManager | 010 | pending |
| 021 | Unit tests for middleware | 015 | pending |
| 022 | Integration tests | 016, 017 | pending |
| 023 | Package README | 022 | pending |

## Architecture Notes

### Package Structure
```
packages/
  auth/
    src/
      Attributes/
        Authenticate.php         # Route attribute for protected routes
      Config/
        AuthConfig.php           # Configuration loaded from config/auth.php
      Contracts/
        AuthenticatableInterface.php
        GuardInterface.php
        PasswordHasherInterface.php
        UserProviderInterface.php
      Events/
        LoginEvent.php
        LogoutEvent.php
        FailedLoginEvent.php
        PasswordResetEvent.php
      Exceptions/
        AuthException.php
        AuthenticationException.php
        AuthorizationException.php
        InvalidCredentialsException.php
      Guard/
        SessionGuard.php
        TokenGuard.php
      Hashing/
        BcryptPasswordHasher.php
      Middleware/
        AuthMiddleware.php
        GuestMiddleware.php      # Redirect authenticated users (for login page)
      Token/
        RememberTokenManager.php
      Traits/
        Authenticatable.php
      AuthManager.php
      Command/
        ClearTokensCommand.php
    tests/
    composer.json
    module.php
```

### Config Location
```php
// config/auth.php
return [
    'defaults' => [
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
            'header' => 'Authorization',
            'prefix' => 'Bearer',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'database',
            'entity' => App\User\Entity\User::class,
            'table' => 'users',
        ],
    ],

    'passwords' => [
        'hasher' => 'bcrypt',
        'bcrypt' => [
            'cost' => 12,
        ],
    ],

    'remember' => [
        'enabled' => true,
        'token_lifetime' => 60 * 24 * 30,  // 30 days in minutes
        'cookie_name' => 'remember_token',
    ],
];
```

### Interface Design

```php
// AuthenticatableInterface - contract for user entities
interface AuthenticatableInterface
{
    public function getAuthIdentifier(): int|string;
    public function getAuthIdentifierName(): string;
    public function getAuthPassword(): string;
    public function getRememberToken(): ?string;
    public function setRememberToken(string $token): void;
    public function getRememberTokenName(): string;
}
```

```php
// UserProviderInterface - contract for user retrieval
interface UserProviderInterface
{
    public function retrieveById(int|string $identifier): ?AuthenticatableInterface;
    public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface;
    public function validateCredentials(AuthenticatableInterface $user, array $credentials): bool;
    public function retrieveByRememberToken(int|string $identifier, string $token): ?AuthenticatableInterface;
    public function updateRememberToken(AuthenticatableInterface $user, string $token): void;
}
```

```php
// GuardInterface - contract for authentication strategies
interface GuardInterface
{
    public function check(): bool;
    public function guest(): bool;
    public function user(): ?AuthenticatableInterface;
    public function id(): int|string|null;
    public function attempt(array $credentials, bool $remember = false): bool;
    public function login(AuthenticatableInterface $user, bool $remember = false): void;
    public function loginById(int|string $id, bool $remember = false): ?AuthenticatableInterface;
    public function logout(): void;
    public function setProvider(UserProviderInterface $provider): void;
    public function getName(): string;
}
```

```php
// PasswordHasherInterface - contract for password hashing
interface PasswordHasherInterface
{
    public function hash(string $password): string;
    public function verify(string $password, string $hash): bool;
    public function needsRehash(string $hash): bool;
}
```

### Guard Implementations

**SessionGuard** - Session-based authentication:
- Stores user ID in session after login
- Regenerates session ID on login (prevents session fixation)
- Supports "remember me" tokens via cookies
- Dispatches authentication events

**TokenGuard** - Stateless token authentication:
- Reads token from Authorization header
- No session required (stateless)
- Ideal for API authentication

### AuthManager - Multi-Guard Support

```php
class AuthManager
{
    public function guard(?string $name = null): GuardInterface;
    public function check(): bool;
    public function user(): ?AuthenticatableInterface;
    public function id(): int|string|null;
    public function attempt(array $credentials, bool $remember = false): bool;
    public function logout(): void;
}
```

### AuthMiddleware - Protecting Routes

```php
#[Get('/dashboard')]
#[Middleware(AuthMiddleware::class)]
public function index(): Response
{
    $user = $this->auth->user();
    return Response::view('dashboard/index', ['user' => $user]);
}

// For API routes
#[Get('/api/profile')]
#[Middleware(AuthMiddleware::class, guard: 'api')]
public function show(): Response
{
    return Response::json(['user' => $this->auth->guard('api')->user()]);
}
```

### Authentication Events

- `LoginEvent` - dispatched after successful login (user, remember, guard)
- `LogoutEvent` - dispatched after logout (user, guard)
- `FailedLoginEvent` - dispatched on failed login attempt (credentials, guard)
- `PasswordResetEvent` - dispatched when password is reset (user)

### Session Guard Dependency

The SessionGuard requires `marko/session`. If not installed:
```
AuthException: Session guard requires marko/session package.

Context: Attempted to create SessionGuard but SessionInterface is not bound.

Suggestion: Install the session package:
  composer require marko/session marko/session-file
```

### Module Bindings

```php
// auth/module.php
return [
    'enabled' => true,
    'bindings' => [
        AuthConfig::class => AuthConfig::class,
        PasswordHasherInterface::class => function (ContainerInterface $container): PasswordHasherInterface {
            $config = $container->get(AuthConfig::class);
            return new BcryptPasswordHasher($config->bcryptCost);
        },
        AuthManager::class => function (ContainerInterface $container): AuthManager {
            return new AuthManager($container, $container->get(AuthConfig::class));
        },
    ],
];
```

### Usage Examples

**User Entity:**
```php
#[Table('users')]
class User extends Entity implements AuthenticatableInterface
{
    use Authenticatable;

    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 255, unique: true)]
    public string $email;

    #[Column(length: 255)]
    public string $password;

    #[Column(length: 100, nullable: true)]
    public ?string $rememberToken = null;
}
```

**Login Controller:**
```php
#[Post('/login')]
public function login(Request $request): Response
{
    $credentials = [
        'email' => $request->post('email'),
        'password' => $request->post('password'),
    ];

    if ($this->auth->attempt($credentials, $request->post('remember'))) {
        return Response::redirect('/dashboard');
    }

    return Response::redirect('/login')->with('error', 'Invalid credentials');
}
```

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| **Session not installed** | Clear error message when attempting to use SessionGuard without session package |
| **Remember token security** | Use cryptographically secure tokens; hash before storage; timing-safe comparison |
| **Session fixation** | SessionGuard calls session->regenerate() after login |
| **User enumeration** | FailedLoginEvent does not expose whether user exists; apps can add rate limiting |
| **Password storage** | BcryptPasswordHasher uses PHP's secure password_hash() with configurable cost |
| **Token leakage** | API tokens extracted from headers, not logged; clear guidelines in docs |
| **Multiple guards complexity** | AuthManager abstracts guard resolution; simple API for common case |
