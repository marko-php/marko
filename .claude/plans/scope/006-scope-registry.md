# Task 006: `ScopeRegistryInterface` + `PhpScopeRegistry`

**Status**: pending
**Depends on**: 002, 003, 004
**Retry count**: 0

## Description
Define the registry contract apps depend on and ship the PHP-config-backed default implementation. The interface is the extension point for the future DB-driven registry — apps never depend on the implementation.

## Context
- Related files: `packages/scope/src/Registry/ScopeRegistryInterface.php` (new), `packages/scope/src/Registry/PhpScopeRegistry.php` (new)
- Patterns to follow: Interface/impl split — see `marko/database` ConnectionInterface vs driver impls. Load axes from `marko/config` via `ConfigRepositoryInterface`.
- Config shape (from `config/scope.php`): `['axes' => ['geo' => ['hierarchy' => [...]], 'locale' => [...]]]`.

## Requirements (Test Descriptions)
- [ ] `it defines ScopeRegistryInterface with hasAxis, getAxis, listAxes, getHierarchy methods`
- [ ] `it loads axes from injected config into PhpScopeRegistry`
- [ ] `it returns ScopeAxis instances from getAxis for registered names`
- [ ] `it throws UnknownAxisException when getAxis is called with unknown axis`
- [ ] `it throws ScopeConfigurationException when config has duplicate axis names`
- [ ] `it throws ScopeConfigurationException when config shape is malformed`
- [ ] `it returns the list of all registered axis names in registration order`

## Acceptance Criteria
- Interface is in `Marko\Scope\Registry\ScopeRegistryInterface`.
- `PhpScopeRegistry` is `readonly class` if all properties are immutable after construction.
- Validation runs in the constructor; no axis is half-registered on failure.
- `getHierarchy(axisName)` returns the `ScopeHierarchy` from the axis.

## Implementation Notes
(Left blank — filled in during implementation.)
