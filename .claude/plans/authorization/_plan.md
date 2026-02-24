# Plan: Authorization Package

## Created
2026-02-24

## Status
completed

## Objective
Build `marko/authorization` — a policy-based authorization system with Gates (closure-based ability checks), Policies (class-based entity authorization), and a `#[Can]` route attribute with middleware integration. Integrates with `marko/auth` guards to resolve the current user.

## Scope

### In Scope
- `GateInterface` for registering and checking closure-based abilities
- `Gate` implementation with `define()`, `allows()`, `denies()`, `authorize()`
- `PolicyInterface` for entity-scoped authorization with convention-based discovery
- `#[Can]` route attribute (method-level) for declarative authorization
- `AuthorizationMiddleware` that reads `#[Can]` attributes via reflection and enforces them
- Enhanced `AuthorizationException` with ability/resource context
- `AuthorizableInterface` for users that support authorization (extends beyond basic auth)
- Config class for default guard selection
- `module.php` bindings for container wiring

### Out of Scope
- Role-based access control (admin-auth already handles this)
- Database-persisted permissions (admin-auth handles this)
- Policy auto-discovery from filesystem (explicit registration only)
- Demo/app customizations

## Success Criteria
- [x] Gate can register and check closure-based abilities
- [x] Policies provide entity-scoped authorization with conventional method names
- [x] `#[Can]` attribute on routes triggers authorization checks via middleware
- [x] `AuthorizationMiddleware` integrates with routing pipeline
- [x] Guest users handled correctly (deny by default unless gate/policy allows)
- [x] All tests passing with >90% coverage
- [x] Code follows project standards (strict types, constructor promotion, no final)

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | AuthorizableInterface and AuthorizationException | - | completed |
| 002 | GateInterface and Gate implementation | 001 | completed |
| 003 | PolicyInterface and policy resolution | 001 | completed |
| 004 | Gate-Policy integration (Gate delegates to policies) | 002, 003 | completed |
| 005 | #[Can] attribute and AuthorizationMiddleware | 004 | completed |
| 006 | Module wiring, config, and composer.json | 005 | completed |

## Architecture Notes
- Follows `marko/auth` exception patterns (message, context, suggestion)
- `#[Can]` attribute mirrors `#[RequiresPermission]` from admin-auth but for general authorization
- Gate is the central facade: checks closures first, then delegates to policies
- Policies are registered explicitly via `Gate::policy(EntityClass, PolicyClass)`
- AuthorizationMiddleware uses reflection like AdminAuthMiddleware to read `#[Can]` from controller methods
- Package lives at `packages/authorization/` with namespace `Marko\Authorization`

## Risks & Mitigations
- **Overlap with admin-auth permissions**: Clear separation — admin-auth is role/permission based for admin panel; authorization is policy/gate based for application logic
- **Middleware needs controller/action context**: Follow AdminAuthMiddleware pattern which receives controller/action via constructor
