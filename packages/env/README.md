# marko/env

Environment variable loading — reads `.env` files and provides the `env()` helper with automatic type coercion.

## Installation

```bash
composer require marko/env
```

## Quick Example

```php
use Marko\Env\EnvLoader;

$envLoader = new EnvLoader();
$envLoader->load(__DIR__);

$debug = env('APP_DEBUG'); // 'true' -> true, 'false' -> false
```

## Documentation

Full usage, API reference, and examples: [marko/env](https://marko.build/docs/packages/env/)
