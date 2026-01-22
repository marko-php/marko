# marko/config

Type-safe configuration management with dot notation, automatic merging, and multi-tenant scope support.

## Overview

The config package provides a centralized configuration system for Marko applications. Config files are plain PHP arrays that get automatically discovered, merged by priority, and accessed through a type-safe repository. Scoped configuration enables multi-tenant applications where each tenant can have different settings while sharing common defaults.

## When to Use This Package

**Use marko/config when:**

- **Installing modules with default config** - Modules ship sensible defaults in `vendor/*/config/`, and you override just what you need in `app/config/`
- **Managing environment-specific settings** - Different database credentials, API keys, or feature flags for dev/staging/prod via `$_ENV`
- **Building multi-tenant applications** - Each tenant needs different settings (currency, locale, pricing) while sharing common defaults
- **Centralizing config access** - Inject `ConfigRepositoryInterface` anywhere instead of loading files directly

**You probably don't need this when:**

- A package loads its own config directly (e.g., `$config = require 'config/database.php'`)
- Your app is simple with no modules shipping default config to override
- You're not using environment variables for different environments

**Note:** Packages can always load config files directly with `require`—that's how `DatabaseConfig` works today. This package adds value when you need merging, scopes, or centralized access across multiple config sources.

```php
<?php
// Simple direct loading (no marko/config needed)
$config = require $paths->config . '/database.php';
$host = $config['host'];
```

## Installation

```bash
composer require marko/config
```

## Usage

### Basic Config File

Config files are PHP files that return arrays. Place them in your module's `config/` directory.

```php
<?php
// config/database.php

declare(strict_types=1);

return [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'my_app',
    'connection' => [
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],
];
```

### Accessing Configuration

Inject `ConfigRepositoryInterface` to access configuration values.

```php
<?php

declare(strict_types=1);

namespace App\Database;

use Marko\Config\ConfigRepositoryInterface;

class DatabaseConnection
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function connect(): PDO
    {
        $host = $this->config->get('database.host');
        $port = $this->config->get('database.port');
        $name = $this->config->get('database.name');

        // With default value
        $charset = $this->config->get(
            'database.connection.charset',
            'utf8mb4',
        );

        return new PDO("mysql:host={$host};port={$port};dbname={$name};charset={$charset}");
    }
}
```

### Type-Safe Accessors

Use typed accessor methods to get values with automatic type validation. These methods throw `ConfigNotFoundException` when the key is missing (unless a default is provided) and `ConfigException` on type mismatch.

```php
<?php

declare(strict_types=1);

// Get string value (throws if not found or not a string)
$host = $config->getString('database.host');

// Get with default
$driver = $config->getString('database.driver', 'mysql');

// Other typed accessors
$port = $config->getInt('database.port');
$debug = $config->getBool('app.debug');
$rate = $config->getFloat('pricing.tax_rate');
$drivers = $config->getArray('cache.available_drivers');

// Check existence before accessing
if ($config->has('feature.experimental')) {
    $enabled = $config->getBool('feature.experimental');
}
```

### Dot Notation

Access nested configuration values using dot notation. The filename becomes the top-level key.

```php
<?php
// config/database.php

declare(strict_types=1);

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'host' => 'localhost',
            'port' => 3306,
        ],
        'pgsql' => [
            'host' => 'localhost',
            'port' => 5432,
        ],
    ],
];
```

```php
<?php
// Access nested values (filename "database" is the top-level key)
$default = $config->get('database.default'); // 'mysql'
$host = $config->get('database.connections.mysql.host'); // 'localhost'
$port = $config->get('database.connections.pgsql.port'); // 5432
```

### Environment Variables

Config files are regular PHP, so you can use environment variables directly.

```php
<?php
// config/database.php

declare(strict_types=1);

return [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
    'name' => $_ENV['DB_NAME'] ?? 'my_app',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
];
```

### Scoped Configuration (Multi-tenant)

For multi-tenant applications, structure config with `default` and `scopes` keys.

```php
<?php
// config/store.php

declare(strict_types=1);

return [
    'default' => [
        'currency' => 'USD',
        'locale' => 'en_US',
        'tax_rate' => 0.08,
        'shipping' => [
            'provider' => 'ups',
            'free_threshold' => 50.00,
        ],
    ],
    'scopes' => [
        'tenant-eu' => [
            'currency' => 'EUR',
            'locale' => 'de_DE',
            'tax_rate' => 0.19,
            'shipping' => [
                'provider' => 'dhl',
            ],
        ],
        'tenant-uk' => [
            'currency' => 'GBP',
            'locale' => 'en_GB',
            'tax_rate' => 0.20,
        ],
    ],
];
```

**Important:** The `default` and `scopes` keys are special—but only when you pass a scope parameter. Without a scope, the config is accessed directly.

```php
<?php

declare(strict_types=1);

// WITHOUT scope - accesses config directly (won't find values inside 'default')
$config->get('store.currency'); // null - 'currency' is inside 'default', not at top level
$config->get('store.default.currency'); // 'USD' - explicit path works

// WITH scope - uses resolution order: scopes.{scope} → default → direct
$config->get('store.currency', scope: 'tenant-eu'); // 'EUR' (from scopes.tenant-eu)
$config->get('store.currency', scope: 'tenant-uk'); // 'GBP' (from scopes.tenant-uk)
$config->get('store.currency', scope: 'unknown');   // 'USD' (falls back to default)

// Scope-specific value with fallback to default
$config->getFloat('store.shipping.free_threshold', scope: 'tenant-eu'); // 50.00 (from default)

// Two ways to access default values directly (both work)
$config->get('store.default.shipping.provider'); // 'ups' (recommended - explicit path)
$config->get('store.shipping.provider', scope: 'default'); // 'ups' (works via fallback)
```

Create a scoped repository for cleaner code when working with a single tenant:

```php
<?php

declare(strict_types=1);

namespace App\Tenant;

use Marko\Config\ConfigRepositoryInterface;

class TenantService
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function handleRequest(string $tenantId): void
    {
        // Create a scoped repository for this tenant
        $tenantConfig = $this->config->withScope($tenantId);

        // All calls automatically use the tenant's scope
        $currency = $tenantConfig->getString('store.currency');
        $locale = $tenantConfig->getString('store.locale');
        $taxRate = $tenantConfig->getFloat('store.tax_rate');
    }
}
```

## Config File Conventions

- Config files live in `config/` directories within modules
- File names become top-level config keys (`config/database.php` -> `database.*`)
- Files must return arrays
- Use `declare(strict_types=1)` in all config files

## Merge Priority

Config files are merged in order of increasing priority:

1. **Vendor modules** (lowest priority) - `vendor/*/config/*.php`
2. **Local modules** - `modules/*/config/*.php`
3. **App config** (highest priority) - `app/config/*.php`

Later sources override earlier ones. For associative arrays, values are recursively merged. For indexed arrays, later values replace earlier ones entirely.

```php
<?php
// vendor/acme/blog/config/blog.php
return [
    'posts_per_page' => 10,
    'cache_ttl' => 3600,
];

// app/config/blog.php (overrides vendor)
return [
    'posts_per_page' => 20, // Overrides vendor value
    // cache_ttl remains 3600 from vendor
];
```

To remove a key defined by a lower-priority config, set it to `null`:

```php
<?php
// app/config/blog.php
return [
    'deprecated_feature' => null, // Removes this key entirely
];
```

## Customization via Preferences

Replace the default `ConfigRepository` implementation using Marko's Preference system.

```php
<?php

declare(strict_types=1);

namespace App\Config;

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Attributes\Preference;

#[Preference(for: ConfigRepositoryInterface::class)]
class CachedConfigRepository extends ConfigRepository
{
    private array $cache = [];

    public function get(
        string $key,
        mixed $default = null,
        ?string $scope = null,
    ): mixed {
        $cacheKey = $key . ($scope ? ":{$scope}" : '');

        if (!isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = parent::get($key, $default, $scope);
        }

        return $this->cache[$cacheKey];
    }
}
```

## API Reference

### ConfigRepositoryInterface

```php
public function get(string $key, mixed $default = null, ?string $scope = null): mixed
public function has(string $key, ?string $scope = null): bool
public function getString(string $key, ?string $default = null, ?string $scope = null): string
public function getInt(string $key, ?int $default = null, ?string $scope = null): int
public function getBool(string $key, ?bool $default = null, ?string $scope = null): bool
public function getFloat(string $key, ?float $default = null, ?string $scope = null): float
public function getArray(string $key, ?array $default = null, ?string $scope = null): array
public function all(?string $scope = null): array
public function withScope(string $scope): ConfigRepositoryInterface
```

### ConfigLoader

```php
public function load(string $filePath): array
public function loadIfExists(string $filePath): ?array
```

### ConfigMerger

```php
public function merge(array $base, array $override): array
public function mergeAll(array ...$configs): array
```

### ConfigDiscovery

```php
public function discover(array $modulePaths, string $rootConfigPath): array
```

### ConfigServiceProvider

```php
public function createRepository(array $modulePaths, string $rootConfigPath): ConfigRepositoryInterface
```

### Exceptions

- `ConfigException` - Base exception for configuration errors
- `ConfigNotFoundException` - Thrown when a required key is not found
- `ConfigLoadException` - Thrown when a config file cannot be loaded
