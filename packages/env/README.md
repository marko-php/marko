# Marko Env

Environment variable loading--reads `.env` files and provides the `env()` helper with automatic type coercion.

## Overview

Env loads variables from a `.env` file into `$_ENV` and `putenv()`, with system environment variables taking precedence. The `env()` helper function retrieves values with type coercion for common patterns (`true`, `false`, `null`, `empty`). No external dependencies--works with any PHP application.

## Installation

```bash
composer require marko/env
```

## Usage

### Loading Environment Variables

Load from a `.env` file at application bootstrap:

```php
use Marko\Env\EnvLoader;

$loader = new EnvLoader();
$loader->load(__DIR__);
```

The `.env` file is optional--if it does not exist, the loader silently returns. System environment variables are never overwritten, allowing production deployments to override `.env` values.

### The `env()` Helper

Retrieve environment variables with automatic type coercion:

```php
// Simple retrieval
$dbHost = env('DB_HOST', 'localhost');

// Type coercion
$debug = env('APP_DEBUG');    // 'true' -> true, 'false' -> false
$value = env('NULLABLE_VAR'); // 'null' -> null
$empty = env('EMPTY_VAR');    // 'empty' -> ''
```

### .env File Format

```
APP_NAME=Marko
APP_DEBUG=true
DB_HOST=localhost
DB_PORT=3306

# Comments start with #
SECRET_KEY="quoted values supported"
ANOTHER_KEY='single quotes too'
```

### Using in Config Files

Environment variables should only be referenced in config files, not in application code:

```php
// config/database.php
return [
    'host' => env('DB_HOST', 'localhost'),
    'port' => (int) env('DB_PORT', 3306),
    'name' => env('DB_NAME', 'marko'),
];
```

Application code then reads config values:

```php
$host = $this->config->getString('database.host');
```

## Type Coercion

| String Value | Coerced To |
|--------------|------------|
| `'true'`, `'(true)'` | `true` |
| `'false'`, `'(false)'` | `false` |
| `'null'`, `'(null)'` | `null` |
| `'empty'`, `'(empty)'` | `''` |
| Everything else | Unchanged string |

## API Reference

### EnvLoader

```php
public function load(string $path): void;
```

### env()

```php
function env(string $key, mixed $default = null): mixed;
```
