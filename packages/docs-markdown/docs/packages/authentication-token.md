---
title: marko/authentication-token
description: Stateless API token authentication --- issue personal access tokens with scoped abilities for mobile apps, SPAs, and third-party integrations.
---

Stateless API token authentication --- issue personal access tokens with scoped abilities for mobile apps, SPAs, and third-party integrations. This package adds personal access token authentication to the Marko Framework. Each token is stored as a SHA-256 hash; the plain-text value is only available once at creation time. Tokens can be scoped to a list of abilities so you can issue limited-permission tokens for specific integrations. `TokenGuard` authenticates API requests by reading the `Authorization: Bearer` header.

## Installation

```bash
composer require marko/authentication-token
```

## Configuration

Override defaults in `config/authentication-token.php`:

```php title="config/authentication-token.php"
return [
    // Days before a token expires. null = never expires.
    'token_expiration_days' => 365,
];
```

## Usage

### Creating a Token

Inject `TokenManager` and call `createToken()`. Capture `plainTextToken` immediately --- it is never retrievable again.

```php title="ApiTokenController.php"
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
}
```

> **Security note:** The plain-text token is available only on `NewAccessToken::$plainTextToken` at creation time. The database stores only a SHA-256 hash. If you lose the plain text, you must revoke and re-issue.

### Bearer Token Authentication Flow

Clients send the token in the `Authorization` header on every request:

```
Authorization: Bearer <plain-text-token>
```

`TokenGuard` extracts and hashes the token, looks it up in `personal_access_tokens`, and resolves the user via the configured user provider:

```php title="ApiController.php"
use Marko\AuthenticationToken\Guard\TokenGuard;

class ApiController
{
    public function __construct(
        private TokenGuard $tokenGuard,
    ) {}

    public function index(): Response
    {
        if ($this->tokenGuard->guest()) {
            return new Response('Unauthorized', 401);
        }

        $user = $this->tokenGuard->user(); // resolved from Bearer token

        return new Response("Hello, user {$user->getAuthIdentifier()}");
    }
}
```

### Checking Token Abilities

After authentication, check whether the resolved token has a specific ability:

```php
public function update(
    Request $request,
): Response {
    if (!$this->tokenGuard->hasAbility('posts:write')) {
        return new Response('Forbidden', 403);
    }

    // proceed with update
}
```

Abilities are stored as a JSON array on the token record. Pass an empty `abilities` array to `createToken()` for a token with no scoping (full access).

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
| `expires_at` | datetime | Expiry timestamp (null = never) |
| `created_at` | datetime | Creation timestamp |

## API Reference

### TokenManager

```php
public function createToken(AuthenticatableInterface $user, string $name, array $abilities = []): NewAccessToken;
public function revokeToken(int $tokenId): void;
public function revokeAllTokens(AuthenticatableInterface $user): void;
```

### TokenGuard

```php
public function check(): bool;
public function guest(): bool;
public function user(): ?AuthenticatableInterface;
public function id(): int|string|null;
public function hasAbility(string $ability): bool;
public function extractToken(): ?string;
public function getName(): string;
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
InvalidTokenException::forToken(string $token): self;
ExpiredTokenException::forToken(string $token, DateTimeInterface $expiredAt): self;
```

Both extend `TokenException`, which exposes `getContext(): string` and `getSuggestion(): string` for detailed error messages.

## Related Packages

- [marko/authentication](/docs/packages/authentication/) --- Core authentication framework with guards, user providers, middleware, and events.
