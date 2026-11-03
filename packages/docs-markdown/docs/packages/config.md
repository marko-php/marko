---
title: marko/config
description: Type-safe configuration management with dot notation, automatic merging, and multi-tenant scope support.
---

The config package provides a centralized configuration system for Marko applications. Config files are plain PHP arrays that get automatically discovered, merged by priority, and accessed through a type-safe repository. Scoped configuration enables multi-tenant applications where each tenant can have different settings while sharing common defaults.

## When to Use This Package

**Use marko/config when:**

- **Installing modules with default config** — Modules ship sensible defaults in `vendor/*/config/`, and you override just what you need in `app/config/`
- **Managing environment-specific settings** — Different database credentials, API keys, or feature flags for dev/staging/prod via `$_ENV`
- **Building multi-tenant applications** — Each tenant needs different settings (currency, locale, pricing) while sharing common defaults
- **Centralizing config access** — Inject `ConfigRepositoryInterface` anywhere instead of loading files directly

**You probably don't need this when:**

- A package loads its own config directly (e.g., `$config = require 'config/database.php'`)
- Your app is simple with no modules shipping default config to override
- You're not using environment variables for different environments

**Note:** Packages can always load config files directly with `require` — that's how `DatabaseConfig` works today. This package adds value when you need merging, scopes, or centralized access across multiple config sources.

```php
// Simple direct loading (no marko/config needed)
$config = require $paths->config . '/database.php';
$host = $config['host'];
```

## Installation

```bash
composer require marko/config
```

## Usage

### Design Philosophy

Config files are the **single source of truth**. All getter methods throw `ConfigNotFoundException` when a key is missing — there are no default parameter fallbacks. This ensures:

- Missing config fails loudly during development
- All configurable values are documented in config files
- No hidden defaults scattered through application code

If you need a default value, define it in the config file.

### Basic Config File

Config files are PHP files that return arrays. Place them in your module's `config/` directory.

```php title="config/database.php"
<?php

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
        private ConfigRepositoryInterface $configRepository,
    ) {}

    public function connect(): PDO
    {
        $host = $this->configRepository->get('database.host');
        $port = $this->configRepository->get('database.port');
        $name = $this->configRepository->get('database.name');
        $charset = $this->configRepository->get('database.connection.charset');

        return new PDO("mysql:host={$host};port={$port};dbname={$name};charset={$charset}");
    }
}
```

The repository is a shared instance (a singleton). Config files are discovered and loaded once, the first time anything resolves `ConfigRepositoryInterface`, and every class that injects it receives that same instance. The repository is read-only, so sharing it is also safe in long-running workers such as [marko/roadrunner](/docs/packages/roadrunner/). Because config is loaded once, changes to environment variables after that point are not picked up until the next process boot.

### Type-Safe Accessors

Use typed accessor methods to get values with automatic type validation. These methods throw `ConfigNotFoundException` when the key is missing and `ConfigException` on type mismatch.

`getInt()` accepts an `int` or a string of digits (`'8080'`); a float, `'1.5'` or `'1e3'` throws. `getBool()` accepts a `bool`, the ints `0` and `1`, or the strings `Env::bool()` accepts (`true`/`false`/`1`/`0`/`yes`/`no`/`on`/`off`, case-insensitive), so `'off'` reads as `false`. Any other value throws instead of being cast.

```php
<?php

declare(strict_types=1);

// Get string value (throws if not found or not a string)
$host = $config->getString('database.host');
$driver = $config->getString('database.driver');

// Other typed accessors
$port = $config->getInt('database.port');
$debug = $config->getBool('app.debug');
$rate = $config->getFloat('pricing.tax_rate');
$drivers = $config->getArray('cache.available_drivers');

// Check existence before accessing optional config
if ($config->has('feature.experimental')) {
    $enabled = $config->getBool('feature.experimental');
}
```

### Dot Notation

Access nested configuration values using dot notation. The filename becomes the top-level key.

```php title="config/mail.php"
<?php

declare(strict_types=1);

return [
    'driver' => 'smtp',
    'from' => [
        'address' => 'hello@example.com',
        'name' => 'My App',
    ],
];
```

```php
<?php
// Access nested values (filename "mail" is the top-level key)
$driver = $config->get('mail.driver'); // 'smtp'
$address = $config->get('mail.from.address'); // 'hello@example.com'
$name = $config->get('mail.from.name'); // 'My App'
```

### Environment Variables

Read environment variables in config files with `Marko\Config\Env`. Each method returns a typed value and throws `ConfigException` when the variable holds something it can't parse, so a typo stops the boot instead of quietly becoming `0`, `false` or `true`. Config files load before the container exists, so `Env` is a plain class with static methods: import it and call it.

```php title="config/database.php"
<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    'host' => Env::string('DB_HOST', 'localhost'),
    'port' => Env::int('DB_PORT', 3306, min: 1, max: 65535),
    'name' => Env::string('DB_NAME', 'my_app'),
    'username' => Env::string('DB_USERNAME', 'root'),
    'password' => Env::string('DB_PASSWORD', ''),
];
```

| Method | Returns | Accepts |
|---|---|---|
| `Env::string($name, $default)` | `string` | Any value, returned as is |
| `Env::nullableString($name, $default = null)` | `?string` | Any value, returned as is |
| `Env::int($name, $default, min:, max:)` | `int` | Whole numbers written with digits, optionally signed (`3600`, `-5`). `abc`, `10s`, `1h`, `1.5` and `1e3` throw. |
| `Env::nullableInt($name, $default = null, min:, max:)` | `?int` | Same as `int()`; returns `null` when unset |
| `Env::float($name, $default, min:, max:)` | `float` | Numbers with a dot as the decimal separator (`1.5`, `2`, `1e3`). `abc`, `1,5`, `inf` and `nan` throw. |
| `Env::bool($name, $default)` | `bool` | `true`, `false`, `1`, `0`, `yes`, `no`, `on`, `off`, case-insensitive and trimmed. Anything else (`ture`, `enabled`) throws. |
| `Env::list($name, $default)` | `list<string>` | A comma-separated value. Items are trimmed and empty items are dropped (`a, b,,c` is `['a', 'b', 'c']`). |

How values are read:

- `$_ENV` is checked first, then `getenv()`. When [marko/env](/docs/packages/env/) is installed, real environment variables and `.env` entries are in `$_ENV` at boot, even when PHP's `variables_order` setting lacks `E`.
- **An unset variable or an empty value returns the default.** `KEY=` in `.env` means "use the default", not "use an empty string". The typed readers (`int`, `nullableInt`, `float`, `bool`, `list`) also treat a value of only spaces as unset. To make a string empty when its default isn't, change the value in your app's config file instead of the environment.
- The default is returned as written and isn't checked against `min`/`max`.
- `min:` and `max:` are optional named arguments. Use them to state the valid range where the value is read: `Env::int('PAGE_CACHE_TTL', 3600, min: 0)` rejects `-1`.

A rejected value throws `ConfigException`. The message names the variable, the context shows the value and the suggestion lists the accepted forms. When the rejection happens while a config file loads, `ConfigLoader` rethrows it as a `ConfigLoadException` (a `ConfigException` subclass) whose message also names the file that read the variable. The original exception is its `getPrevious()`:

```text
Environment variable "PAGE_CACHE_TTL" must be an integer [file: /app/config/page-cache.php]
Got "1h"
Set PAGE_CACHE_TTL to a whole number written with digits only (no units, decimals or exponents) of 0 or greater, or remove it to use the default (3600).
```

Don't cast `$_ENV` values (`(int) ($_ENV['PORT'] ?? 80)`) or parse them with `filter_var()`: `(int) 'abc'` is `0` and an unrecognised `filter_var()` boolean is `false`, with no error. Every config file Marko ships reads its environment variables through `Env`. The global `env()` helper from `marko/env` was [removed in 0.9.0](/docs/packages/env/#removed-in-090-the-env-helper); that section maps each of its coercions to an `Env` method.

### Scoped Configuration (Multi-tenant)

For multi-tenant applications, structure config with `default` and `scopes` keys.

```php title="config/store.php"
<?php

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

**Important:** The `default` and `scopes` keys are special — but only when you pass a scope parameter. Without a scope, the config is accessed directly.

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
        private ConfigRepositoryInterface $configRepository,
    ) {}

    public function handleRequest(string $tenantId): void
    {
        // Create a scoped repository for this tenant
        $tenantConfig = $this->configRepository->withScope($tenantId);

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
- **Default values belong in config files, not hardcoded in code** — Config files are the single source of truth. If a config key is missing, it should fail loudly, not fall back to a hardcoded default.

```php title="config/blog.php"
<?php
return [
    'posts_per_page' => 10,
    'site_name' => 'My Blog',
];
```

```php
<?php
// CORRECT - no fallback, config file is the source of truth
public function getPostsPerPage(): int
{
    return $this->configRepository->getInt('blog.posts_per_page');
}

// WRONG - hardcoded fallback hides missing config
public function getPostsPerPage(): int
{
    return $this->configRepository->getInt('blog.posts_per_page', 10);
}
```

## Merge Priority

Config files are merged in order of increasing priority:

1. **Vendor modules** (lowest priority) — `vendor/*/config/*.php`
2. **Local modules** — `modules/*/config/*.php`
3. **App config** (highest priority) — `app/config/*.php`

Later sources override earlier ones. For associative arrays, values are recursively merged. For indexed arrays, later values replace earlier ones entirely.

```php title="vendor/acme/blog/config/blog.php"
<?php
return [
    'posts_per_page' => 10,
    'cache_ttl' => 3600,
];
```

```php title="app/config/blog.php"
<?php
return [
    'posts_per_page' => 20, // Overrides vendor value
    // cache_ttl remains 3600 from vendor
];
```

`null` is an ordinary value. Setting a key to `null` overrides the lower-priority value with `null` and keeps the key, so `has()` returns `true` and `get()` returns `null`. This is what an `Env::nullableString()` that reads an unset variable produces, and what package defaults such as `'secure' => null` rely on.

To remove a key defined by a lower-priority config, set it to `new RemoveKey()`:

```php title="app/config/blog.php"
<?php

use Marko\Config\RemoveKey;

return [
    'deprecated_feature' => new RemoveKey(), // Removes this key entirely
    'cache_ttl' => null, // Keeps the key, with the value null
];
```

The merger drops every `RemoveKey` marker, including ones for keys no lower-priority file defines, so a marker is never stored as a config value. Reading a removed key throws `ConfigNotFoundException`, the same as any key that was never defined.

## Config Caching

In production, `marko discovery:cache` also compiles your config. `marko/config` declares `Marko\Config\Discovery\ConfigCacheContributor` under the `discovery` key of its `module.php`, so the [discovery cache](/docs/packages/core/#discovery-cache) gets a `config` section holding the merged result of every config file. On a cached boot, `ConfigRepositoryInterface` is built from that section: no config directory is scanned, no config file is `require`d and nothing is merged. `ConfigRepository` behaves the same either way.

There's no separate `config:cache` command. Run `discovery:cache` on every deploy (see [Deploying to production](/docs/packages/core/#deploying-to-production)), and `marko discovery:clear` to go back to loading config live. In a development environment the discovery cache is always bypassed, so config edits take effect on the next request.

### Config values must be plain data

The cache is written with `var_export()`, so config values may only be strings, numbers, booleans, `null` and arrays. A closure or object anywhere in the merged config makes `discovery:cache` fail with a `ConfigCacheException` naming the config key and the file it came from:

```text
Config value 'payments.resolver' in /app/config/payments.php is a Closure and cannot be stored in the discovery cache
```

Store a class name instead and resolve it through the container where you use it. A closure in a module's config file is fine when a later file (your app config, say) replaces it with a plain value.

### Environment variables are fixed when the cache is built

Config files run once, during `discovery:cache`, so every value they read from the environment is fixed at that moment. To keep that from going unnoticed, every variable a config file reads while the cache is compiled is recorded, and the `config` section stores those names with a hash of their values. That covers reads through `Env` and the variables the core-owned `config/discovery.php` reads through `DiscoveryEnvironment`: `MARKO_ENV`, `APP_ENV`, `DISCOVERY_CACHE_ENABLED` and `DISCOVERY_CACHE_PATH`. On a cached boot the hashes are recomputed from the current environment (no file reads). If any differ, boot throws `ConfigCacheException`, a `DiscoveryCacheException`, naming the changed variables:

```text
Environment variables changed since the discovery cache was built: DB_PASSWORD, MAIL_HOST
```

Run `marko discovery:cache` again after changing an environment variable. Config is never rebuilt silently and stale values are never used.

- `.env` is still loaded on a cached boot: the boot gate reads `APP_ENV` and `DISCOVERY_CACHE_ENABLED` before the cache is loaded. What a cached boot skips is evaluating config files.
- Reads through `Env`, `AppEnvironment` and `DiscoveryEnvironment` are tracked. A config file that reads `$_ENV[...]` or `getenv()` directly has its value fixed without any check, which is one more reason to [read environment variables with `Env`](#environment-variables).
- The cache file holds resolved values, including secrets such as database passwords and app keys. `discovery:cache` writes it with mode `0600` (readable and writable by its owner only). Keep it out of version control and build artifacts you share.

### What marks the cache as stale

Editing, adding or removing a file in the root `config/` directory makes boot throw a stale `DiscoveryCacheException`. Config files in vendor packages change only through Composer, which the cache already detects. Config files inside `modules/` and `app/` modules aren't tracked, the same as code in those modules, so recompile the cache on every deploy.

## Customization via Preferences

Replace the default `ConfigRepository` implementation using Marko's [Preference system](/docs/packages/core/).

```php
<?php

declare(strict_types=1);

namespace App\Config;

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Attributes\Preference;

#[Preference(replaces: ConfigRepositoryInterface::class)]
class CachedConfigRepository extends ConfigRepository
{
    private array $cache = [];

    public function get(
        string $key,
        ?string $scope = null,
    ): mixed {
        $cacheKey = $key . ($scope ? ":{$scope}" : '');

        if (!isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = parent::get($key, $scope);
        }

        return $this->cache[$cacheKey];
    }
}
```

## API Reference

### ConfigRepositoryInterface

```php
public function get(string $key, ?string $scope = null): mixed
public function has(string $key, ?string $scope = null): bool
public function getString(string $key, ?string $scope = null): string
public function getInt(string $key, ?string $scope = null): int
public function getBool(string $key, ?string $scope = null): bool
public function getFloat(string $key, ?string $scope = null): float
public function getArray(string $key, ?string $scope = null): array
public function all(?string $scope = null): array
public function withScope(string $scope): ConfigRepositoryInterface
```

All getter methods throw `ConfigNotFoundException` when the key does not exist. Use `has()` to check for existence before accessing, or define all defaults in your config files.

### Env

```php
public static function string(string $name, string $default): string
public static function nullableString(string $name, ?string $default = null): ?string
public static function int(string $name, int $default, ?int $min = null, ?int $max = null): int
public static function nullableInt(string $name, ?int $default = null, ?int $min = null, ?int $max = null): ?int
public static function float(string $name, float $default, ?float $min = null, ?float $max = null): float
public static function bool(string $name, bool $default): bool
public static function list(string $name, array $default): array
```

`Env::TRUE_VALUES` and `Env::FALSE_VALUES` hold the accepted boolean tokens. Every method throws `ConfigException` for a value it can't parse or one outside `min`/`max`.

The [config cache](#config-caching) uses these to track which variables the config files read:

```php
public static function startRecording(): void
public static function stopRecording(): array
public static function isRecording(): bool
public static function valueHashes(array $names): array
```

`startRecording()` throws `ConfigException` when already recording, and `stopRecording()` when not recording. `stopRecording()` returns the names read since `startRecording()`, once each, in first-read order. That includes variables read through `Env` and through the `marko/core` readers (`AppEnvironment`, `DiscoveryEnvironment`), because `Env` records into core's [`EnvironmentVariables`](/docs/packages/core/#environmentvariables). `valueHashes()` returns a hash of each variable's current value keyed by name. An empty variable hashes differently from an unset one: `Env` reads both as unset, but `DiscoveryEnvironment` does not (an empty `DISCOVERY_CACHE_ENABLED` disables the cache).

### ConfigLoader

```php
public function load(string $filePath): array
public function loadIfExists(string $filePath): ?array
```

Load errors throw `ConfigLoadException`, whose message ends with `[file: <path>]`: a missing file, a file that doesn't return an array, a syntax error, and any Marko exception the file throws while it runs (an `Env` rejection, for example). For the last case the message, context and suggestion are the original's, and `getPrevious()` returns the original. Other throwables, such as `TypeError`, pass through unchanged.

### ConfigMerger

```php
public function merge(array $base, array $override): array
public function mergeAll(array ...$configs): array
```

Associative arrays merge recursively; any other value, `null` included, replaces the base value. A `RemoveKey` value removes the key (see [Merge Priority](#merge-priority)).

### RemoveKey

```php
new \Marko\Config\RemoveKey()
```

A marker for a config value that deletes the key from the lower-priority config. It never appears in the merged config.

### ConfigDiscovery

```php
public function discover(array $modulePaths, string $rootConfigPath): array
public function files(array $modulePaths, string $rootConfigPath): array
```

`files()` lists every config file in merge order: each module's `config/*.php` in module load order, then the root config directory. `discover()` loads and merges them in that order.

### ConfigServiceProvider

```php
public function createRepository(array $modulePaths, string $rootConfigPath, ?CachedDiscovery $cachedDiscovery = null): ConfigRepositoryInterface
```

When `$cachedDiscovery` comes from a boot that used the discovery cache, the repository is built from its `config` section. Otherwise config files are discovered and merged live.

### ConfigCacheContributor

`Marko\Config\Discovery\ConfigCacheContributor` implements `Marko\Core\Discovery\DiscoveryCacheContributorInterface` with the key `config` (`ConfigCacheContributor::KEY`). `compile()` runs `ConfigDiscovery::discover()` over the enabled modules and the root config directory while `Env` records the variables read. `Marko\Config\Discovery\ConfigCacheSection` builds the section from the result and reads it back on a cached boot, checking the recorded variables against the current environment.

### Exceptions

- `ConfigException` — Base exception for configuration errors
- `ConfigNotFoundException` — Thrown when a required key is not found
- `ConfigLoadException` — Thrown when a config file cannot be loaded
- `ConfigCacheException` — A `DiscoveryCacheException` thrown when a config value can't be cached (a closure or object) or when an environment variable read through `Env` changed after the cache was built
