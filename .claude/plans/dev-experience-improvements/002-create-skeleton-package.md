# Task 002: Create marko/skeleton package

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `packages/skeleton` package that serves as the `composer create-project` target. This gives new users a zero-friction starting point with the correct directory structure, entry point, and dependencies pre-configured.

## Context
- Related files: new package at `packages/skeleton/`
- Pattern: Laravel's `laravel/laravel` — a `"type": "project"` package
- Must use the new `Application::boot()` API in `public/index.php`
- The skeleton is NOT a module (no `marko.module: true`) — it's a project template
- Must be registered in root `composer.json` repositories array (path type)

## Requirements (Test Descriptions)
- [ ] `it has a valid composer.json with type project`
- [ ] `it requires marko/framework`
- [ ] `it requires marko/dev-server as a dev dependency`
- [ ] `it has public/index.php using Application::boot() API`
- [ ] `it has .env.example with placeholder values`
- [ ] `it has empty app/ directory with .gitkeep`
- [ ] `it has empty modules/ directory with .gitkeep`
- [ ] `it has empty config/ directory with .gitkeep`
- [ ] `it has empty storage/ directory with .gitkeep`

## Acceptance Criteria
- All requirements have passing tests
- `composer.json` is valid with `"type": "project"`
- `public/index.php` uses `Application::boot(dirname(__DIR__))` + `$app->handleRequest()`
- Directory structure matches Marko conventions
- Code follows project standards

## Implementation Notes
### File structure:
```
packages/skeleton/
  composer.json
  public/
    index.php
  app/
    .gitkeep
  modules/
    .gitkeep
  config/
    .gitkeep
  storage/
    .gitkeep
  .env.example
  .gitignore
```

### composer.json:
```json
{
    "name": "marko/skeleton",
    "description": "Marko Framework - Application Skeleton",
    "license": "MIT",
    "type": "project",
    "require": {
        "php": "^8.5",
        "marko/framework": "*",
        "marko/env": "*"
    },
    "require-dev": {
        "marko/dev-server": "*",
        "pestphp/pest": "^4.0"
    },
    "config": {
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    }
}
```

### public/index.php:
```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marko\Core\Application;

$app = Application::boot(dirname(__DIR__));
$app->handleRequest();
```

### .env.example:
```
APP_ENV=local
APP_DEBUG=true
```

### .gitignore:
```
/vendor/
.env
composer.lock
```

**Important**: The skeleton uses `"*"` version constraints (not `"self.version"`) because it is a `"type": "project"` template installed via `composer create-project`. In end-user projects there is no monorepo context, so `self.version` would fail. When tagging releases, update `"*"` to proper version constraints like `"^1.0"`.

### Root composer.json:
Add the skeleton path repository entry alongside existing packages.

### Tests:
Create `packages/skeleton/tests/PackageStructureTest.php` following existing patterns (see `packages/dev-server/tests/PackageStructureTest.php`).
