# Task 017: `marko/scope` `module.php` bindings + singletons

**Status**: pending
**Depends on**: 006, 007, 015, 032
**Retry count**: 0

## Description
Wire all interfaces and shared services for `marko/scope`. Binds `ScopeRegistryInterface` to `PhpScopeRegistry`, registers `ScopeContext`, `ScopeMetadataFactory`, `ScopeResolver`, and `ScopedOrderByFactory` as singletons (shared across the request).

## Context
- Related files: `packages/scope/module.php`
- Patterns to follow: `.claude/architecture.md` § Dependency Injection. Use simple bindings where possible; closure bindings only for config-dependent construction.

## Requirements (Test Descriptions)
- [ ] `it binds ScopeRegistryInterface to PhpScopeRegistry`
- [ ] `it constructs PhpScopeRegistry from injected config repository`
- [ ] `it registers ScopeContext as a singleton`
- [ ] `it registers ScopeMetadataFactory as a singleton`
- [ ] `it registers ScopeResolver as a singleton`
- [ ] `it registers ScopedOrderByFactory as a singleton`
- [ ] `it does not bind ScopeSortRendererInterface` (driver packages bind it)
- [ ] `it throws a loud error if a ScopedOrderBy is used while no ScopeSortRendererInterface is bound`

## Acceptance Criteria
- `module.php` returns an array with `bindings` and `singletons` keys per the framework convention.
- `PhpScopeRegistry` construction reads `config/scope.php` via `ConfigRepositoryInterface`.
- The "missing renderer" error is exercised in a feature test.

## Implementation Notes
(Left blank — filled in during implementation.)
