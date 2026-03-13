# marko/authentication-token

Stateless API token authentication --- issue personal access tokens with scoped abilities for mobile apps, SPAs, and third-party integrations.

## Installation

```bash
composer require marko/authentication-token
```

## Quick Example

```php
use Marko\AuthenticationToken\Service\TokenManager;

$newToken = $tokenManager->createToken(
    user: $user,
    name: 'mobile-app',
    abilities: ['posts:read', 'posts:write'],
);

// Plain-text token is ONLY available at creation time
$newToken->plainTextToken;
```

## Documentation

Full usage, API reference, and examples: [marko/authentication-token](https://marko.build/docs/packages/authentication-token/)
