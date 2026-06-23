# Marko Framework Core Guidelines

## Strict Types
Every PHP file must declare `declare(strict_types=1);`.

## Constructor Property Promotion
Use constructor property promotion always. Avoid traditional property + assignment patterns.

## No Final Classes
Marko's Preferences system requires extensibility. Do not mark classes as `final`.

## No Magic Methods
Avoid `__get`, `__set`, `__call`. Be explicit.

## Type Declarations
All parameters, return types, and properties require type declarations. Use the narrowest type.

## Loud Errors
All exceptions extend `MarkoException` with `message`, `context`, `suggestion` named parameters.

## No Traits
Use explicit constructor composition instead; traits hide where behavior comes from.

## Interface Over Driver
Depend on the interface package (e.g. `marko/log`), never the driver (e.g. `marko/log-file`); let the app choose the driver via its module config.

## Constructor Injection Only
Inject all dependencies via constructor; never call `Container::get()` in app code (service locator anti-pattern).

## Config Is the Source of Truth
Defaults live in `config/*.php`; config getters throw `ConfigNotFoundException` — no fallback parameter. Reference `$_ENV` only inside config files.

## Formatting and Linting
The LSP and formatter enforce import ordering, PSR-12, multiline signatures, and similar rules automatically — you need not police them.

## Deeper Documentation
For "how X works" depth (DI resolution, plugin sortOrder, route rules, scoped-config cascade), call the `search_docs` MCP tool — it indexes the full framework docs.
