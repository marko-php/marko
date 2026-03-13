---
title: Configuration
description: Configure your Marko application with type-safe PHP config files.
---

Marko uses **PHP files** for configuration — not YAML, not JSON, not .env directly. PHP config files give you type safety, IDE autocompletion, and a single source of truth.

## Config Files

Configuration lives in PHP files that return arrays:

```php title="config/database.php"
<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => (int) env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'marko'),
            'username' => env('DB_USERNAME', 'marko'),
            'password' => env('DB_PASSWORD', ''),
        ],
    ],
];
```

## Accessing Configuration

Use the `ConfigRepositoryInterface` with dot notation:

```php
<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;

class DatabaseService
{
    public function __construct(
        private readonly ConfigRepositoryInterface $configRepository,
    ) {}

    public function getHost(): string
    {
        return $this->configRepository->getString('database.connections.pgsql.host');
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

```php title="config/app.php"
return [
    'debug' => (bool) env('APP_DEBUG', false),
];
```

```php
$debug = $this->configRepository->getBool('app.debug');
```

This keeps environment variables in one place and makes your application testable with config overrides.

## Config Merge Priority

When multiple modules provide the same config key, higher-priority modules win:

```
vendor/marko/cache/config/cache.php     → Base defaults
modules/acme/cache/config/cache.php     → Third-party overrides
app/my-app/config/cache.php             → Your overrides (wins)
```

## Next Steps

- [Understand dependency injection](/docs/concepts/dependency-injection/)
- [Set up your database](/docs/guides/database/)
