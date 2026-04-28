---
title: Project Structure
description: Understand how a Marko application is organized.
---

A Marko application has a flat, predictable structure. Modules live in three locations with clear priority rules, and every module follows the same internal layout.

```
my-app/
├── app/                  # Application modules (highest priority)
│   ├── blog/
│   │   ├── src/
│   │   ├── config/
│   │   ├── composer.json
│   │   └── module.php    # Optional module configuration
│   └── shop/
├── modules/              # Third-party modules (medium priority)
├── vendor/               # Composer packages (lowest priority)
│   └── marko/
│       ├── core/
│       ├── routing/
│       └── ...
├── public/
│   └── index.php         # Entry point
├── config/               # Root configuration
├── storage/              # Logs, cache, sessions
├── composer.json
└── .env.example
```

## Module Locations

Marko discovers modules from three locations. When conflicts arise, higher-priority locations win:

### `app/` — Application Modules (Highest Priority)

Your custom modules live here. These override everything else. Each subdirectory is a module:

```
app/
├── blog/           # Your blog customizations
├── checkout/       # Your checkout logic
└── analytics/      # Your analytics module
```

### `modules/` — Third-Party Modules (Medium Priority)

For manually-installed modules that aren't on Packagist:

```
modules/
└── acme/
    └── custom-shipping/
```

### `vendor/` — Composer Packages (Lowest Priority)

All `composer require`'d packages. Marko auto-discovers modules with `marko.module: true`.

## Inside a Module

Every module follows the same structure:

```
my-module/
├── src/                  # PHP source code (PSR-4 autoloaded)
│   ├── Controller/       # HTTP controllers
│   ├── Model/            # Domain models / entities
│   ├── Repository/       # Data access
│   ├── Observer/         # Event observers
│   └── Plugin/           # Method plugins
├── config/               # Module configuration files
├── database/
│   ├── migrations/       # Schema migrations
│   └── seeders/          # Data seeders
├── resources/
│   └── views/            # View templates
├── tests/                # Module tests
├── composer.json         # Package definition
└── module.php            # Optional: bindings, singletons, sequence, boot
```

## The `module.php` File

This optional file declares a module's bindings, singletons, and wiring:

```php title="module.php"
<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\File\Driver\FileCacheDriver;

return [
    'bindings' => [
        CacheInterface::class => FileCacheDriver::class,
    ],
    'singletons' => [
        FileCacheDriver::class,
    ],
];
```

## The `public/index.php` Entry Point

The web entry point is minimal — it boots the framework and handles the request:

```php title="public/index.php"
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marko\Core\Application;

$app = Application::boot(dirname(__DIR__));
$app->handleRequest();
```

## Next Steps

- [Configure your application](/docs/getting-started/configuration/)
- [Learn about modules in depth](/docs/concepts/modularity/)
