# Plan: Hashing Package (marko/hashing)

## Created
2026-01-21

## Status
pending

## Objective
Create the `marko/hashing` package providing a unified, extensible hashing system for Marko applications. The package will deliver multiple hashing algorithms (bcrypt, Argon2id) through a pluggable manager interface, support configurable parameters for each algorithm, and integrate seamlessly with the application's authentication and security workflows.

## Scope

### In Scope
- `HasherInterface` - contract for hashing operations (hash, verify, needsRehash)
- `BcryptHasher` - bcrypt implementation using PHP's password_hash() and password_verify()
- `Argon2Hasher` - Argon2id implementation using password_hash() and password_verify()
- `HashManager` - manages multiple hashers, provides default hasher access
- `HashConfig` - configuration loaded from config/hashing.php
- `HasherFactory` - factory for creating hasher instances with config parameters
- Configuration support with cost/memory/time parameters per algorithm
- hash(), verify(), needsRehash() operations on all hashers
- Configurable default hasher selection
- Loud errors when hashers are misconfigured or not available
- Exception classes with helpful error messages
- Package structure following Marko conventions

### Out of Scope
- HMAC-based hashing (not password hashing)
- Salted hashing (PHP's password_hash handles salts automatically)
- Custom hashing algorithms (users can extend with Preferences)
- Encryption (separate security concern)
- API key/token generation (future package)
- Password reset token generation (authentication concern)
- Rate limiting (security middleware concern)

## Success Criteria
- [ ] `HasherInterface` defines hash(), verify(), needsRehash() contract
- [ ] `BcryptHasher` hashes with configurable cost parameter
- [ ] `Argon2Hasher` hashes with configurable memory, time, and threads parameters
- [ ] `HashManager` provides access to all registered hashers
- [ ] `HashConfig` loads and validates configuration from config/hashing.php
- [ ] Default hasher is configurable and accessible via `$manager->hash()`
- [ ] needsRehash() indicates when a hash needs updating due to algorithm/cost changes
- [ ] Both hashers correctly verify hashes they've created
- [ ] Cross-verification works (hash from one, verify with other if same algo)
- [ ] HasherNotFoundException thrown with helpful message when hasher not found
- [ ] InvalidHasherConfigException thrown for misconfigured parameters
- [ ] PHP version requirements checked at boot (Argon2 >= 7.2, bcrypt >= 5.3)
- [ ] All tests passing
- [ ] Code follows project standards (strict types, no final, etc.)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding (composer.json, module.php) | - | pending |
| 002 | Exception classes (HasherException, HasherNotFoundException, InvalidHasherConfigException) | 001 | pending |
| 003 | HasherInterface contract | 001 | pending |
| 004 | HashConfig class (loads and validates config) | 001, 002 | pending |
| 005 | BcryptHasher implementation | 003, 004 | pending |
| 006 | Argon2Hasher implementation | 003, 004 | pending |
| 007 | HasherFactory (instantiates hashers with config) | 004, 005, 006 | pending |
| 008 | HashManager implementation | 003, 007 | pending |
| 009 | Module bindings (module.php with HasherInterface binding) | 008 | pending |
| 010 | Config file template (config/hashing.php example) | 004 | pending |
| 011 | Unit tests for BcryptHasher | 005 | pending |
| 012 | Unit tests for Argon2Hasher | 006 | pending |
| 013 | Unit tests for HashManager | 008 | pending |
| 014 | Unit tests for HashConfig | 004 | pending |
| 015 | Integration tests (multiple hashers, switching algorithms) | 008, 011, 012, 013 | pending |

## Architecture Notes

### Package Structure
```
packages/hashing/
  src/
    Contracts/
      HasherInterface.php
    Exceptions/
      HasherException.php
      HasherNotFoundException.php
      InvalidHasherConfigException.php
    Hash/
      BcryptHasher.php
      Argon2Hasher.php
    Config/
      HashConfig.php
    Factory/
      HasherFactory.php
    HashManager.php
  tests/
    Unit/
      Hash/
        BcryptHasherTest.php
        Argon2HasherTest.php
      HashManagerTest.php
      HashConfigTest.php
    Feature/
      HashingIntegrationTest.php
  composer.json
  module.php
```

### HasherInterface Contract
```php
declare(strict_types=1);

namespace Marko\Hashing\Contracts;

interface HasherInterface
{
    /**
     * Hash a value using the hasher's algorithm.
     */
    public function hash(string $value): string;

    /**
     * Verify a value against a hash.
     */
    public function verify(string $value, string $hash): bool;

    /**
     * Check if a hash needs to be rehashed due to algorithm/cost changes.
     */
    public function needsRehash(string $hash): bool;

    /**
     * Get the algorithm name for this hasher.
     */
    public function algorithm(): string;
}
```

### BcryptHasher Implementation
```php
declare(strict_types=1);

namespace Marko\Hashing\Hash;

use Marko\Hashing\Contracts\HasherInterface;
use Marko\Hashing\Exceptions\InvalidHasherConfigException;

readonly class BcryptHasher implements HasherInterface
{
    public const int DEFAULT_COST = 12;
    private int $cost;

    public function __construct(?int $cost = null)
    {
        $this->cost = $cost ?? self::DEFAULT_COST;
        $this->validateCost();
    }

    public function hash(string $value): string
    {
        $hashed = password_hash($value, PASSWORD_BCRYPT, ['cost' => $this->cost]);

        if ($hashed === false) {
            throw new InvalidHasherConfigException(
                message: 'Failed to hash value with bcrypt',
                context: "Cost: {$this->cost}, PHP version: " . phpversion(),
                suggestion: 'Ensure PHP version is >= 5.3 and cost is between 4 and 31',
            );
        }

        return $hashed;
    }

    public function verify(string $value, string $hash): bool
    {
        return password_verify($value, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    public function algorithm(): string
    {
        return 'bcrypt';
    }

    private function validateCost(): void
    {
        if ($this->cost < 4 || $this->cost > 31) {
            throw new InvalidHasherConfigException(
                message: 'Invalid bcrypt cost parameter',
                context: "Cost must be between 4 and 31, got {$this->cost}",
                suggestion: 'Update config/hashing.php bcrypt cost to a value between 4 and 31',
            );
        }
    }
}
```

### Argon2Hasher Implementation
```php
declare(strict_types=1);

namespace Marko\Hashing\Hash;

use Marko\Hashing\Contracts\HasherInterface;
use Marko\Hashing\Exceptions\InvalidHasherConfigException;

readonly class Argon2Hasher implements HasherInterface
{
    public const int DEFAULT_MEMORY = 65536;
    public const int DEFAULT_TIME = 4;
    public const int DEFAULT_THREADS = 1;

    private int $memory;
    private int $time;
    private int $threads;

    public function __construct(?int $memory = null, ?int $time = null, ?int $threads = null)
    {
        $this->memory = $memory ?? self::DEFAULT_MEMORY;
        $this->time = $time ?? self::DEFAULT_TIME;
        $this->threads = $threads ?? self::DEFAULT_THREADS;
        $this->validate();
    }

    public function hash(string $value): string
    {
        $hashed = password_hash($value, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memory,
            'time_cost' => $this->time,
            'threads' => $this->threads,
        ]);

        if ($hashed === false) {
            throw new InvalidHasherConfigException(
                message: 'Failed to hash value with Argon2id',
                context: "Memory: {$this->memory}, Time: {$this->time}, Threads: {$this->threads}",
                suggestion: 'Ensure PHP version is >= 7.2 with Argon2 support and parameters are valid',
            );
        }

        return $hashed;
    }

    public function verify(string $value, string $hash): bool
    {
        return password_verify($value, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->memory,
            'time_cost' => $this->time,
            'threads' => $this->threads,
        ]);
    }

    public function algorithm(): string
    {
        return 'argon2id';
    }

    private function validate(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            throw new InvalidHasherConfigException(
                message: 'Argon2 is not available in this PHP installation',
                context: 'PASSWORD_ARGON2ID constant not defined',
                suggestion: 'Upgrade to PHP >= 7.2 or install Argon2 support',
            );
        }

        if ($this->memory < 8) {
            throw new InvalidHasherConfigException(
                message: 'Invalid Argon2 memory parameter',
                context: "Memory must be >= 8, got {$this->memory}",
                suggestion: 'Update config/hashing.php argon2id memory to >= 8',
            );
        }

        if ($this->time < 1) {
            throw new InvalidHasherConfigException(
                message: 'Invalid Argon2 time parameter',
                context: "Time must be >= 1, got {$this->time}",
                suggestion: 'Update config/hashing.php argon2id time to >= 1',
            );
        }

        if ($this->threads < 1) {
            throw new InvalidHasherConfigException(
                message: 'Invalid Argon2 threads parameter',
                context: "Threads must be >= 1, got {$this->threads}",
                suggestion: 'Update config/hashing.php argon2id threads to >= 1',
            );
        }
    }
}
```

### HashManager Implementation
```php
declare(strict_types=1);

namespace Marko\Hashing;

use Marko\Hashing\Contracts\HasherInterface;
use Marko\Hashing\Config\HashConfig;
use Marko\Hashing\Exceptions\HasherNotFoundException;
use Marko\Hashing\Factory\HasherFactory;

class HashManager
{
    /** @var array<string, HasherInterface> */
    private array $hashers = [];

    public function __construct(
        private readonly HashConfig $config,
        private readonly HasherFactory $factory,
    ) {}

    public function hash(string $value): string
    {
        return $this->hasher()->hash($value);
    }

    public function verify(string $value, string $hash): bool
    {
        return $this->hasher()->verify($value, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return $this->hasher()->needsRehash($hash);
    }

    public function hasher(string $name = null): HasherInterface
    {
        $name = $name ?? $this->config->defaultHasher();

        if (!isset($this->hashers[$name])) {
            $this->hashers[$name] = $this->factory->make($name);
        }

        return $this->hashers[$name];
    }

    public function has(string $name): bool
    {
        try {
            $this->hasher($name);
            return true;
        } catch (HasherNotFoundException) {
            return false;
        }
    }
}
```

### Configuration File
```php
// config/hashing.php
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

### Module Bindings
```php
// packages/hashing/module.php
declare(strict_types=1);

use Marko\Hashing\Config\HashConfig;
use Marko\Hashing\Contracts\HasherInterface;
use Marko\Hashing\Factory\HasherFactory;
use Marko\Hashing\HashManager;

return [
    'enabled' => true,
    'bindings' => [
        HashConfig::class => HashConfig::class,
        HasherFactory::class => HasherFactory::class,
        HashManager::class => HashManager::class,
        HasherInterface::class => static function (ContainerInterface $container): HasherInterface {
            return $container->get(HashManager::class)->hasher();
        },
    ],
];
```

### Usage Examples

**Basic Hashing:**
```php
$hashManager = $container->get(HashManager::class);

// Hash a password
$hashed = $hashManager->hash('user-password');

// Verify a password
$isValid = $hashManager->verify('user-password', $hashed);

// Check if rehash needed (cost changed, algorithm changed)
if ($hashManager->needsRehash($storedHash)) {
    $newHash = $hashManager->hash('user-password');
}
```

**Using Specific Hashers:**
```php
$bcrypt = $hashManager->hasher('bcrypt');
$argon2 = $hashManager->hasher('argon2id');

$bcryptHash = $bcrypt->hash('password');
$argon2Hash = $argon2->hash('password');
```

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| **Password hashes from one algorithm can't be verified by another** | Each hasher includes algorithm identifier in hash; verify only works with correct algo |
| **Weak bcrypt cost allows fast brute forcing** | Default cost of 12, documentation recommends at least 12, loud error if < 4 |
| **Argon2 not available in older PHP versions** | Validation at config time throws loud error; documentation requires PHP >= 7.2 |
| **Configuration errors cause runtime failures** | HashConfig and HasherFactory validate all params; loud errors with suggestions |
| **Factory creates many hasher instances** | Manager caches hasher instances per algorithm; minimal memory overhead |
| **Regression if password hashing changes** | Comprehensive test suite covers hash/verify/needsRehash for both algorithms |
