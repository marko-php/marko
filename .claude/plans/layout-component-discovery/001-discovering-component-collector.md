# Task 001: DiscoveringComponentCollector

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `DiscoveringComponentCollector` class that wraps `ComponentCollector` and automatically discovers `#[Component]` classes from all module `src/` directories. Follows the same scanning pattern as `RoutingBootstrapper::findControllerClasses()` using `ClassFileParser`.

## Context
- Related files: `packages/routing/src/RoutingBootstrapper.php` (scanning pattern to follow), `packages/core/src/Discovery/ClassFileParser.php` (file scanning + class loading), `packages/layout/src/ComponentCollector.php` (inner collector to wrap), `packages/layout/src/ComponentCollectorInterface.php` (interface to implement)
- Namespace: `Marko\Layout`
- Lives at `packages/layout/src/DiscoveringComponentCollector.php`
- Constructor takes: `ModuleRepositoryInterface $moduleRepository`, `ClassFileParser $classFileParser`, `ComponentCollector $componentCollector` (inject concrete class, not the interface, to avoid circular DI binding since the interface maps to `DiscoveringComponentCollector` itself)
- Does NOT internally `new` the `ComponentCollector` -- receives it via constructor injection per code-standards rule #2
- `collect()` scans all modules for `#[Component]` classes, merges with any explicitly passed `$classNames`, then delegates to the inner `ComponentCollector::collect()`
- `discoverFromClass()` delegates directly to the inner `ComponentCollector::discoverFromClass()`
- Uses `ClassFileParser::findPhpFiles()`, `extractClassName()`, `loadClass()` to scan
- Uses reflection to check for `#[Component]` attribute at the CLASS level: `(new ReflectionClass($className))->getAttributes(Component::class) !== []`. Note: unlike `RoutingBootstrapper::hasRouteAttributes()` which checks method-level attributes, `#[Component]` is `Attribute::TARGET_CLASS` so check the class itself
- Handles missing classes from uninstalled packages gracefully (already handled by `ClassFileParser::loadClass()`)

## Requirements (Test Descriptions)
- [ ] `it discovers classes with Component attribute from module src directories`
- [ ] `it skips classes without Component attribute`
- [ ] `it skips files that fail to load gracefully`
- [ ] `it merges explicitly passed class names with discovered classes`
- [ ] `it delegates to inner ComponentCollector for handle matching`
- [ ] `it delegates discoverFromClass to inner collector`
- [ ] `it scans all modules from ModuleRepositoryInterface`
- [ ] `it handles modules without src directories gracefully`

## Acceptance Criteria
- All requirements have passing tests
- Class should be `readonly` since all dependencies are constructor-injected and immutable (per code-standards rule #4: if ALL promoted properties are readonly, use `readonly class`)
- Follows RoutingBootstrapper scanning pattern
- Handles missing package classes gracefully
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
