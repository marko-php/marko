# marko/authentication-passkey

Passkey (WebAuthn) sign-in and passkey management --- passwordless login, password confirmation by passkey, and list, rename and delete for a user's passkeys.

## Installation

```bash
composer require marko/authentication-passkey
```

## Quick Example

```php
use Marko\AuthenticationPasskey\Service\PasskeyAuthentication;

// 1. Hand these options to navigator.credentials.get() (or the bundled passkeys.js)
$options = $passkeyAuthentication->requestOptions();

// 2. Verify the browser's response and log the passkey's owner in
$user = $passkeyAuthentication->authenticate($credentialJson);
```

## Documentation

Full usage, API reference, and examples: [marko/authentication-passkey](https://marko.build/docs/packages/authentication-passkey/)
