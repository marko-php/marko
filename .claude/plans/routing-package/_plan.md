# Plan: Routing Package (marko/routing)

## Created
2026-01-20

## Status
completed

## Objective
Build the `marko/routing` package providing attribute-based routing with HTTP method attributes (#[Get], #[Post], etc.), route parameters, middleware support, route conflict detection, and DisableRoute for explicit route removal - all integrating cleanly with the core extensibility system.

## Scope

### In Scope
- Package structure (composer.json, PSR-4 autoloading)
- Route attributes (#[Get], #[Post], #[Put], #[Patch], #[Delete])
- Route parameters (`/posts/{slug}`)
- Middleware attribute and pipeline
- Route discovery from controller classes
- Route collection and matching
- Route conflict detection with loud errors
- DisableRoute attribute for explicit route removal
- Preference inheritance rules for routes
- Router integration with core Application
- Demo app exercising routes with blog controllers
- HTTP Request/Response value objects

### Out of Scope
- Named routes (future enhancement)
- Route groups/prefixes (future enhancement)
- Route caching (optimization for later)
- Advanced regex constraints on parameters
- Optional route parameters
- Rate limiting middleware (separate package)

## Success Criteria
- [x] Route attributes define HTTP method and path
- [x] Route parameters are extracted and passed to controller methods
- [x] Middleware can intercept requests before/after controller
- [x] Controllers resolved through DI container
- [x] Routes discovered automatically from modules
- [x] Duplicate routes throw loud RouteConflictException
- [x] DisableRoute explicitly removes inherited routes
- [x] Method override without route attribute throws RouteException
- [x] Preferences properly inherit/override parent routes
- [x] Demo app handles real HTTP requests
- [x] All tests passing
- [x] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package structure and composer.json | - | completed |
| 002 | Exception classes (RouteException, RouteConflictException) | 001 | completed |
| 003 | HTTP Request/Response value objects | 001 | completed |
| 004 | Route attributes (#[Get], #[Post], etc.) | 001 | completed |
| 005 | Middleware attribute and interface | 001 | completed |
| 006 | DisableRoute attribute | 001 | completed |
| 007 | RouteDefinition value object | 004, 005 | completed |
| 008 | Route discovery from controllers | 004, 006, 007 | completed |
| 009 | Route collection and conflict detection | 002, 007, 008 | completed |
| 010 | Route matching and parameter extraction | 007, 009 | completed |
| 011 | Middleware pipeline | 003, 005 | completed |
| 012 | Router class (orchestrates everything) | 009, 010, 011 | completed |
| 013 | Preference route inheritance | 002, 008 | completed |
| 014 | Application integration | 012, 013 | completed |
| 015 | Demo app with blog routes | 014 | completed |

## Architecture Notes

### Route Discovery Flow
1. Scan all modules for controller classes
2. For each class, check if any method has route attributes
3. Build RouteDefinition from attribute + controller + method
4. Check for preferences that override the controller
5. Apply inheritance rules for routed methods
6. Register routes in RouteCollection

### Middleware Pipeline
```
Request → Middleware1 → Middleware2 → Controller → Middleware2 → Middleware1 → Response
```
Middleware can short-circuit by returning Response early.

### Preference Inheritance Rules
| Scenario | Result |
|----------|--------|
| Method not overridden | Parent's route attribute applies |
| Method overridden with route attribute | Child's route attribute applies |
| Method overridden with #[DisableRoute] | Route is removed |
| Method overridden with no attribute | **ERROR** (RouteException) |

### Integration Points
- Router gets container from Application for controller resolution
- Route discovery uses module list from Application
- Middleware resolved through container (supports DI)

## Risks & Mitigations
- **Performance of reflection-based discovery**: Keep it simple first; add caching later if needed
- **Complex inheritance edge cases**: Comprehensive tests for all preference scenarios
- **Parameter type coercion**: Start with string parameters; add type coercion in future version
