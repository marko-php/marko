---
title: marko/hashing
description: Password hashing and verification with configurable algorithms --- hash passwords securely without worrying about algorithm details.
---

Password hashing and verification with configurable algorithms --- hash passwords securely without worrying about algorithm details. Hashing provides a unified API for hashing and verifying passwords using bcrypt or Argon2id. The `HashManager` selects the configured default algorithm, and `needsRehash()` tells you when a stored hash should be upgraded due to algorithm or cost changes. All settings come from `config/hashing.php`.

## Installation

```bash
composer require marko/hashing
```

## Configuration

```php title="config/hashing.php"
use Marko\Config\Env;

return [
    'default' => Env::string('HASH_DRIVER', 'bcrypt'),

    'hashers' => [
        'bcrypt' => [
            'cost' => Env::int('BCRYPT_COST', 12, min: 4, max: 31),
        ],

        'argon2id' => [
            'memory' => Env::int('ARGON2_MEMORY', 65536, min: 8),
            'time' => Env::int('ARGON2_TIME', 4, min: 1),
            'threads' => Env::int('ARGON2_THREADS', 1, min: 1),
        ],
    ],
];
```

Out-of-range or non-numeric values throw a `ConfigException` at boot (`BCRYPT_COST` must be 4-31, `ARGON2_MEMORY` at least 8, `ARGON2_TIME` and `ARGON2_THREADS` at least 1).

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

### Bcrypt Length Limit

Bcrypt only uses the first 72 bytes of a value and cannot hash a NUL (`\0`) byte. Rather than silently truncate, `BcryptHasher::hash()` throws `InvalidValueException` for a value longer than 72 bytes (`BcryptHasher::MAX_VALUE_BYTES`) or containing a NUL byte. `verify()` returns `false` for such a value and never throws, since no stored bcrypt hash can match it. Before returning `false` it still verifies the value against a dummy hash of the configured cost, so rejecting an over-long or NUL-containing value takes as long as checking a wrong password and `HashManager::verify()` leaks no timing difference between the two.

The limit is in bytes, not characters: a password of multibyte UTF-8 characters reaches it sooner. Validate password length with `strlen()` on registration and password change so users get a form error instead of an exception:

```php
use Marko\Hashing\Hash\BcryptHasher;

if (
    strlen($password) > BcryptHasher::MAX_VALUE_BYTES
    || str_contains($password, "\0")
) {
    // Reject with a validation error
}
```

If you want to allow longer passphrases, set `hashing.default` to `argon2id`, which has no length limit.

### Rehashing on Login

With [marko/authentication](/docs/packages/authentication/#password-hashing) installed you get this for free: its `PasswordHasherInterface` is backed by `HashManager`, so `HASH_DRIVER` decides how login passwords are hashed, and `SessionGuard::attempt()` rehashes and saves a user's password whenever `needsRehash()` reports it out of date. Switching `hashing.default` from `bcrypt` to `argon2id` keeps existing bcrypt users able to log in and moves each one to Argon2id on their next login.

Elsewhere, upgrade hashes transparently when algorithm or cost settings change:

```php
use Marko\Hashing\HashManager;

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
use Marko\Hashing\HashManager;

$argonHasher = $this->hashManager->hasher('argon2id');
$hash = $argonHasher->hash($password);
```

### Customization

Replace the `HashManager` via [Preference](/docs/packages/core/) to add custom hashers or change selection logic:

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
use Marko\Hashing\HashManager;

public function hash(string $value): string;
public function verify(
    string $value,
    string $hash,
): bool;
public function needsRehash(string $hash): bool;
public function hasher(?string $name = null): HasherInterface;
public function has(string $name): bool;
```

### HasherInterface

```php
use Marko\Hashing\Contracts\HasherInterface;

interface HasherInterface
{
    public function hash(string $value): string;
    public function verify(
        string $value,
        string $hash,
    ): bool;
    public function needsRehash(string $hash): bool;
    public function algorithm(): string;
}
```

### Built-in Hashers

- `BcryptHasher` --- bcrypt with configurable cost (default: 12, valid range: 4--31); rejects values over 72 bytes or containing a NUL byte (see [Bcrypt Length Limit](#bcrypt-length-limit))
- `Argon2Hasher` --- Argon2id with configurable memory (default: 65536, min: 8), time (default: 4, min: 1), and threads (default: 1, min: 1)
