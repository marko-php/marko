# marko/authentication-two-factor

Two-factor authentication for Marko --- TOTP codes from any authenticator app, single-use recovery codes, and a login challenge that holds password logins until the code is entered.

## Installation

```bash
composer require marko/authentication-two-factor
```

## Quick Example

```php
use Marko\AuthenticationTwoFactor\Contracts\TwoFactorManagerInterface;
use Marko\AuthenticationTwoFactor\TwoFactorChallenge;

// Setup: show the QR code (otpauth URI) and recovery codes once
$setup = $twoFactorManager->enable($user, label: $user->email);
$twoFactorManager->confirm($user, $codeFromApp);

// Login: with TwoFactorLoginChallenge on the guard, attempt() holds the user
if (!$authManager->attempt($credentials) && $twoFactorChallenge->isPending()) {
    // ...show the code form, then:
    $twoFactorChallenge->verify($submittedCode);
}
```

## Documentation

Full usage, API reference, and examples: [marko/authentication-two-factor](https://marko.build/docs/packages/authentication-two-factor/)
