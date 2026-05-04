---
title: marko/oauth
description: OAuth2 authorization server integration for Marko Framework.
---

OAuth2 authorization server integration for Marko Framework. This package provides the foundation for Marko applications that need delegated access and machine-to-machine authentication through OAuth2. It wraps `league/oauth2-server` while keeping the public package surface aligned with Marko modules, configuration, database entities, repositories, CLI commands, and route-level scope declarations.

This first package slice includes configuration, OAuth storage entities, repository bindings, signing-key generation, grant-type values, and the `#[RequiresScope]` attribute. Protocol controllers and League repository adapters are planned follow-up work on top of this foundation.

## Installation

```bash
composer require marko/oauth
```

## Configuration

Configure OAuth in `config/oauth.php`:

```php title="config/oauth.php"
return [
    'routes' => [
        'enabled' => true,
        'prefix' => '/oauth',
        'management' => false,
    ],

    'keys' => [
        'private' => 'storage/oauth/private.key',
        'public' => 'storage/oauth/public.key',
        'passphrase' => null,
    ],

    'tokens' => [
        'access_token_ttl' => 'PT1H',
        'refresh_token_ttl' => 'P30D',
        'auth_code_ttl' => 'PT10M',
        'check_revocation' => true,
    ],

    'refresh_tokens' => [
        'rotate' => true,
        'reuse_detection' => true,
    ],

    'consent' => [
        'remember' => true,
        'ttl' => 'P1Y',
    ],

    'scopes' => [
        'profile:read' => 'Read your profile',
        'posts:write' => 'Create and update posts',
    ],

    'default_scopes' => [
        'profile:read',
    ],
];
```

| Key | Purpose |
| --- | --- |
| `routes.enabled` | Enables package-owned OAuth routes when protocol controllers are installed. |
| `routes.prefix` | URL prefix for OAuth routes. Defaults to `/oauth`. |
| `routes.management` | Controls optional client-management routes. Defaults to `false`. |
| `keys.private` | Private signing key path used for token signing. |
| `keys.public` | Public signing key path used for token verification. |
| `keys.passphrase` | Optional private-key passphrase. |
| `tokens.access_token_ttl` | ISO-8601 duration for access token lifetime. |
| `tokens.refresh_token_ttl` | ISO-8601 duration for refresh token lifetime. |
| `tokens.auth_code_ttl` | ISO-8601 duration for authorization code lifetime. |
| `tokens.check_revocation` | Enables database revocation checks for issued access-token identifiers. |
| `refresh_tokens.rotate` | Rotates refresh tokens on use. |
| `refresh_tokens.reuse_detection` | Enables revoked refresh-token reuse detection. |
| `consent.remember` | Remembers consent for the same user, client, and scope set. |
| `consent.ttl` | ISO-8601 duration for remembered consent. |
| `scopes` | Map of configured OAuth scope identifiers to human-readable labels. |
| `default_scopes` | Scopes applied when a request does not specify scopes. |

## Usage

### Generate Signing Keys

Generate the OAuth signing key pair with the CLI:

```bash
marko oauth:keys
```

The command writes the private and public keys to the configured paths. It refuses to overwrite existing key files unless `--force` is passed:

```bash
marko oauth:keys --force
```

The private key is written with restrictive file permissions where the platform supports it. Store production keys outside package files and avoid committing generated key material.

### Declare Required Scopes

Use `#[RequiresScope]` to declare the OAuth scopes a route requires:

```php
use Marko\OAuth\Attributes\RequiresScope;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Response;

class PostController
{
    #[Post('/posts')]
    #[RequiresScope('posts:write')]
    public function store(): Response
    {
        return Response::json(['created' => true], 201);
    }
}
```

The attribute is repeatable and can be applied to classes or methods. Scope enforcement middleware is planned with the route-aware middleware work described in the package PRD.

### OAuth Storage Entities

The package defines these database-backed entities:

| Entity | Table | Purpose |
| --- | --- | --- |
| `OAuthClient` | `oauth_clients` | Stores OAuth clients, hashed secrets, redirect URIs, allowed scopes, grant types, and revocation metadata. |
| `OAuthAuthCode` | `oauth_auth_codes` | Stores authorization codes, PKCE challenge data, scopes, redirect URI, expiry, and revocation state. |
| `OAuthAccessToken` | `oauth_access_tokens` | Stores issued access-token identifiers for revocation and audit. |
| `OAuthRefreshToken` | `oauth_refresh_tokens` | Stores refresh tokens, token families, expiry, and revocation state. |
| `OAuthApproval` | `oauth_approvals` | Stores remembered user consent per user, client, and scope set. |
| `OAuthScope` | `oauth_scopes` | Stores configured scopes when applications choose database-backed scope management. |

Each entity has a matching repository interface and concrete Marko database repository bound by `module.php`.

## Errors

Following Marko's loud-errors principle, key-generation failures throw `OAuthException` with actionable context and suggestions.

`OAuthException::keyFileExists()` is thrown when `oauth:keys` would overwrite an existing key without `--force`.

`OAuthException::keyGenerationFailed()` is thrown when OpenSSL cannot produce a usable RSA key pair.

`OAuthException::keyDirectoryFailed()` is thrown when the configured key directory cannot be created.

`OAuthException::keyWriteFailed()` is thrown when generated key material cannot be written to disk.

## API Reference

### OAuthConfig

```php
namespace Marko\OAuth\Config;

readonly class OAuthConfig
{
    public function routesEnabled(): bool;
    public function routePrefix(): string;
    public function managementRoutesEnabled(): bool;
    public function privateKeyPath(): string;
    public function publicKeyPath(): string;
    public function keyPassphrase(): ?string;
    public function accessTokenTtl(): string;
    public function refreshTokenTtl(): string;
    public function authCodeTtl(): string;
    public function checkRevocation(): bool;
    public function rotateRefreshTokens(): bool;
    public function detectRefreshTokenReuse(): bool;
    public function rememberConsent(): bool;
    public function consentTtl(): string;
    public function scopes(): array;
    public function defaultScopes(): array;
}
```

### KeyGenerator

```php
namespace Marko\OAuth\Service;

readonly class KeyGenerator
{
    public function generate(
        string $privateKeyPath,
        string $publicKeyPath,
        ?string $passphrase = null,
        bool $force = false,
    ): void;
}
```

### KeysCommand

```php
namespace Marko\OAuth\Command;

readonly class KeysCommand implements CommandInterface
{
    public function execute(Input $input, Output $output): int;
}
```

### RequiresScope

```php
namespace Marko\OAuth\Attributes;

#[RequiresScope('posts:write')]
#[RequiresScope('profile:read', 'posts:read')]
```

### Repository Interfaces

Each repository interface extends `Marko\Database\Repository\RepositoryInterface`:

```php
namespace Marko\OAuth\Repository;

interface OAuthClientRepositoryInterface extends RepositoryInterface {}
interface OAuthAuthCodeRepositoryInterface extends RepositoryInterface {}
interface OAuthAccessTokenRepositoryInterface extends RepositoryInterface {}
interface OAuthRefreshTokenRepositoryInterface extends RepositoryInterface {}
interface OAuthApprovalRepositoryInterface extends RepositoryInterface {}
interface OAuthScopeRepositoryInterface extends RepositoryInterface {}
```

## Related Packages

- [marko/authentication](/docs/packages/authentication/) --- Core authentication guards and user-provider contracts.
- [marko/authentication-token](/docs/packages/authentication-token/) --- Personal access tokens for simple first-party API authentication.
- [marko/database](/docs/packages/database/) --- Entity and repository infrastructure used by OAuth storage.
- [marko/routing](/docs/packages/routing/) --- Attribute routing and middleware pipeline integration.
- [marko/view](/docs/packages/view/) --- View abstraction for future consent UI rendering.
