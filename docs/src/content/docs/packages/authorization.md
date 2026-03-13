---
title: marko/authorization
description: Gates, policies, and the #[Can] attribute -- control who can do what with expressive, testable authorization checks.
---

Gates, policies, and the `#[Can]` attribute --- control who can do what with expressive, testable authorization checks. Define abilities with closures via the Gate, or organize them into policy classes mapped to entities. Use `#[Can]` on controller methods to enforce permissions automatically via middleware. Denials throw `AuthorizationException` with clear context.

## Installation

```bash
composer require marko/authorization
```

## Usage

### Defining Abilities

Register abilities as closures on the Gate:

```php
use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Contracts\GateInterface;

class AuthorizationBootstrap
{
    public function __construct(
        private readonly GateInterface $gate,
    ) {}

    public function boot(): void
    {
        $this->gate->define(
            'edit-settings',
            fn (?AuthorizableInterface $user) => $user?->can('admin', true) ?? false,
        );
    }
}
```

### Checking Abilities

```php
use Marko\Authorization\Contracts\GateInterface;

class SettingsController
{
    public function __construct(
        private readonly GateInterface $gate,
    ) {}

    public function update(): void
    {
        if ($this->gate->denies('edit-settings')) {
            // handle unauthorized
        }

        // proceed with update
    }
}
```

Use `authorize()` to throw on denial:

```php
$this->gate->authorize('edit-settings');
// Throws AuthorizationException if denied
```

### Using Policies

Policies group authorization logic per entity. Create a policy class with methods named after abilities:

```php
use Marko\Authorization\AuthorizableInterface;

class PostPolicy
{
    public function update(
        ?AuthorizableInterface $user,
        Post $post,
    ): bool {
        return $user !== null && $user->getAuthIdentifier() === $post->authorId;
    }

    public function delete(
        ?AuthorizableInterface $user,
        Post $post,
    ): bool {
        return $user !== null && $user->getAuthIdentifier() === $post->authorId;
    }
}
```

Register the policy:

```php
$this->gate->policy(
    Post::class,
    PostPolicy::class,
);
```

Check against the policy by passing the entity:

```php
$this->gate->authorize('update', $post);
```

### The #[Can] Attribute

Add `#[Can]` to controller methods to enforce authorization via middleware:

```php
use Marko\Authorization\Attributes\Can;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

class PostController
{
    #[Get('/posts/{id}/edit')]
    #[Can(ability: 'edit', entityClass: Post::class)]
    public function edit(
        int $id,
    ): Response {
        // Only reachable if authorized
    }
}
```

The `AuthorizationMiddleware` reads `#[Can]` attributes and checks the Gate automatically. It returns a `401 Unauthorized` response for unauthenticated users and a `403 Forbidden` response for authenticated users who lack the required ability. JSON responses are returned when the request's `Accept` header contains `application/json`.

### Implementing AuthorizableInterface

Your user entity must implement `AuthorizableInterface`, which extends [`AuthenticatableInterface`](/docs/packages/authentication/):

```php
use Marko\Authorization\AuthorizableInterface;

class User implements AuthorizableInterface
{
    public function can(
        string $ability,
        mixed ...$arguments,
    ): bool {
        // Check ability via gate or custom logic
    }

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return $this->passwordHash;
    }
}
```

## API Reference

### GateInterface

```php
interface GateInterface
{
    public function define(string $ability, callable $callback): void;
    public function allows(string $ability, mixed ...$arguments): bool;
    public function denies(string $ability, mixed ...$arguments): bool;
    public function authorize(string $ability, mixed ...$arguments): bool;
    public function policy(string $entityClass, string $policyClass): void;
}
```

### AuthorizableInterface

```php
interface AuthorizableInterface extends AuthenticatableInterface
{
    public function can(string $ability, mixed ...$arguments): bool;
}
```

### #[Can] Attribute

```php
#[Can(ability: 'edit', entityClass: Post::class)]  // With entity
#[Can(ability: 'access-dashboard')]                  // Without entity
```

### AuthorizationMiddleware

```php
class AuthorizationMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
```

### AuthorizationException

```php
class AuthorizationException extends Exception
{
    public function __construct(
        string $message,
        string $ability = '',
        string $resource = '',
        string $context = '',
        string $suggestion = '',
    );

    public function getAbility(): string;
    public function getResource(): string;
    public function getContext(): string;
    public function getSuggestion(): string;

    public static function forbidden(string $ability, string $resource): self;
    public static function missingPolicy(string $entityClass, string $ability): self;
}
```
