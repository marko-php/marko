# Plan: Standardize NoDriverException Convention

## Created
2026-03-26

## Status
completed

## Objective
Replace the filesystem-scanning driver discovery in `BindingException` with a convention-based approach: each interface package ships a `NoDriverException` with a flat `DRIVER_PACKAGES` constant listing known implementations. The container detects and throws these specific exceptions instead of the generic `BindingException`.

## Scope

### In Scope
- Add `NoDriverException` to all 17 interface packages that have driver implementations
- Wire the convention into `Container.php` so it throws `NoDriverException` instead of generic `BindingException`
- Remove `discoverDriverPackages()` and `scanForDriverPackages()` from `BindingException`
- Update existing `NoDriverException` in `view` and `DatabaseException` in `database` to follow the standard pattern
- Remove hardcoded driver suggestions from `mail`, `queue`, `notification`, `filesystem` exception classes
- Update all related tests

### Out of Scope
- Packagist API integration
- Auto-discovery of community packages
- Changes to driver packages themselves (they already register bindings via `module.php`)

## Success Criteria
- [ ] All 17 interface packages have a `NoDriverException` with `DRIVER_PACKAGES` constant
- [ ] Container throws `NoDriverException` (not generic `BindingException`) when resolving unbound Marko interfaces that have drivers
- [ ] Filesystem scanning removed from `BindingException`
- [ ] Hardcoded `noDriverInstalled()` methods removed from `MailException` and `QueueException` (other methods on those classes preserved)
- [ ] `NotificationException::noQueueAvailable()` preserved (runtime code dependency)
- [ ] `DatabaseException::noDriverInstalled(string $driver)` preserved with renamed `KNOWN_DRIVERS` constant
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Wire NoDriverException convention into Container | - | pending |
| 002 | Update view package NoDriverException | 001 | pending |
| 003 | Add NoDriverException to database package | 001 | pending |
| 004 | Add NoDriverException to cache package | 001 | pending |
| 005 | Add NoDriverException to session package | 001 | pending |
| 006 | Add NoDriverException to mail package | 001 | pending |
| 007 | Add NoDriverException to queue package | 001 | pending |
| 008 | Add NoDriverException to filesystem package | 001 | pending |
| 009 | Add NoDriverException to encryption package | 001 | pending |
| 010 | Add NoDriverException to http package | 001 | pending |
| 011 | Add NoDriverException to log package | 001 | pending |
| 012 | Add NoDriverException to media package | 001 | pending |
| 013 | Add NoDriverException to pubsub package | 001 | pending |
| 014 | Add NoDriverException to translation package | 001 | pending |
| 015 | Add NoDriverException to errors package | 001 | pending |
| 016 | Add NoDriverException to authentication package | 001 | pending |
| 017 | Add NoDriverException to notification package | 001 | pending |
| 018 | Add NoDriverException to admin package | 001 | pending |
| 019 | Clean up hardcoded suggestions from other exception classes | 002-018 | pending |

## Architecture Notes

### Convention
When resolving `Marko\{Package}\SomeInterface` fails in the container:
1. Extract the package namespace (second segment after `Marko\`)
2. Check if `Marko\{Package}\Exceptions\NoDriverException` exists
3. Check if it has a static `noDriverInstalled()` method
4. If yes, throw that instead of `BindingException::noImplementation()`

### Standard NoDriverException Pattern
```php
class NoDriverException extends {PackageBaseException}
{
    private const array DRIVER_PACKAGES = [
        'marko/view-latte',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No driver installed for {package description}.',
            context: 'Attempted to resolve {InterfaceName} but no implementation is bound.',
            suggestion: "Install a driver package:\n$packageList",
        );
    }
}
```

### Exception Base Classes
Each `NoDriverException` extends its package's base exception:
- `view`: `ViewException` (extends `MarkoException`)
- `database`: `MarkoException` (no base exception class)
- `cache`: `CacheException` (extends `Exception`)
- `session`: `SessionException` (extends `Exception`)
- `mail`: `MarkoException` directly (NOTE: mail has two exception dirs -- `Exception/` singular with `MailException` in `Marko\Mail\Exception`, and `Exceptions/` plural with `MessageException` in `Marko\Mail\Exceptions`. NoDriverException must go in `Exceptions/` plural to match the container convention)
- `queue`: `QueueException` (extends `MarkoException`)
- `filesystem`: `FilesystemException` (extends `Exception`)
- `encryption`: `EncryptionException` (extends `Exception`)
- `http`: `HttpException` (extends `Exception`)
- `log`: `LogException` (extends `Exception`)
- `media`: `MediaException` (extends `MarkoException`)
- `pubsub`: `PubSubException` (extends `MarkoException`)
- `translation`: `TranslationException` (extends `MarkoException`)
- `errors`: No exception class exists — create `ErrorsException` extending `MarkoException`
- `authentication`: `AuthException` (extends `Exception`)
- `notification`: `NotificationException` (extends `MarkoException`)
- `admin`: `AdminException` (extends `MarkoException`)

## Risks & Mitigations
- **Class loading overhead**: `class_exists()` + `method_exists()` on every unbound interface resolution — mitigated by the fact this only runs on error paths, not hot paths
- **Packages with Exception extending Exception (not MarkoException)**: NoDriverException needs `message`, `context`, `suggestion` named params from `MarkoException`. For packages whose base extends `Exception` directly, `NoDriverException` should extend `MarkoException` directly instead of the package base exception, to get the structured error format
- **Runtime callers of existing methods**: `NotificationException::noQueueAvailable()` is called from `NotificationSender.php` -- must not be removed. `MailException::configFileNotFound()` and `QueueException::configFileNotFound()` are called from runtime code -- those classes must not be deleted, only their `noDriverInstalled()` methods removed
- **Filesystem `DriverRegistry` hardcoded suggestion**: The suggestion in `DriverRegistry::get()` is about an unknown driver name at runtime, not about no driver being installed. This is a different concern from `NoDriverException` and should be left alone or updated independently
