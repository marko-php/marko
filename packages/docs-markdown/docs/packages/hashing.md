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
return [
    'default' => $_ENV['HASH_DRIVER'] ?? 'bcrypt',

    'hashers' => [
        'bcrypt' => [
            'cost' => (int) ($_ENV['BCRYPT_COST'] ?? 12),
        ],

        'argon2id' => [
            'memory' => (int) ($_ENV['ARGON2_MEMORY'] ?? 65536),
            'time' => (int) ($_ENV['ARGON2_TIME'] ?? 4),
            'threads' => (int) ($_ENV['ARGON2_THREADS'] ?? 1),
        ],
    ],
];
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
public function verify(string $value, string $hash): bool;
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
    public function verify(string $value, string $hash): bool;
    public function needsRehash(string $hash): bool;
    public function algorithm(): string;
}
```

### Built-in Hashers

- `BcryptHasher` --- bcrypt with configurable cost (default: 12, valid range: 4--31)
- `Argon2Hasher` --- Argon2id with configurable memory (default: 65536, min: 8), time (default: 4, min: 1), and threads (default: 1, min: 1)
