# Plan: Layout System (Components All the Way Down)

## Created
2026-04-10

## Status
completed

## Objective
Create the `marko/layout` package — a PHP attribute-driven layout system where everything is a component. Layouts, page sections, and widgets are all components composed via `#[Component]` attributes, with cross-module extensibility via the existing Plugin/Preference system.

## Scope

### In Scope
- `#[Component]` attribute with metadata: `template`, `slot`, `handle`, `slots`, `sortOrder`, `before`, `after`
- `#[Layout]` attribute for controllers (class and method level)
- `HandleResolver` — auto-generates handles from route (first-segment_controller_method)
- Handle prefix matching (`'customer'` matches `customer_order_show`, `customer_wishlist_index`, etc.)
- `ComponentCollector` — discovers `#[Component]` classes, collects per handle
- `ComponentCollection` — ordered collection with `add()`, `remove()`, `move()`, `get()`, `all()` using class references
- `LayoutProcessor` — orchestrates full render pipeline with multi-pass rendering for nested slots
- `LayoutResolver` — reads `#[Layout]` from controller, resolves component class
- Route parameter injection into component `data()` methods (same mechanism as controller methods)
- `LayoutMiddleware` to detect `#[Layout]` and hand off to `LayoutProcessor` (Router is readonly, so Plugin interception is not possible)
- Latte `{slot}` custom tag in `view-latte` package
- Loud error exceptions: `ComponentNotFoundException`, `SlotNotFoundException`, `LayoutNotFoundException`, `DuplicateComponentException`, `CircularSlotException`, `AmbiguousSortOrderException`
- Package scaffolding: `composer.json`, `module.php`, config

### Out of Scope
- Asset management (`#[RequiresAsset]`) — deferred to `marko/assets`
- Component caching (`#[Cache]`) — deferred
- Component naming (using class references instead)
- `#[HandleGroup]` attribute (prefix matching replaces it)
- Blade/Twig slot tags (future view drivers)

## Success Criteria
- [ ] `#[Component]` and `#[Layout]` attributes work as designed
- [ ] Handle auto-generation and prefix matching work correctly
- [ ] Components are discovered, collected, sorted, and rendered per handle
- [ ] Nested slots (dot-notation) render via multi-pass
- [ ] Route parameters are injected into `data()` methods
- [ ] Cross-module manipulation works (add/remove/move via ComponentCollection)
- [ ] Router integration detects `#[Layout]` and delegates to LayoutProcessor
- [ ] Latte `{slot}` tag renders pre-assembled section HTML
- [ ] All tests passing with min 80% coverage
- [ ] Code follows project standards (phpcs, php-cs-fixer)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding (composer.json, module.php, config, exceptions) | - | completed |
| 002 | `#[Component]` attribute | - | completed |
| 003 | `#[Layout]` attribute | - | completed |
| 004 | `HandleResolver` — auto-generate handles from route/controller/method | - | completed |
| 005 | `ComponentDefinition` — resolved component data object | 002 | completed |
| 006 | `ComponentCollection` — ordered collection with add/remove/move/get | 005 | completed |
| 007 | `ComponentCollector` — discover and collect components per handle | 002, 004, 005, 006 | completed |
| 008 | `LayoutResolver` — read `#[Layout]` from controller, resolve component | 002, 003 | completed |
| 009 | Route parameter resolver for component `data()` methods | - | completed |
| 010 | `LayoutProcessor` — orchestrate full render pipeline (flat slots) | 006, 007, 008, 009 | completed |
| 011 | Nested slot rendering (dot-notation, multi-pass) | 010 | completed |
| 012 | Latte `{slot}` custom tag extension for `view-latte` | 010 | completed |
| 013 | Router integration via LayoutMiddleware — detect `#[Layout]` and delegate to LayoutProcessor | 008, 010 | completed |
| 014 | README.md | 001-013 | completed |

## Architecture Notes

### Package Structure
```
packages/layout/
├── src/
│   ├── Attributes/
│   │   ├── Component.php
│   │   └── Layout.php
│   ├── ComponentDefinition.php
│   ├── ComponentCollection.php
│   ├── ComponentCollector.php
│   ├── HandleResolver.php
│   ├── LayoutResolver.php
│   ├── LayoutProcessor.php
│   ├── ComponentDataResolver.php
│   ├── Middleware/
│   │   └── LayoutMiddleware.php
│   └── Exceptions/
│       ├── LayoutException.php
│       ├── ComponentNotFoundException.php
│       ├── SlotNotFoundException.php
│       ├── LayoutNotFoundException.php
│       ├── DuplicateComponentException.php
│       ├── CircularSlotException.php
│       └── AmbiguousSortOrderException.php
├── config/
│   └── layout.php
├── module.php
├── composer.json
└── tests/
```

### Key Patterns
- Follows `RouteDiscovery` pattern for component discovery (attribute scanning via reflection)
- Follows `RouteCollection` pattern for `ComponentCollection` (keyed by class, ordered)
- Follows `RouteDefinition` pattern for `ComponentDefinition` (readonly data object)
- Exceptions extend `LayoutException` which extends `MarkoException` (message/context/suggestion)
- `module.php` provides bindings for the layout system classes
- Route parameter resolution for `data()` mirrors the same `resolveParameters` pattern from `Router` (private, so mirrored not reused)
- No `LayoutProcessorInterface` — there's no alternate implementation scenario (not a driver)
- `marko/layout` depends on `marko/view` (interface), NOT `marko/view-latte` (implementation)

### Sorting Rules
- `before`/`after` constraints take priority over `sortOrder` (explicit relationships win)
- Within unconstrained components, `sortOrder` determines order
- Same `sortOrder` with no `before`/`after` constraints = loud error (ambiguous, not silently resolved)

### Naming
- `slot` (singular) = where this component renders (one target)
- `slots` (plural) = what sub-slots this component provides for children

### Handle Convention
- Auto-generated: `{first-route-segment}_{controller-minus-suffix}_{method}`
- Examples: `products_product_show`, `customer_order_index`, `index_home_index`
- `'default'` matches every page
- Prefix matching: `'customer'` matches all `customer_*` handles
- Class reference `[Controller::class, 'method']` resolves to full handle string

### Render Pipeline
1. Router matches request → finds controller + action → enters middleware pipeline
2. `LayoutMiddleware` checks for `#[Layout]` on controller via `LayoutResolver`; if none, passes through to normal flow
3. `HandleResolver` generates handle string from route/controller/action
4. `ComponentCollector` gathers all `#[Component]` classes matching the handle (including prefix matches and `'default'`)
5. `ComponentCollection` sorts by slot, then sortOrder/before/after
6. For each top-level slot: render components via `ViewInterface::renderToString()`
7. For nested slots: multi-pass — render parent components first, then fill sub-slots
8. Render layout template with assembled slot HTML
9. Return `Response`

### Component `data()` Resolution
- Route parameters injected by name (same as controller method resolution)
- Uses reflection on `data()` method signature
- Type casting: int, float, bool (same as Router)
- Optional `data()` — if class has no `data()` method, empty array used

## Risks & Mitigations
- **Multi-pass rendering complexity**: Nested slots require rendering parent components before children. Mitigation: detect nesting depth at collection time, validate no circular slot references, render in topological order.
- **Router integration coupling**: Modifying Router behavior risks breaking existing routes. Mitigation: use a `LayoutMiddleware` rather than modifying Router directly, keeping the layout package decoupled. Note: Plugin on Router is NOT possible because Router is `readonly` and has no interface -- the interceptor generator throws `PluginException::cannotInterceptReadonly()`.
- **Handle collision**: Two different routes could generate the same handle. Mitigation: HandleResolver detects collisions at discovery time and throws a loud error.
- **Performance of attribute scanning**: Component discovery scans all module classes. Mitigation: results are cached after first scan (same as route discovery).
