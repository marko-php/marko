---
title: Configuration
description: Configure your Marko application with type-safe PHP config files.
---

Marko uses **PHP files** for configuration — not YAML, not JSON, not .env directly. Config defaults go in each module's `config/` directory, and PHP config files give you type safety, IDE autocompletion, and a single source of truth.

## Config Files

Configuration lives in PHP files that return arrays:

```php title="config/database.php"
<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    'driver' => 'pgsql',
    'host' => Env::string('DB_HOST', 'localhost'),
    'port' => Env::int('DB_PORT', 5432, min: 1, max: 65535),
    'database' => Env::string('DB_DATABASE', 'marko'),
    'username' => Env::string('DB_USERNAME', 'marko'),
    'password' => Env::string('DB_PASSWORD', ''),
];
```

## Accessing Configuration

Use the `ConfigRepositoryInterface` with dot notation:

```php
<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;

readonly class DatabaseService
{
    public function __construct(
        private ConfigRepositoryInterface $configRepository,
    ) {}

    public function getHost(): string
    {
        return $this->configRepository->getString('database.host');
    }
}
```

### Typed Accessors

Never worry about type coercion. Every accessor enforces the return type:

| Method | Returns |
|---|---|
| `getString(key)` | `string` |
| `getInt(key)` | `int` |
| `getFloat(key)` | `float` |
| `getBool(key)` | `bool` |
| `getArray(key)` | `array` |
| `get(key)` | `mixed` |

All typed accessors throw if the value doesn't match the expected type. No silent coercion.

## Environment Variables

Environment variables belong in `.env`. They should **only** be referenced from config files, never directly in application code:

```bash title=".env"
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=marko
APP_DEBUG=true
```

Read them with the `Marko\Config\Env` reader, which parses each value to the right type and fails the boot with a `ConfigException` when a value is invalid. See [Environment Variables](/docs/packages/config/#environment-variables) for every method.

```php title="config/app.php"
use Marko\Config\Env;

return [
    'debug' => Env::bool('APP_DEBUG', false),
];
```

```php
$debug = $this->configRepository->getBool('app.debug');
```

This keeps environment variables in one place and makes your application testable with config overrides.

## Where Config Defaults Go

Config defaults go in each module's `config/` directory. When several files provide the same config key, the later one wins: vendor modules, then `modules/`, then `app/` modules, then your project-root `config/` directory. Your own config overrides the defaults that ship with a module:

```
vendor/marko/cache/config/cache.php     → Base defaults
modules/acme/cache/config/cache.php     → Third-party overrides
app/my-app/config/cache.php             → Your module overrides
config/cache.php                        → Project overrides (wins)
```

## Which Config Location to Use

The four locations do two different jobs:

- **A module's `config/` ships defaults with its code.** The file belongs to the module and travels with it, so every project that installs the module gets the same starting values.
- **The project-root `config/` holds settings for this installation.** Database credentials, the cache or mail driver, the app URL and anything read from the environment belong to the deployment, not to any one module.

When you're deciding where a value goes:

| You're setting | Put it in |
|----------------|-----------|
| Defaults for a module you're writing | That module's `config/` |
| An override that belongs with one of your app modules, such as a blog's `posts_per_page` | That app module's `config/` (`app/blog/config/blog.php`) |
| A project-wide or per-environment value, or an override of a vendor package's config | The project-root `config/` (`config/cache.php`) |

Most overrides belong in the project-root `config/`. A fresh project's `app/` is empty, and the root `config/` works without creating a module just to hold settings. It is also the one place that always wins.

## Next Steps

- [Understand dependency injection](/docs/concepts/dependency-injection/)
- [Set up your database](/docs/guides/database/)
