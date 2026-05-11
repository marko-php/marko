# Task 009: Boot wiring — populate registry via discovery in module.php

**Status**: completed
**Depends on**: [003]
**Retry count**: 0

## Description
Wire `EntityExtensionRegistry` as a singleton in `database/module.php` and populate it at boot via `EntityExtensionDiscovery`. The registry must be fully populated before any repository is resolved from the container.

## Context
- Related files:
  - `packages/database/module.php` — currently only declares `bindings`; this task adds `singletons` and `boot` keys
  - `packages/database/src/Entity/EntityExtensionRegistry.php` (from task 003)
  - `packages/database/src/Entity/EntityExtensionDiscovery.php` (from task 003)
  - `packages/database/src/Entity/EntityExtensionMetadataFactory.php` (from task 002)
  - `Marko\Core\Path\ProjectPaths` — already in container, provides `->vendor`, `->modules`, `->app`
- **Current state of `module.php`**: only has the `bindings` array (with `SeederDiscoveryInterface` and the `SeederRunner` closure). There is NO `singletons` or `boot` key today. Both must be ADDED, not edited.
- Reference for `boot` autowiring: `packages/debugbar/module.php` (`'boot' => static function (Debugbar $debugbar): void { ... }`) and `packages/notification/module.php`. The `Application::bootModules()` method (`packages/core/src/Application.php`, around line 178-184) uses `$this->container->call($module->boot)`, so parameter type-hints are autowired from the container.
- Pattern to follow:
  ```php
  // In module.php top-level return array:
  'singletons' => [
      EntityExtensionRegistry::class => EntityExtensionRegistry::class,
  ],
  'boot' => static function (
      EntityExtensionRegistry $registry,
      EntityExtensionDiscovery $discovery,
      ProjectPaths $paths,
  ): void {
      $pairs = array_merge(
          $discovery->discoverInVendor($paths->vendor),
          $discovery->discoverInModules($paths->modules),
          $discovery->discoverInApp($paths->app),
      );
      foreach ($pairs as [$entityClass, $extensionClass]) {
          $registry->register($entityClass, $extensionClass);
      }
  },
  ```
- The discover methods return `array<array{0: class-string<Entity>, 1: class-string<EntityExtension>}>` per task 003 — destructuring is safe.
- `EntityExtensionRegistry` MUST be in `singletons` so the registry instance shared with `EntityMetadataFactory` (injected via the container) is the same one populated at boot.
- `EntityExtensionDiscovery` and `EntityExtensionMetadataFactory` are autowirable (no special binding needed); both will resolve via the container.
- **Boot ordering caveat**: per `Application::bootModules()` in core, boot callbacks run AFTER all modules' bindings are registered. The registry will be populated before any controller/route handler resolves a Repository, so first-parse metadata will include extensions. Test wiring (unit tests that instantiate `EntityMetadataFactory` directly) does NOT trigger boot — those tests pass `null` registry per task 005's backward-compat shim.
- This task only touches `module.php`; no new PHP classes are needed.

## Requirements (Test Descriptions)
- [x] `it registers EntityExtensionRegistry as a singleton in the container`
- [x] `it declares a boot callback in module.php that populates the registry from discovery`

Note: these are integration-level assertions. Test by `require`-ing `packages/database/module.php` and asserting the array contains a `singletons` key with `EntityExtensionRegistry::class` and a `boot` key whose value is a callable. Structural assertions on the module.php export array are sufficient for this task; full container-boot integration is covered by the existing `PackageScaffoldingTest` patterns. Do NOT shell out to spin up a full app.

## Acceptance Criteria
- All requirements have passing tests
- `EntityExtensionRegistry` is in `singletons`
- Boot callback populates the registry using `EntityExtensionDiscovery` and `ProjectPaths`
- Code follows project standards
