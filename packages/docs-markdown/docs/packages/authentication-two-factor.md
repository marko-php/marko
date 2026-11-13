---
title: marko/authentication-two-factor
description: Two-factor authentication for Marko --- TOTP codes from any authenticator app, single-use recovery codes, and a login challenge that holds password logins until the code is entered.
---

Two-factor authentication for Marko --- TOTP codes from any authenticator app, single-use recovery codes, and a login challenge that holds password logins until the code is entered. The package implements RFC 6238 (6-digit codes, 30-second steps, one step of clock drift either side) on top of [spomky-labs/otphp](https://github.com/Spomky-Labs/otphp), rejects replayed codes, and plugs into [marko/authentication](/docs/packages/authentication/)'s [login challenge hook](/docs/packages/authentication/#login-challenges), so the session guard itself is never patched. `RequireTwoFactorMiddleware` makes two-factor mandatory on routes such as an admin area.

## Installation

```bash
composer require marko/authentication-two-factor
```

The challenge throttle counts attempts through [marko/ratelimiter](/docs/packages/ratelimiter/), which stores them in the cache, so a cache driver such as [marko/cache-file](/docs/packages/cache-file/) or [marko/cache-redis](/docs/packages/cache-redis/) is required.

## Configuration

```php title="config/authentication-two-factor.php"
use Marko\Config\Env;

return [
    'issuer' => Env::nullableString('TWO_FACTOR_ISSUER'),
    'setup_route' => '/two-factor/setup',
    'recovery_code_count' => 8,
    'throttle' => [
        'max_attempts' => 5,
        'decay_seconds' => 60,
    ],
];
```

| Key | Purpose |
|---|---|
| `issuer` | The name authenticator apps show next to the account, usually your app name. Required: `enable()` throws `TwoFactorException` while it is not set. |
| `setup_route` | Where `RequireTwoFactorMiddleware` redirects a browser request from a user without confirmed two-factor. |
| `recovery_code_count` | How many recovery codes `enable()` and `regenerateRecoveryCodes()` issue. |
| `throttle.max_attempts` / `throttle.decay_seconds` | Codes a pending user may try within the window before `TwoFactorChallenge::verify()` throws `TooManyTwoFactorAttemptsException`. |

Every number must be 1 or more; anything else throws `TwoFactorException` when it is read.

Then list the login challenge on each session guard that should ask for a code:

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

## Usage

### Preparing the User Entity

Implement `TwoFactorAuthenticatableInterface` on your user entity. Changes are saved through the `UserPersisterInterface` you already bind for marko/authentication (see [Saving Changed Users](/docs/packages/authentication/#saving-changed-users)); there is no second persistence contract.

```php title="app/web/src/Entity/User.php"
use DateTimeImmutable;
use Marko\AuthenticationTwoFactor\Contracts\TwoFactorAuthenticatableInterface;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Encrypted;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('users')]
class User extends Entity implements TwoFactorAuthenticatableInterface
{
    #[Column]
    #[Encrypted]
    public ?string $twoFactorSecret = null;

    /** @var list<string> SHA-256 hashes */
    #[Column]
    public array $twoFactorRecoveryCodes = [];

    #[Column]
    public ?DateTimeImmutable $twoFactorConfirmedAt = null;

    #[Column]
    public ?int $twoFactorLastUsedStep = null;

    public function getTwoFactorSecret(): ?string
    {
        return $this->twoFactorSecret;
    }

    public function setTwoFactorSecret(
        ?string $secret,
    ): void {
        $this->twoFactorSecret = $secret;
    }

    // ...the other getters and setters, plus AuthenticatableInterface
}
```

- **The secret must be reversible**, because every login recomputes codes from it. Store it encrypted at rest with [`#[Encrypted]`](/docs/packages/database/#encrypted-columns) from marko/database (which needs an encryption driver such as [marko/encryption-openssl](/docs/packages/encryption-openssl/)). This package ships no entity, so it does not require marko/database; your entity does.
- **Recovery codes are stored as SHA-256 hashes**, never in plain text. They are single-use secrets you never need to show again, so they need not be reversible.
- **`twoFactorLastUsedStep`** records the last accepted TOTP time step. A code for that step or an earlier one is rejected, so a code seen over someone's shoulder cannot be replayed.

### Enabling Two-Factor

Setup takes two steps. `enable()` stores a new secret and recovery codes but does not activate them: logins are only challenged once the user proves their authenticator works by entering a first code to `confirm()`.

```php title="app/web/src/Controller/TwoFactorSetupController.php"
use Marko\Authentication\AuthManager;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\AuthenticationTwoFactor\Contracts\TwoFactorManagerInterface;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Middleware(AuthMiddleware::class)]
class TwoFactorSetupController
{
    public function __construct(
        private AuthManager $authManager,
        private TwoFactorManagerInterface $twoFactorManager,
    ) {}

    #[Post('/two-factor/enable')]
    public function enable(): Response
    {
        $user = $this->authManager->user();
        $setup = $this->twoFactorManager->enable($user, label: $user->email);

        // Show these once: only hashes of the recovery codes are stored
        return Response::json([
            'secret' => $setup->secret,
            'otpauth_uri' => $setup->provisioningUri,
            'recovery_codes' => $setup->recoveryCodes,
        ]);
    }

    #[Post('/two-factor/confirm')]
    public function confirm(
        Request $request,
    ): Response {
        $confirmed = $this->twoFactorManager->confirm(
            $this->authManager->user(),
            (string) $request->post('code'),
        );

        return $confirmed
            ? Response::redirect('/account?two-factor=enabled')
            : Response::redirect('/two-factor/setup?error=invalid-code');
    }
}
```

`enable()` throws `TwoFactorException` for a user who already has two-factor confirmed, so an active secret is never replaced by accident: call `disable()` first to move to a new authenticator. Calling `enable()` again before confirming simply starts setup over with a new secret.

### Showing a QR Code

The package returns the `otpauth://` URI rather than an image, so it carries no image or SVG dependency. Render the URI with the QR library of your choice, for example [bacon/bacon-qr-code](https://github.com/Bacon/BaconQrCode):

```php
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

$writer = new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd()));
$svg = $writer->writeString($setup->provisioningUri);
```

Also show `$setup->secret` for users who type the key into their app by hand.

### Handling the Login Challenge

With `TwoFactorLoginChallenge` on the guard, `attempt()` returns `false` for a user with confirmed two-factor even when the password is right: the user is held at the challenge, not logged in, and no `LoginEvent` fires yet. `TwoFactorChallenge` handles the code step:

```php title="app/web/src/Controller/LoginController.php"
use Marko\Authentication\AuthManager;
use Marko\AuthenticationTwoFactor\TwoFactorChallenge;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

class LoginController
{
    public function __construct(
        private AuthManager $authManager,
        private TwoFactorChallenge $twoFactorChallenge,
    ) {}

    #[Post('/login')]
    public function login(
        Request $request,
    ): Response {
        if ($this->authManager->attempt([
            'email' => (string) $request->post('email'),
            'password' => (string) $request->post('password'),
        ], remember: $request->post('remember') === '1')) {
            return Response::redirect('/dashboard');
        }

        if ($this->twoFactorChallenge->isPending()) {
            return Response::redirect('/login/two-factor');
        }

        return Response::redirect('/login?error=invalid');
    }

    #[Post('/login/two-factor')]
    public function twoFactor(
        Request $request,
    ): Response {
        if (!$this->twoFactorChallenge->isPending()) {
            return Response::redirect('/login');
        }

        $verified = $this->twoFactorChallenge->verify((string) $request->post('code'));

        return $verified
            ? Response::redirect('/dashboard')
            : Response::redirect('/login/two-factor?error=invalid-code');
    }
}
```

`verify()` accepts either a 6-digit TOTP code or a recovery code. A valid one calls `completeChallenge()` on the guard, which logs the user in, dispatches `LoginEvent` and, when the login was attempted with `remember: true`, issues the remember-me cookie. Pass `remember: true` or `remember: false` to `verify()` to override the flag from `attempt()`. A remember cookie therefore only ever exists after a completed challenge. A recovery code is spent the moment it is used. `cancel()` abandons the pending login.

Every method takes an optional `guard` argument for guards other than `authentication.default.guard`. `verify()` throws `TwoFactorException` when no two-factor challenge is pending on the guard (check `isPending()` first; the session may have expired).

### Throttling

`verify()` counts every attempt per guard and pending user through marko/ratelimiter's `RateLimiterInterface`. More than `throttle.max_attempts` within `throttle.decay_seconds` throws `TooManyTwoFactorAttemptsException` --- even for the right code --- until the window passes. A successful code clears the count. The exception renders as `429 Too Many Requests` with a `Retry-After` header if you don't catch it; `getRetryAfter()` gives the seconds to wait.

### Managing Recovery Codes and Disabling

```php
// Replace every recovery code; returns the new plain codes to show once
$codes = $this->twoFactorManager->regenerateRecoveryCodes($user);

// Turn two-factor off: clears the secret, codes and confirmation
$this->twoFactorManager->disable($user);

$this->twoFactorManager->isEnabled($user); // confirmed secret present?
```

`regenerateRecoveryCodes()` throws `TwoFactorException` for a user without confirmed two-factor.

Every manager method takes an optional `guard` argument too. Changes are saved through the persister of that guard's user provider (`authentication.providers.<provider>.persister`, or your `UserPersisterInterface` binding), so a user from an `admins` provider is saved by the admins' persister.

When `confirm()` activates two-factor, it also ends the user's other sessions and remember-me devices on the guard through `AuthManager::logoutOtherDevices()` (see [marko/authentication](/docs/packages/authentication/)), because none of them passed a two-factor challenge. The device that confirmed stays logged in.

### Requiring Two-Factor on Routes

`RequireTwoFactorMiddleware` lets through only users with confirmed two-factor. Put it after `AuthMiddleware`:

```php title="app/web/src/Controller/BillingController.php"
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\AuthenticationTwoFactor\Middleware\RequireTwoFactorMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;

#[Middleware(AuthMiddleware::class)]
#[Middleware(RequireTwoFactorMiddleware::class)]
class BillingController
{
    #[Get('/billing')]
    public function index(): Response
    {
        // Only users with confirmed two-factor get here
    }
}
```

A browser request from a user without confirmed two-factor is redirected to `setup_route`; a request that wants JSON gets a `403` (`TwoFactorRequiredException`). A request with no authenticated user gets the usual `401` `UnauthenticatedException`, and a user entity that does not implement `TwoFactorAuthenticatableInterface` throws `TwoFactorException`.

The middleware checks the default guard. For another guard, set the `guard` constructor argument with the `arguments` key. The other parameters still autowire (see [Argument Substitution](/docs/concepts/dependency-injection/#argument-substitution)):

```php title="module.php"
use Marko\AuthenticationTwoFactor\Middleware\RequireTwoFactorMiddleware;

return [
    'arguments' => [
        RequireTwoFactorMiddleware::class => [
            'guard' => 'admin',
        ],
    ],
];
```

### Requiring Two-Factor for marko/admin-auth

Two-factor is not built into [marko/admin-auth](/docs/packages/admin-auth/); turning it on is configuration:

1. Give the admin entity two-factor columns: a [Preference](/docs/concepts/preferences/) for `AdminUser` that implements `TwoFactorAuthenticatableInterface`, and a persister for the `admins` provider (`authentication.providers.admins.persister`).
2. Add `TwoFactorLoginChallenge::class` to `authentication.guards.admin.challenges`, and handle the challenge in your admin login flow with `$twoFactorChallenge->verify($code, guard: 'admin')`.
3. Bind `RequireTwoFactorMiddleware` with `guard: 'admin'` as above, and apply it to the admin routes.

## Events

All events live in `Marko\AuthenticationTwoFactor\Event`. None carries a secret or code.

| Event | Dispatched by | Properties |
|---|---|---|
| `TwoFactorEnabledEvent` | `confirm()`, when two-factor becomes active (not `enable()`) | `user` |
| `TwoFactorDisabledEvent` | `disable()`, for a user who had two-factor confirmed | `user` |
| `RecoveryCodeUsedEvent` | `verify()` / `TwoFactorChallenge::verify()`, when a recovery code is spent | `user`, `remainingCodes` |
| `TwoFactorChallengeFailedEvent` | `TwoFactorChallenge::verify()`, for a wrong code | `user`, `guard` |

```php title="app/web/src/Observer/LowRecoveryCodesObserver.php"
use Marko\AuthenticationTwoFactor\Event\RecoveryCodeUsedEvent;
use Marko\Core\Attributes\Observer;

#[Observer(RecoveryCodeUsedEvent::class)]
class LowRecoveryCodesObserver
{
    public function handle(
        RecoveryCodeUsedEvent $event,
    ): void {
        if ($event->remainingCodes <= 2) {
            // Remind the user to generate new recovery codes
        }
    }
}
```

## API Reference

### TwoFactorManagerInterface

```php
public function enable(TwoFactorAuthenticatableInterface $user, string $label, ?string $guard = null): TwoFactorSetup;
public function confirm(TwoFactorAuthenticatableInterface $user, string $code, ?string $guard = null): bool;
public function disable(TwoFactorAuthenticatableInterface $user, ?string $guard = null): void;
public function regenerateRecoveryCodes(TwoFactorAuthenticatableInterface $user, ?string $guard = null): array;
public function isEnabled(TwoFactorAuthenticatableInterface $user): bool;
public function verify(TwoFactorAuthenticatableInterface $user, string $code, ?string $guard = null): bool;
```

Bound to `TwoFactorManager`. `TwoFactorSetup` has the public properties `secret`, `provisioningUri` and `recoveryCodes` (plain, shown once).

### TwoFactorChallenge

```php
public function isPending(?string $guard = null): bool;
public function verify(string $code, ?bool $remember = null, ?string $guard = null): bool; // null: the flag attempt() was called with
public function cancel(?string $guard = null): void;
```

### TwoFactorLoginChallenge

Implements `LoginChallengeInterface` with the name `two_factor` (`TwoFactorLoginChallenge::NAME`). It requires the challenge for users that implement `TwoFactorAuthenticatableInterface` and have a confirmed secret.

### TwoFactorAuthenticatableInterface

Extends `AuthenticatableInterface`:

```php
public function getTwoFactorSecret(): ?string;
public function setTwoFactorSecret(?string $secret): void;
public function getTwoFactorRecoveryCodes(): array;
public function setTwoFactorRecoveryCodes(array $hashes): void;
public function getTwoFactorConfirmedAt(): ?DateTimeImmutable;
public function setTwoFactorConfirmedAt(?DateTimeImmutable $at): void;
public function getTwoFactorLastUsedStep(): ?int;
public function setTwoFactorLastUsedStep(?int $step): void;
```

### TotpInterface

```php
public function generateSecret(): string;
public function verify(string $secret, string $code, ?int $lastUsedStep): ?int;
public function provisioningUri(string $secret, string $label, string $issuer): string;
```

Bound to `OtphpTotp`. `verify()` returns the accepted time step, or `null` for a wrong code, a code outside the ±1 step window, or a step at or before `$lastUsedStep`. Secrets are 160-bit base32 strings.

### Exceptions

| Exception | When |
|---|---|
| `TwoFactorException` | Misconfiguration or misuse: missing issuer, invalid setting, `enable()`/`confirm()` on an active user, operations on a user without two-factor, no pending challenge, a guard that does not support challenges, a user entity without `TwoFactorAuthenticatableInterface` |
| `TooManyTwoFactorAttemptsException` | Too many challenge codes; `429` with `Retry-After` |
| `TwoFactorRequiredException` | `RequireTwoFactorMiddleware` on a JSON request from a user without confirmed two-factor; `403` |

## Related Packages

- [marko/authentication](/docs/packages/authentication/) --- Guards, user providers and the login challenge hook this package plugs into.
- [marko/ratelimiter](/docs/packages/ratelimiter/) --- Counts two-factor challenge attempts.
- [marko/database](/docs/packages/database/) --- `#[Encrypted]` for storing the secret at rest.
