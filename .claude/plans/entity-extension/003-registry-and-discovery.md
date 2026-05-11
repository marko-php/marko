# Task 003: EntityExtensionRegistry + EntityExtensionDiscovery

**Status**: completed
**Depends on**: [001, 002]
**Retry count**: 0

## Description
Create `EntityExtensionRegistry` — a singleton that maps entity class strings to their registered extension class strings — and `EntityExtensionDiscovery` that scans `src/EntityExtension/` directories across vendor/modules/app for classes bearing `#[ExtensionOf]`. Discovery validates that the target entity class is actually an `Entity` subclass.

## Context
- Related files:
  - `packages/database/src/Entity/EntityDiscovery.php` — exact parallel; follow its scanning pattern
  - `packages/core/src/Discovery/ClassFileParser.php` — used to find/load PHP files
  - `packages/database/src/Entity/Entity.php`
  - `packages/database/src/Entity/EntityExtension.php` (from task 001)
  - `packages/database/src/Attributes/ExtensionOf.php` (from task 001)
  - `packages/database/src/Entity/EntityExtensionMetadataFactory.php` (from task 002)
- `EntityExtensionDiscovery` takes a `ClassFileParser` via constructor (mirror `EntityDiscovery`)
- `EntityExtensionDiscovery` scans:
  - `vendor/*/*/src/EntityExtension/` (two vendor levels, like `EntityDiscovery`)
  - `modules/*/*/src/EntityExtension/` (two module levels)
  - `app/*/EntityExtension/` and `app/*/src/EntityExtension/`
- Each `discoverIn*` method MUST return `array<array{0: class-string<Entity>, 1: class-string<EntityExtension>}>` — i.e. a list of `[entityClass, extensionClass]` pairs. This is consumed by the boot wiring in task 009.
- Discovery throws a loud `EntityException` if `#[ExtensionOf]` names a class that does not extend `Entity` (new factory method on `EntityException`: `extensionOfTargetNotEntity`)
- Discovery silently skips files without `#[ExtensionOf]` or that don't extend `EntityExtension`
- `EntityExtensionRegistry::register(string $entityClass, string $extensionClass)` is idempotent (safe to call twice with the same pair)
- `EntityExtensionRegistry::getExtensions(string $entityClass): array<class-string<EntityExtension>>` returns all registered extension classes for an entity, empty array if none
- `EntityExtensionRegistry` is not responsible for parsing; it only holds the mapping
- `EntityExtensionRegistry` is mutable (state added via `register()`); do NOT mark it `readonly`

## Requirements (Test Descriptions)
- [ ] `it registers an extension class against its target entity class`
- [ ] `it returns all registered extension classes for an entity`
- [ ] `it returns empty array for entity with no registered extensions`
- [ ] `it is idempotent when registering the same pair twice`
- [ ] `it discovers extension classes in vendor src/EntityExtension directories`
- [ ] `it discovers extension classes in modules src/EntityExtension directories`
- [ ] `it discovers extension classes in app EntityExtension directories`
- [ ] `it skips classes without ExtensionOf attribute`
- [ ] `it skips classes that do not extend EntityExtension`
- [ ] `it throws when ExtensionOf target class does not extend Entity`

## Acceptance Criteria
- All requirements have passing tests
- `EntityExtensionRegistry` lives at `Marko\Database\Entity\EntityExtensionRegistry`
- `EntityExtensionDiscovery` lives at `Marko\Database\Entity\EntityExtensionDiscovery`
- `EntityExtensionRegistry` exposes `register(string $entityClass, string $extensionClass): void` and `getExtensions(string $entityClass): array<class-string<EntityExtension>>`
- `EntityExtensionDiscovery` exposes `discoverInVendor()`, `discoverInModules()`, `discoverInApp()`, each returning `array<array{0: class-string<Entity>, 1: class-string<EntityExtension>}>`
- New factory method added to `Marko\Database\Exceptions\EntityException`: `extensionOfTargetNotEntity(string $extensionClass, string $targetClass)`
- Code follows project standards
