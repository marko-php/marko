# Plan: Layout Component Discovery

## Created
2026-04-10

## Status
completed

## Objective
Wire up automatic component discovery so `LayoutProcessor` actually finds `#[Component]` classes from modules. Currently `collect([], $handle)` passes an empty array, so no components are ever discovered.

## Scope

### In Scope
- `DiscoveringComponentCollector` — wraps `ComponentCollector`, scans module `src/` dirs for `#[Component]` classes via `ClassFileParser`
- Module bindings in `layout/module.php` — bind `ComponentCollectorInterface` → `DiscoveringComponentCollector`, `LayoutProcessorInterface` → `LayoutProcessor`, singletons for `HandleResolver` and `LayoutResolver`
- No changes to `LayoutProcessor` needed — the `[]` argument is semantically correct ("no additional classes"), and the discovering collector supplies discovered classes internally

### Out of Scope
- Preference-aware component resolution (future enhancement)
- Component caching (deferred to `marko/cache` integration)
- Changes to `Application.php` boot flow (middleware already registered)

## Success Criteria
- [ ] `DiscoveringComponentCollector` scans modules and discovers `#[Component]` classes
- [ ] `ComponentCollectorInterface` is bound to `DiscoveringComponentCollector` in `module.php`
- [ ] `LayoutProcessor` delegates discovery to the collector (via `ComponentCollectorInterface` binding)
- [ ] Missing/uninstalled package classes handled gracefully
- [ ] All tests passing with min 80% coverage
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | `DiscoveringComponentCollector` — scan modules for `#[Component]` classes | - | completed |
| 002 | Module bindings and `LayoutProcessor` wiring | 001 | completed |

## Architecture Notes

### Pattern Reference
Follows `RoutingBootstrapper::findControllerClasses()` pattern:
1. Iterate `ModuleRepositoryInterface::all()` for module manifests
2. Use `ClassFileParser::findPhpFiles()` to scan each module's `src/` directory
3. Use `ClassFileParser::extractClassName()` + `loadClass()` for each file
4. Check via reflection if class has `#[Component]` attribute
5. Collect matching class names and pass to inner `ComponentCollector::collect()`

### Key Design Decisions
- `DiscoveringComponentCollector` implements `ComponentCollectorInterface` (decorator pattern)
- The inner `ComponentCollector` is injected via constructor as the concrete class (not interface) to avoid circular DI binding
- The inner `ComponentCollector` stays pure — accepts explicit class lists for testability
- `DiscoveringComponentCollector` overrides `collect()` to inject discovered classes, delegates `discoverFromClass()` directly
- The `$classNames` parameter in `collect()` acts as additional classes (merged with discovered ones), keeping the API flexible
- Module scanning happens per `collect()` call (no caching yet — note: unlike route discovery which runs once at boot, this runs per-request; caching deferred)

### Wiring
- `module.php` binds `ComponentCollectorInterface::class` → `DiscoveringComponentCollector::class`
- `module.php` also adds singletons for `HandleResolver`, `LayoutResolver`, and binds `LayoutProcessorInterface` → `LayoutProcessor`
- `DiscoveringComponentCollector` constructor: `ModuleRepositoryInterface`, `ClassFileParser`, `ComponentCollector` (concrete class, not interface — avoids circular binding since the interface maps to `DiscoveringComponentCollector` itself)
- `LayoutProcessor` already depends on `ComponentCollectorInterface` — no changes needed to its constructor

## Risks & Mitigations
- **Performance of scanning all module files**: Same concern as route discovery. Mitigation: future caching layer (out of scope).
- **Missing package classes during scan**: Handled by `ClassFileParser::loadClass()` which already skips uninstalled Marko packages gracefully.
