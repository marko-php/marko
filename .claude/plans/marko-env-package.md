# Plan: marko/env Package

## Problem Statement

Marko applications need environment-specific configuration (database credentials, API keys, debug settings) that:
1. Differs between development, staging, and production
2. Should not be committed to version control (secrets)
3. Should be easy to configure without editing PHP files

Currently, myblog has hardcoded values in `config/database.php`:
```php
return [
    'host' => '127.0.0.1',
    'database' => 'myblog',
    'username' => 'root',
    'password' => 'password',
];
```

This doesn't scale across environments.

---

## Core Principle: Config Files Are the Source of Truth

**This is the key distinction from Laravel's approach.**

| Aspect | Laravel | Marko |
|--------|---------|-------|
| Source of truth | `.env` file | `config/` files |
| Config files | Thin wrappers around `env()` | Complete definitions with defaults |
| Without .env | App often broken | App works with sensible defaults |
| Discoverability | Check .env AND config | Check config only |

### Why Config as Source of Truth?

1. **Discoverability** - A developer can open `config/database.php` and see ALL options, their types, and their defaults. No hunting through `.env.example`.

2. **Works Out of the Box** - Clone a repo, run it. Sensible defaults mean no setup for basic development.

3. **Explicit Over Implicit** - The config file explicitly shows what's configurable. `.env` just provides overrides for environment-specific values.

4. **Type Safety** - Config files can use PHP types. `.env` is always strings.

---

## Design Decisions

### 1. No `.env.sample` Fallback (Rejected)

**Question:** Should we read `.env.sample` if `.env` doesn't exist?

**Answer:** No. This is magic.

- Developer doesn't know which file is being used
- Changes to `.env.sample` unexpectedly affect behavior
- Violates "explicit over implicit"

**The right approach:** Config files have sensible defaults. No `.env` file? App still works for development.

### 2. What Goes Where?

#### In `.env` (secrets and environment-specific):
```bash
APP_ENV=development

# Database credentials
DB_HOST=127.0.0.1
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=secret

# Third-party services
MAIL_HOST=smtp.mailtrap.io
MAIL_USERNAME=abc123
MAIL_PASSWORD=xyz789

# API keys
STRIPE_KEY=sk_test_xxx
```

#### In `config/` files (structure, options, defaults):
```php
// config/database.php
return [
    'driver' => env('DB_DRIVER', 'mysql'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', 3306),
    'database' => env('DB_DATABASE', 'app'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
];
```

**Key insight:** The config file shows ALL options. The `.env` file only needs to override what differs from defaults.

### 3. `.env.example` Contents (Minimal)

The `.env.example` should be **minimal** - only things that:
1. Have no sensible default (secrets)
2. Almost always need to be changed per environment

```bash
# .env.example
APP_ENV=development

# Database (defaults work for local MySQL)
DB_DATABASE=myapp
DB_PASSWORD=

# Add your API keys here
# STRIPE_KEY=
# MAIL_PASSWORD=
```

**NOT in .env.example:**
- `DB_HOST=127.0.0.1` (sensible default in config)
- `DB_PORT=3306` (sensible default in config)
- `DB_USERNAME=root` (common dev default)
- `CACHE_DRIVER=file` (this is app config, not env)

### 4. The `env()` Helper Function

```php
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key) ?: null;

    if ($value === null) {
        return $default;
    }

    // Type coercion for common patterns
    return match (strtolower($value)) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'null', '(null)' => null,
        'empty', '(empty)' => '',
        default => $value,
    };
}
```

### 5. When to Load `.env`

**Load early, before config files are parsed.**

```
Bootstrap sequence:
1. Autoloader (Composer) → env() function available
2. bootstrap.php called → EnvLoader::load() if marko/env installed
3. Application::boot() called
4. Config files loaded (which call env()) - values already available
5. Module boot callbacks run
```

**Implementation:** The `marko/core` bootstrap automatically loads `.env` if `marko/env` is installed. This keeps application entry points clean while ensuring env vars are available before any config is parsed.

### 7. System Environment Variables Take Precedence

`EnvLoader` does **not** overwrite existing environment variables. This allows:
- Production: Set real values via system env vars (Docker, systemd, etc.)
- Development: Use `.env` file for convenience
- CI/CD: Override specific values without modifying `.env`

```php
// EnvLoader only sets if not already present
if (!isset($_ENV[$name]) && getenv($name) === false) {
    $_ENV[$name] = $value;
    putenv("$name=$value");
}
```

### 6. Environment Detection for Error Handler

**No changes needed to `marko/errors-simple`.** The existing `Environment` class uses `getenv()` which automatically picks up values loaded by `EnvLoader` (via `putenv()`).

Current implementation already works:
```php
// errors-simple/src/Environment.php
private function getEnvVar(string $name): ?string
{
    $value = getenv($name);  // Works because EnvLoader calls putenv()
    return $value === false ? null : $value;
}
```

**Why no dependency on marko/env?**
- Low-level packages should have minimal dependencies
- `getenv()` is sufficient for simple "is this development?" checks
- `env()` is for config files where type coercion and defaults matter

---

## Package Structure

```
packages/env/
  src/
    EnvLoader.php      # Loads and parses .env files
    functions.php      # Global env() helper (auto-loaded via Composer)
  tests/
    Unit/
      EnvLoaderTest.php
      EnvFunctionTest.php
  composer.json
  README.md
```

**Note:** No `module.php` needed. The `env()` helper is auto-loaded via Composer's `files` autoload. Applications must explicitly call `EnvLoader::load()` in their bootstrap - this follows Marko's "explicit over implicit" philosophy and works correctly in all scenarios (monorepo development, deployed apps, testing).

### composer.json

```json
{
    "name": "marko/env",
    "description": "Environment variable loading for Marko applications",
    "type": "library",
    "license": "MIT",
    "autoload": {
        "psr-4": {
            "Marko\\Env\\": "src/"
        },
        "files": [
            "src/functions.php"
        ]
    },
    "autoload-dev": {
        "psr-4": {
            "Marko\\Env\\Tests\\": "tests/"
        }
    },
    "require": {
        "php": "^8.5"
    },
    "require-dev": {
        "pestphp/pest": "^4.0"
    }
}
```

### Bootstrap (automatic via marko/core)

Environment loading is automatic when using the standard bootstrap. The `bootstrap.php` checks if `marko/env` is installed and loads `.env` before booting:

```php
// packages/core/bootstrap.php (automatic)
if (class_exists(EnvLoader::class)) {
    (new EnvLoader())->load(dirname($vendorPath));
}
```

Application entry point stays clean:

```php
// public/index.php
require __DIR__ . '/../vendor/autoload.php';

$app = (require __DIR__ . '/../vendor/marko/core/bootstrap.php')(
    vendorPath: __DIR__ . '/../vendor',
    modulesPath: __DIR__ . '/../modules',
    appPath: __DIR__ . '/../app',
);
```

### functions.php

```php
<?php

declare(strict_types=1);

if (!function_exists('env')) {
    /**
     * Get an environment variable with optional default and type coercion.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? null;

        if ($value === null) {
            $envValue = getenv($key);
            $value = $envValue === false ? null : $envValue;
        }

        if ($value === null) {
            return $default;
        }

        // Type coercion for common patterns
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
        default => $value,
    };
}
```

### EnvLoader.php

```php
<?php

declare(strict_types=1);

namespace Marko\Env;

class EnvLoader
{
    public function load(string $path): void
    {
        $file = $path . '/.env';

        if (!is_file($file)) {
            return; // No .env file is fine - config defaults apply
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue; // Skip comments
            }

            if (!str_contains($line, '=')) {
                continue; // Skip invalid lines
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            // Don't overwrite existing environment variables
            // This allows system env vars to take precedence
            if (!isset($_ENV[$name]) && getenv($name) === false) {
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
        }
    }
}
```

---

## Migration Path for Existing Apps

### Before (myblog/config/database.php):
```php
return [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'myblog',
    'username' => 'root',
    'password' => 'password',
];
```

### After (myblog/config/database.php):
```php
return [
    'driver' => env('DB_DRIVER', 'mysql'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', 3306),
    'database' => env('DB_DATABASE', 'myblog'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
];
```

### myblog/.env (created by developer):
```bash
APP_ENV=development
DB_PASSWORD=secret
```

### myblog/.env.example (committed):
```bash
APP_ENV=development
DB_DATABASE=myblog
DB_PASSWORD=
```

**Result:**
- Clone repo → works immediately with defaults
- Production → set real values in `.env`
- Config file documents all options

---

## Documentation (README.md)

### Usage

```php
// In config files
return [
    'debug' => env('APP_DEBUG', false),
    'api_key' => env('STRIPE_KEY'), // No default = required in production
];
```

### Philosophy

1. **Config files are the source of truth** - They define what's configurable
2. **`.env` provides overrides** - For secrets and environment-specific values
3. **Sensible defaults** - Apps work without `.env` for development
4. **Minimal `.env.example`** - Only truly variable values

### What Goes in `.env`?

| Yes | No |
|-----|-----|
| Database password | Database charset |
| API keys | Cache driver |
| SMTP credentials | Log channel |
| APP_ENV | Route prefix |

**Rule of thumb:** If it's a secret or differs per environment, use `.env`. If it's an application setting, put it directly in config.

---

## Open Questions

### 1. Should `env()` be globally available or require import?

**Recommendation:** Global function (like Laravel) for convenience in config files.

### 2. Should we validate required env vars?

**Recommendation:** No. If a config value is required and has no default, the consuming code should fail loudly when it tries to use `null`.

### 3. Variable interpolation in .env?

```bash
BASE_URL=https://example.com
API_URL=${BASE_URL}/api
```

**Recommendation:** No. Keep it simple. If you need this, compute it in PHP config files.

### 4. Multiple .env files (.env.local, .env.testing)?

**Recommendation:** Not initially. Keep it simple. One `.env` file, use `APP_ENV` to change behavior in config files if needed.

---

## Summary

| Aspect | Decision |
|--------|----------|
| Source of truth | Config files |
| `.env` purpose | Secrets and env-specific overrides |
| `.env.example` | Minimal - only secrets and commonly-changed values |
| `.env.sample` fallback | No (too magic) |
| Default without `.env` | App works with config defaults |
| Load timing | Explicit call in bootstrap (before Application::boot()) |
| `env()` function | Global helper with type coercion |
| `getenv()` usage | Fine for low-level packages (errors-simple) |
| System env precedence | Yes - `.env` doesn't overwrite existing vars |
| Variable interpolation | No |
| Multiple .env files | No (keep simple) |
| errors-simple changes | None needed - `getenv()` works automatically |

This aligns with Marko's philosophy:
- **Explicit over implicit** - Config files show everything
- **Loud errors** - Missing required values fail at use time
- **Opinionated** - One way to do environment config
- **Developer friendly** - Works out of the box

---

## Implementation Tasks

1. **Create package structure** - `packages/env/` with composer.json, src/, tests/
2. **Implement EnvLoader** - Parse .env files, populate $_ENV and putenv()
3. **Implement env() helper** - Type coercion, defaults, in functions.php
4. **Write tests** - Unit tests for EnvLoader parsing, env() type coercion
5. **Add to root composer.json** - Path repository for monorepo
6. **Update demo** - Add marko/env dependency, create sample .env.example
