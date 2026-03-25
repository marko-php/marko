```
██      ██   ██████   ██████    ██   ██   ██████
████  ████  ██    ██  ██   ██   ██  ██   ██    ██
██ ████ ██  ████████  ██████    █████    ██    ██
██  ██  ██  ██    ██  ██   ██   ██  ██   ██    ██
██      ██  ██    ██  ██    ██  ██   ██   ██████
```

**Enterprise-grade extensibility meets modern PHP.**

Override anything. Intercept any method. Extend any module — all without touching vendor code.

[![PHP 8.5+](https://img.shields.io/badge/PHP-8.5%2B-7A86B8?style=flat-square)](https://www.php.net/releases/8.5/en.php)
[![License: MIT](https://img.shields.io/badge/License-MIT-E8B931?style=flat-square)](LICENSE)
[![Active Development](https://img.shields.io/badge/Status-Active%20Development-00C853?style=flat-square)](#status)
[![Latest Version](https://img.shields.io/packagist/v/marko/core?style=flat-square)](https://packagist.org/packages/marko/core)
[![Total Downloads](https://img.shields.io/packagist/dt/marko/core?style=flat-square)](https://packagist.org/packages/marko/core)

---

## Why Marko?

Rapid-development frameworks are great — until you need to customize a third-party module. Enterprise frameworks let you override anything, but demand months of learning.

Marko bridges the gap.

**Magento's deep extensibility + Laravel's developer experience + loud, helpful errors.**

Marko is a modular PHP 8.5+ framework where everything is a module: your app code, third-party packages, …even the framework. All integration areas follow the same structure and rules. Later modules always win, so your app layer cleanly overrides vendor without patches, forks, or hacks.

### What makes Marko different

- **True modularity** — Interface and implementation are always separate. Swap any driver (database, cache, mail, queue) without touching a line of application code.
- **Preferences** — Remap any interface to your own implementation, framework-wide. One line of config replaces an entire class.
- **Plugins** — Intercept any public method with `before` and `after` hooks. Modify inputs, transform outputs, or add behavior — all without inheritance.
- **PHP-native configuration** — No XML. No YAML. Every route, binding, and plugin is defined in PHP, including full IDE autocompletion and `::class` constants.
- **Attribute-driven** — Routes, plugins, observers, commands, and more are declared with PHP attributes. Discovery is automatic.
- **Loud errors** — No silent failures. When something goes wrong, Marko tells you exactly what happened, where, and how to fix it.

## Quick Start

```bash
composer global require marko/cli
composer create-project marko/skeleton my-app
cd my-app
```

Register your first module in `app/hello/composer.json`:

```json
{
    "name": "app/hello",
    "autoload": {
        "psr-4": {
            "App\\Hello\\": "src/"
        }
    },
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

Create a controller at `app/hello/src/Controller/HelloController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Hello\Controller;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

class HelloController
{
    #[Get('/')]
    public function index(): Response
    {
        return new Response('Hello from Marko!');
    }
}
```

Start the dev server:

```bash
marko up
```

Visit [http://localhost:8000](http://localhost:8000) to see your response.

## Module System

Everything in Marko is a module. Modules are discovered automatically from three locations, loaded in priority order:

| Location | Purpose | Priority |
|----------|---------|----------|
| `vendor/` | Framework & third-party packages | Lowest |
| `modules/` | Locally installed third-party modules | Middle |
| `app/` | Your application code | Highest |

**Later modules always win.** Your `app/` modules can override controllers, templates, config, and DI bindings from any vendor package — without modifying vendor code.

### Extending vendor behavior

**Preferences** — Replace any interface binding:

```php
// app/my-module/module.php
return [
    'preferences' => [
        PaymentProcessorInterface::class => MyCustomPaymentProcessor::class,
    ],
];
```

**Plugins** — Intercept any public method:

```php
#[Plugin(target: OrderService::class)]
class OrderPlugin
{
    #[Before]
    public function place(Order $order): null
    {
        // Runs before OrderService::place() — method name matches target
        return null;
    }
}

#[Plugin(target: OrderService::class)]
class OrderAuditPlugin
{
    #[After(method: 'place')]
    public function auditPlace(OrderResult $result, Order $order): OrderResult
    {
        // Custom method name — `method` param maps it to OrderService::place()
        return $result;
    }
}
```

**Observers** — React to system events:

```php
#[Observer(event: OrderPlaced::class)]
class SendOrderNotification
{
    public function handle(OrderPlaced $event): void
    {
        // Decoupled, event-driven logic
    }
}
```

## Packages

Marko ships as composable packages — require only what you need. Every package follows an interface/implementation split, so you can swap drivers without changing application code.

### Core

| Package | Description |
|---------|-------------|
| [core](packages/core/README.md) | DI container, module discovery, bootstrap, plugin & preference systems |
| [config](packages/config/README.md) | PHP-native configuration with dot-notation access |
| [env](packages/env/README.md) | Environment variable loading |
| [routing](packages/routing/README.md) | Attribute-based HTTP routing with conflict detection |
| [cli](packages/cli/README.md) | Console command system with attribute-driven discovery |
| [framework](packages/framework/README.md) | Full-stack metapackage for rapid setup |

### Database

| Package | Description |
|---------|-------------|
| [database](packages/database/README.md) | Database abstraction, migrations, entity management |
| [database-pgsql](packages/database-pgsql/README.md) | PostgreSQL driver |
| [database-mysql](packages/database-mysql/README.md) | MySQL driver |

### Caching

| Package | Description |
|---------|-------------|
| [cache](packages/cache/README.md) | Cache contracts and manager |
| [cache-array](packages/cache-array/README.md) | In-memory cache (testing) |
| [cache-file](packages/cache-file/README.md) | Filesystem cache driver |
| [cache-redis](packages/cache-redis/README.md) | Redis cache driver |

### Session

| Package | Description |
|---------|-------------|
| [session](packages/session/README.md) | Session contracts and manager |
| [session-file](packages/session-file/README.md) | File-based sessions |
| [session-database](packages/session-database/README.md) | Database-backed sessions |

### Mail

| Package | Description |
|---------|-------------|
| [mail](packages/mail/README.md) | Mail contracts and manager |
| [mail-log](packages/mail-log/README.md) | Log driver (development) |
| [mail-smtp](packages/mail-smtp/README.md) | SMTP driver |

### Queue

| Package | Description |
|---------|-------------|
| [queue](packages/queue/README.md) | Queue contracts and manager |
| [queue-sync](packages/queue-sync/README.md) | Synchronous driver (development) |
| [queue-database](packages/queue-database/README.md) | Database-backed queue |
| [queue-rabbitmq](packages/queue-rabbitmq/README.md) | RabbitMQ driver |

### Logging

| Package | Description |
|---------|-------------|
| [log](packages/log/README.md) | PSR-3 logging contracts |
| [log-file](packages/log-file/README.md) | File-based log driver |

### Views & Templates

| Package | Description |
|---------|-------------|
| [view](packages/view/README.md) | View contracts and template resolution |
| [view-latte](packages/view-latte/README.md) | Latte template engine integration |

### Error Handling

| Package | Description |
|---------|-------------|
| [errors](packages/errors/README.md) | Error handling contracts |
| [errors-simple](packages/errors-simple/README.md) | Minimal error handler |
| [errors-advanced](packages/errors-advanced/README.md) | Rich error pages with stack traces |

### HTTP

| Package | Description |
|---------|-------------|
| [http](packages/http/README.md) | HTTP client contracts |
| [http-guzzle](packages/http-guzzle/README.md) | Guzzle HTTP driver |

### Filesystem

| Package | Description |
|---------|-------------|
| [filesystem](packages/filesystem/README.md) | Filesystem contracts |
| [filesystem-local](packages/filesystem-local/README.md) | Local filesystem driver |
| [filesystem-s3](packages/filesystem-s3/README.md) | Amazon S3 driver |

### Encryption

| Package | Description |
|---------|-------------|
| [encryption](packages/encryption/README.md) | Encryption contracts |
| [encryption-openssl](packages/encryption-openssl/README.md) | OpenSSL driver |

### Media

| Package | Description |
|---------|-------------|
| [media](packages/media/README.md) | Image processing contracts |
| [media-gd](packages/media-gd/README.md) | GD driver |
| [media-imagick](packages/media-imagick/README.md) | ImageMagick driver |

### Pub/Sub & Real-Time

| Package | Description |
|---------|-------------|
| [pubsub](packages/pubsub/README.md) | Pub/Sub contracts |
| [pubsub-pgsql](packages/pubsub-pgsql/README.md) | PostgreSQL LISTEN/NOTIFY driver |
| [pubsub-redis](packages/pubsub-redis/README.md) | Redis Pub/Sub driver |
| [sse](packages/sse/README.md) | Server-Sent Events |
| [amphp](packages/amphp/README.md) | Async foundation with AMPHP |

### Security & Access Control

| Package | Description |
|---------|-------------|
| [authentication](packages/authentication/README.md) | Guard-based authentication |
| [authentication-token](packages/authentication-token/README.md) | Token/API authentication |
| [authorization](packages/authorization/README.md) | Policy-based authorization |
| [hashing](packages/hashing/README.md) | Password hashing |
| [security](packages/security/README.md) | Security utilities and middleware |
| [cors](packages/cors/README.md) | Cross-Origin Resource Sharing |
| [rate-limiting](packages/rate-limiting/README.md) | Request rate limiting |

### Content & Admin

| Package | Description |
|---------|-------------|
| [blog](packages/blog/README.md) | Blog module with posts, categories, and tags |
| [admin](packages/admin/README.md) | Admin panel foundation |
| [admin-auth](packages/admin-auth/README.md) | Admin authentication |
| [admin-api](packages/admin-api/README.md) | Admin REST API |
| [admin-panel](packages/admin-panel/README.md) | Admin UI panel |

### Utilities

| Package | Description |
|---------|-------------|
| [api](packages/api/README.md) | REST API foundation |
| [validation](packages/validation/README.md) | Input validation |
| [pagination](packages/pagination/README.md) | Query result pagination |
| [notification](packages/notification/README.md) | Notification contracts |
| [notification-database](packages/notification-database/README.md) | Database notification driver |
| [translation](packages/translation/README.md) | Translation contracts |
| [translation-file](packages/translation-file/README.md) | File-based translations |
| [search](packages/search/README.md) | Search abstraction |
| [scheduler](packages/scheduler/README.md) | Task scheduling |
| [health](packages/health/README.md) | Health check endpoints |
| [webhook](packages/webhook/README.md) | Webhook handling |
| [dev-server](packages/dev-server/README.md) | Local development server |

### Testing

| Package | Description |
|---------|-------------|
| [testing](packages/testing/README.md) | Test fakes, assertions, and Pest expectations |

## Example Applications

| App | Description |
|-----|-------------|
| [MarkoTalk](https://github.com/marko-php/markotalk) | Real-time community chat — dogfoods plugins, preferences, events, SSE, and the admin panel |

## Requirements

- PHP 8.5+
- Composer 2.x

## Status

Marko is in **active development**. The architecture is stable and packages are fully functional, but APIs may evolve before the 1.0 release. We welcome early adopters and feedback.

## Contributing

Marko is developed as a monorepo. All packages live under `packages/` and are tested together.

```bash
# Clone the repository
git clone https://github.com/marko-php/marko.git
cd marko

# Install dependencies
composer install

# Run tests
./vendor/bin/pest --parallel

# Check code style
./vendor/bin/phpcs
```

## Learn More

Visit [marko.build](https://marko.build) for documentation and updates.

## Credits

Created by [Mark Shust](https://markshust.com)

## License

Marko is open-source software licensed under the [MIT License](LICENSE).
