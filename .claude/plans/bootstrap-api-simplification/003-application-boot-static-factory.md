# Task 003: Add static Application::boot() factory method

**Status**: completed
**Depends on**: 001, 002
**Retry count**: 0

## Description
Add a static `boot(string $basePath)` factory method to `Application` that infers vendorPath, modulesPath, and appPath from the base path, calls `initialize()`, and returns the Application instance. This enables the simplified `Application::boot(dirname(__DIR__))` entry point.

## Context
- Related files: `packages/core/src/Application.php`, `packages/core/src/Path/ProjectPaths.php`
- `ProjectPaths` already derives vendor/modules/app from a base path — use the same conventions (`$basePath/vendor`, `$basePath/modules`, `$basePath/app`)
- The instance method has been renamed to `initialize()` (task 002). The static factory `boot()` calls `$app->initialize()` internally and returns the `$app` instance (return type `self`).
- Env loading: For now, `boot()` does NOT load env itself. Task 005 moves env loading into `initialize()`, so `boot()` gets it for free. This task just creates the static factory.

## Requirements (Test Descriptions)
- [ ] `it creates an application with inferred paths from base path using Application::boot()`
- [ ] `it sets vendorPath to basePath/vendor`
- [ ] `it sets modulesPath to basePath/modules`
- [ ] `it sets appPath to basePath/app`
- [ ] `it calls initialize() during boot() so the application is fully initialized`
- [ ] `it returns the Application instance (return type self)`
- [ ] `it throws RuntimeException when basePath is not a valid directory`

## Acceptance Criteria
- All requirements have passing tests
- Existing constructor signature unchanged
- `boot()` is a `public static` method returning `self`
- Static method uses same path conventions as ProjectPaths
- Code follows project standards

## Implementation Notes
```php
public static function boot(string $basePath): self
{
    if (!is_dir($basePath)) {
        throw new RuntimeException("Base path does not exist: {$basePath}");
    }

    $app = new self(
        vendorPath: $basePath . '/vendor',
        modulesPath: $basePath . '/modules',
        appPath: $basePath . '/app',
    );

    $app->initialize();

    return $app;
}
```

Testing note: The `boot()` tests will need a temp directory structure with valid module manifests (similar to existing ApplicationTest patterns). The initialize process discovers modules, so the temp directory needs at minimum a vendor directory.
