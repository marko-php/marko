# Plan: Core Package (marko/core)

## Created
2026-01-19

## Status
in_progress

## Objective
Build the foundational `marko/core` package containing the DI container, module system, plugin system, event system, and bootstrap - the unified extensibility engine that makes Marko, Marko.

## Scope

### In Scope
- Package structure (composer.json, module.php, PSR-4 autoloading)
- DI Container with autowiring, bindings, and preferences
- Module discovery (scanning vendor/, modules/, app/)
- Module manifest parsing (module.php)
- Dependency resolution with topological sort
- Conflict detection with loud errors
- Plugin system (#[Plugin], #[Before], #[After])
- Event system (#[Observer], EventDispatcher)
- Bootstrap sequence (bootstrap.php)
- Demo application exercising all features

### Out of Scope
- Routing (separate marko/routing package)
- CLI commands (separate marko/cli package)
- Database, cache, view packages
- HTTP request/response handling
- Configuration file loading beyond module manifests

## Success Criteria
- [ ] Container resolves dependencies via autowiring
- [ ] Container respects interface → implementation bindings
- [ ] Container applies class → class preferences
- [ ] Modules discovered from vendor/, modules/, app/ directories
- [ ] Module dependencies resolved and loaded in correct order
- [ ] Binding conflicts throw loud, helpful errors
- [ ] Plugins intercept method calls with before/after hooks
- [ ] Events dispatch to registered observers
- [ ] Demo app boots and exercises all features
- [ ] All tests passing
- [ ] Code follows project standards (strict types, no final, etc.)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package structure and composer.json | - | completed |
| 002 | Exception classes | 001 | completed |
| 003 | Container interface and autowiring | 002 | completed |
| 004 | Module manifest and discovery | 002 | completed |
| 005 | Dependency resolution and module loading | 004 | pending |
| 006 | Binding registration from modules | 003, 005 | pending |
| 007 | Preference attribute and resolution | 006 | pending |
| 008 | Plugin attributes and discovery | 002, 005 | pending |
| 009 | Plugin interceptor (method wrapping) | 003, 008 | pending |
| 010 | Event system (Observer, Dispatcher) | 002, 005 | pending |
| 011 | Bootstrap sequence | 006, 007, 009, 010 | pending |
| 012 | Demo application | 011 | pending |

## Architecture Notes

### Container Resolution Order
1. Check explicit binding in requesting context
2. Check preferences (class → class replacement)
3. Check module-level bindings (app > modules > vendor)
4. Autowire if no binding exists

### Module Discovery Order
1. `vendor/*/` - Two levels deep (lowest priority)
2. `modules/**/` - Recursive (middle priority)
3. `app/*/` - One level deep (highest priority)

### Bootstrap Sequence
1. Autoload (Composer)
2. Bootstrap (execute bootstrap.php)
3. Scan (find modules via composer.json)
4. Parse (read composer.json + module.php)
5. Validate (check dependencies, detect conflicts)
6. Sort (topological sort for load order)
7. Boot (load modules, register bindings/plugins/observers)
8. Ready (container ready)

### Module Configuration Split
- **composer.json** (required): name, version, require - standard PHP package metadata
- **module.php** (optional): enabled, sequence, bindings - Marko-specific config

### Key Design Decisions
- Plugins wrap container resolution - when resolving a class with plugins, return a proxy
- Preferences are resolved before autowiring
- Binding conflicts are loud errors, not silent overwrites
- Everything is discovered via PHP 8 attributes, not configuration
- All modules require composer.json (enforces good PHP practices)

## Risks & Mitigations
- **Plugin proxy generation complexity**: Start with simple closure-based wrapping, optimize later if needed
- **Circular dependency detection**: Use standard DFS with visited/in-progress sets
- **Performance of attribute scanning**: Cache discovered attributes after first scan
