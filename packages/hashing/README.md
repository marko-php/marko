# Marko Hashing

Password hashing and verification with configurable algorithms--hash passwords securely without worrying about algorithm details.

## Overview

Hashing provides a unified API for hashing and verifying passwords using bcrypt or Argon2id. The `HashManager` selects the configured default algorithm, and `needsRehash()` tells you when a stored hash should be upgraded due to algorithm or cost changes. All settings come from `config/hashing.php`.

## Installation

```bash
composer require marko/hashing
```

## Usage

### Hashing and Verifying Passwords

Inject `HashManager` and use it directly:

```php
use Marko\Hashing\HashManager;

class UserService
{
    public function __construct(
        private HashManager $hashManager,
    ) {}

    public function register(
        string $email,
        string $password,
    ): void {
        $hashedPassword = $this->hashManager->hash($password);
        // Store $hashedPassword in database
    }

    public function authenticate(
        string $password,
        string $storedHash,
    ): bool {
        return $this->hashManager->verify($password, $storedHash);
    }
}
```

### Rehashing on Login

Upgrade hashes transparently when algorithm or cost settings change:

```php
if ($this->hashManager->verify($password, $user->passwordHash)) {
    if ($this->hashManager->needsRehash($user->passwordHash)) {
        $user->passwordHash = $this->hashManager->hash($password);
        // Persist updated hash
    }
}
```

### Using a Specific Algorithm

Request a hasher by name instead of the configured default:

```php
$argonHasher = $this->hashManager->hasher('argon2id');
$hash = $argonHasher->hash($password);
```

## Customization

Replace the `HashManager` via Preference to add custom hashers or change selection logic:

```php
use Marko\Core\Attributes\Preference;
use Marko\Hashing\HashManager;

#[Preference(replaces: HashManager::class)]
class MyHashManager extends HashManager
{
    // Custom hasher resolution logic
}
```

## API Reference

### HashManager

```php
public function hash(string $value): string;
public function verify(string $value, string $hash): bool;
public function needsRehash(string $hash): bool;
public function hasher(?string $name = null): HasherInterface;
public function has(string $name): bool;
```

### HasherInterface

```php
interface HasherInterface
{
    public function hash(string $value): string;
    public function verify(string $value, string $hash): bool;
    public function needsRehash(string $hash): bool;
    public function algorithm(): string;
}
```

### Built-in Hashers

- `BcryptHasher` -- bcrypt with configurable cost (default: 12)
- `Argon2Hasher` -- Argon2id with configurable memory, time, and threads
