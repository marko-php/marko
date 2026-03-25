# Task 005: Move env loading into initialize() and simplify bootstrap.php

**Status**: completed
**Depends on**: 002, 003
**Retry count**: 0

## Description
Move env loading from `bootstrap.php` into `Application::initialize()` so that both entry paths (bootstrap.php closure and `Application::boot()`) share the same env loading logic. Then simplify `bootstrap.php` by removing its env loading code. The closure signature and return type must remain identical for backwards compatibility.

## Context
- Related files: `packages/core/bootstrap.php`, `packages/core/src/Application.php`, `packages/env/src/EnvLoader.php`
- The closure currently: loads env via `EnvLoader`, creates Application with explicit paths, calls `initialize()`, returns app
- After refactor: `initialize()` handles env loading internally. `bootstrap.php` just creates Application, calls `initialize()`, returns app.
- **Key**: `bootstrap.php` accepts explicit vendorPath/modulesPath/appPath that may NOT follow the basePath convention. The env loading in `initialize()` must derive basePath via `dirname($this->vendorPath)` — this is already done on line 110 of Application.php for ProjectPaths. Use the same derivation.
- **Guard required**: `marko/env` is optional. Use `class_exists(EnvLoader::class)` guard.
- **No double-load concern**: After this refactor, env loading is removed from `bootstrap.php` entirely. It only happens inside `initialize()`. `EnvLoader::load()` is also idempotent.

## Requirements (Test Descriptions)
- [ ] `it still returns an Application instance from the bootstrap closure`
- [ ] `it still accepts explicit vendorPath, modulesPath, and appPath parameters`
- [ ] `it loads environment variables during initialize() using class_exists(EnvLoader::class) guard`
- [ ] `it derives basePath for env loading via dirname($this->vendorPath) inside initialize()`

## Acceptance Criteria
- All requirements have passing tests
- `bootstrap.php` closure signature unchanged: `function(string $vendorPath, string $modulesPath, string $appPath): Application`
- `bootstrap.php` no longer contains env loading code
- `initialize()` loads env at the start, guarded by `class_exists(EnvLoader::class)`
- All existing tests still pass

## Implementation Notes

### Step 1: Add env loading to the start of `Application::initialize()`
```php
public function initialize(): void
{
    // Load environment variables if marko/env is installed
    if (class_exists(EnvLoader::class)) {
        $basePath = dirname($this->vendorPath);
        (new EnvLoader())->load($basePath);
    }

    // ... existing initialize logic unchanged ...
}
```

Add `use Marko\Env\EnvLoader;` import at the top of Application.php.

### Step 2: Simplify `bootstrap.php`
Remove the env loading block and the `use Marko\Env\EnvLoader;` import. The file becomes:
```php
<?php

declare(strict_types=1);

use Marko\Core\Application;

/**
 * Marko Framework Bootstrap
 *
 * Legacy entry point for Marko applications.
 * For new projects, use Application::boot($basePath) instead.
 */
return function (
    string $vendorPath,
    string $modulesPath,
    string $appPath,
): Application {
    $app = new Application(
        vendorPath: $vendorPath,
        modulesPath: $modulesPath,
        appPath: $appPath,
    );

    $app->initialize();

    return $app;
};
```
