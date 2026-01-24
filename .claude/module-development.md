# Module Development Guide

How to create and configure Marko modules.

---

## Quick Start

A minimal Marko module requires only a `composer.json` with two things:

```json
{
    "name": "vendor/package-name",
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

That's it. Drop this in `app/`, `modules/`, or publish to Packagist and the framework discovers it automatically.

---

## composer.json Structure

### Required Fields

| Field                | Purpose                                           |
|----------------------|---------------------------------------------------|
| `name`               | Composer package name (vendor/package format)     |
| `extra.marko.module` | Must be `true` to be recognized as a Marko module |

### Recommended Fields

```json
{
    "name": "acme/blog",
    "description": "Blog functionality for my application",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4",
        "marko/core": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "Acme\\Blog\\": "src/"
        }
    },
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

### The `extra.marko` Section

The `extra.marko` object tells the framework this package is a Marko module:

```json
{
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

Without `"module": true`, the package is treated as a regular Composer dependency and won't be loaded by the module system.

### Full Example

A complete module composer.json with all common fields:

```json
{
    "name": "acme/payment-stripe",
    "description": "Stripe payment integration for Marko",
    "type": "library",
    "license": "MIT",
    "version": "1.0.0",
    "require": {
        "php": "^8.4",
        "marko/core": "^1.0",
        "stripe/stripe-php": "^10.0"
    },
    "require-dev": {
        "pestphp/pest": "^4.0"
    },
    "autoload": {
        "psr-4": {
            "Acme\\Payment\\Stripe\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Acme\\Payment\\Stripe\\Tests\\": "tests/"
        }
    },
    "config": {
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    },
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

---

## module.php (Optional)

The `module.php` file provides Marko-specific configuration. It's optional—only create it when you need bindings or to disable the module.

### Location

Place `module.php` in the module root, next to `composer.json`:

```
my-module/
  composer.json
  module.php      ← Here
  src/
```

### Structure

```php
<?php

declare(strict_types=1);

return [
    'enabled' => true,
    'bindings' => [
        // Interface => Implementation mappings
    ],
];
```

### Available Options

| Option     | Type  | Default | Purpose                              |
|------------|-------|---------|--------------------------------------|
| `enabled`  | bool  | `true`  | Set to `false` to disable the module |
| `bindings` | array | `[]`    | Interface to implementation mappings |

### Simple Bindings

Map interfaces to concrete implementations:

```php
return [
    'enabled' => true,
    'bindings' => [
        PaymentInterface::class => StripePayment::class,
        NotificationInterface::class => EmailNotification::class,
    ],
];
```

### Factory Bindings

For complex instantiation, use a closure that receives the container:

```php
use Marko\Core\Container\ContainerInterface;

return [
    'enabled' => true,
    'bindings' => [
        CacheInterface::class => function (ContainerInterface $container): CacheInterface {
            $config = $container->get(CacheConfig::class);

            return new RedisCache(
                host: $config->host,
                port: $config->port,
            );
        },
    ],
];
```

### Disabling a Module

```php
return [
    'enabled' => false,
];
```

---

## Directory Structure

### Standard Layout

```
my-module/
  composer.json        # Required: package metadata + extra.marko.module
  module.php           # Optional: bindings, enabled flag
  src/                 # PHP source code (PSR-4 root)
    Controllers/
    Entity/
    Repositories/
    Services/
    Plugins/
    Observers/
  Seed/                # Database seeders (discovered automatically)
  config/              # Configuration files
  resources/           # Views, translations, assets
  tests/               # Module tests
```

### Special Directories

| Directory    | Purpose             | Discovery                       |
|--------------|---------------------|---------------------------------|
| `src/`       | PHP source code     | Via Composer autoload           |
| `Seed/`      | Database seeders    | Scanned for `#[Seeder]` classes |
| `config/`    | Configuration files | Loaded by config system         |
| `resources/` | Views, assets       | Accessed via view system        |

---

## Module Locations

Modules can live in three places, with later locations taking priority:

| Location   | Priority | Use Case                                  |
|------------|----------|-------------------------------------------|
| `vendor/`  | Lowest   | Composer-installed packages               |
| `modules/` | Middle   | Manual installs, shared code, client work |
| `app/`     | Highest  | Your application modules                  |

### Discovery Depths

- `vendor/*/*/` — Two levels deep (vendor/package)
- `modules/**/` — Recursive (any depth)
- `app/*/` — One level deep

### Override Priority

When the same binding or preference exists in multiple modules, later locations win:

1. `vendor/acme/blog` defines `BlogServiceInterface => BasicBlog`
2. `app/blog` defines `BlogServiceInterface => CustomBlog`
3. Result: `CustomBlog` is used (app wins)

---

## Naming Conventions

### Package Names

Use Composer's vendor/package format:

```
acme/blog           ✓ Good
acme/payment-stripe ✓ Good
AcmeBlog            ✗ Wrong (not vendor/package)
acme_blog           ✗ Wrong (underscore)
```

### Namespaces

Follow PSR-4, matching your autoload configuration:

```json
{
    "name": "acme/blog",
    "autoload": {
        "psr-4": {
            "Acme\\Blog\\": "src/"
        }
    }
}
```

### App Modules

For modules in `app/`, use `App` as the vendor namespace:

```json
{
    "name": "app/blog",
    "autoload": {
        "psr-4": {
            "App\\Blog\\": ""
        }
    }
}
```

---

## Examples

### Minimal Module

The smallest possible module:

```
app/my-feature/
  composer.json
```

```json
{
    "name": "app/my-feature",
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

### Module with Bindings

```
app/payments/
  composer.json
  module.php
  src/
    StripePayment.php
```

**composer.json:**
```json
{
    "name": "app/payments",
    "autoload": {
        "psr-4": {
            "App\\Payments\\": ""
        }
    },
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

**module.php:**
```php
<?php

declare(strict_types=1);

use App\Payments\StripePayment;
use Marko\Payment\PaymentInterface;

return [
    'bindings' => [
        PaymentInterface::class => StripePayment::class,
    ],
];
```

### Module with Seeders

```
app/blog/
  composer.json
  Seed/
    PostSeeder.php
```

**composer.json:**
```json
{
    "name": "app/blog",
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

**Seed/PostSeeder.php:**
```php
<?php

declare(strict_types=1);

namespace App\Blog\Seed;

use Marko\Blog\Entity\Post;
use Marko\Blog\Repositories\PostRepository;
use Marko\Database\Seed\Seeder;
use Marko\Database\Seed\SeederInterface;

/** @noinspection PhpUnused */
#[Seeder(name: 'posts', order: 1)]
class PostSeeder implements SeederInterface
{
    public function __construct(
        private PostRepository $repository,
    ) {}

    public function run(): void
    {
        $post = new Post();
        $post->title = 'Hello World';
        $post->slug = 'hello-world';
        $post->content = 'Welcome to my blog!';
        $post->createdAt = date('Y-m-d H:i:s');

        $this->repository->save($post);
    }
}
```

Run with: `marko db:seed`

---

## Common Mistakes

### Missing `extra.marko.module`

`// WRONG - won't be discovered as a module`

```json
{
    "name": "acme/blog",
    "type": "marko-module"
}
```

`// CORRECT`

```json
{
    "name": "acme/blog",
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

### Wrong Type Field

The `type` field doesn't affect Marko module discovery. Use `library` (the Composer default):

`// WRONG - type doesn't matter for Marko`

```json
{
    "type": "marko-module"
}
```

`// CORRECT - use standard type, add extra.marko`

```json
{
    "type": "library",
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

### Forgetting Autoload

If your module has PHP classes, you need autoload configuration:

```json
{
    "name": "app/blog",
    "autoload": {
        "psr-4": {
            "App\\Blog\\": ""
        }
    },
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

---

## Checklist

Before publishing or using a module:

- [ ] `composer.json` has `name` field
- [ ] `composer.json` has `extra.marko.module: true`
- [ ] Autoload configured if module has PHP classes
- [ ] `module.php` exists only if needed (bindings, disable)
- [ ] Directory structure follows conventions
- [ ] Seeders are in `Seed/` directory with `#[Seeder]` attribute
