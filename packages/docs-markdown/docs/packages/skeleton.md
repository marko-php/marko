---
title: marko/skeleton
description: The official application template for starting new Marko Framework projects.
---

The official application template for starting new Marko Framework projects. This is a `composer create-project` template that scaffolds a ready-to-use project structure with the entry point, directory layout, environment config, and dev tooling pre-configured.

## Installation

```bash
composer create-project marko/skeleton my-app
cd my-app
```

## Project Structure

```
my-app/
├── app/                # Your application modules
├── config/             # Root configuration overrides
├── modules/            # Third-party modules
├── public/
│   └── index.php       # Web entry point
├── storage/            # Logs, cache, sessions
├── tests/
├── .env.example        # Environment variable template
└── composer.json
```

## Entry Point

The `public/index.php` bootstraps the application:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marko\Core\Application;

$app = Application::boot(dirname(__DIR__));
$app->handleRequest();
```

`Application::boot()` discovers modules, registers bindings, and builds the DI container. `handleRequest()` routes the incoming HTTP request to the matched controller.

## Getting Started

1. Copy the environment template:

```bash
cp .env.example .env
```

2. Start the dev server:

```bash
marko up
```

3. Visit [http://localhost:8000](http://localhost:8000)

## Included Dependencies

| Package | Description |
|---|---|
| [`marko/framework`](/docs/packages/framework/) | Metapackage bundling core, routing, CLI, errors, config, hashing, validation |
| [`marko/env`](/docs/packages/env/) | Environment variable loading with `env()` helper |

### Dev Dependencies

| Package | Description |
|---|---|
| [`marko/dev-server`](/docs/packages/dev-server/) | `marko up` / `marko down` development environment |
| `pestphp/pest` | Testing framework |

## Environment Configuration

The `.env.example` ships with:

```env
APP_ENV=local
APP_DEBUG=true
```

See the [Configuration guide](/docs/getting-started/configuration/) for adding database, cache, and other environment variables.

## Next Steps

- [Your First Application](/docs/getting-started/first-application/) — Create your first route and controller
- [Project Structure](/docs/getting-started/project-structure/) — Understand the directory layout in depth
- [Create a Custom Module](/docs/tutorials/custom-module/) — Build a reusable module inside `app/`
- [marko/framework](/docs/packages/framework/) — See all optional packages you can add

## Requirements

- PHP 8.5 or higher
