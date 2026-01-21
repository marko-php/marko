# Task 008: Scoped Configuration Support

## Status
pending

## Depends On
006

## Description
Enhance ConfigRepository with robust scoped configuration support for multi-tenant applications.

## Requirements
- [ ] Implement scope resolution logic in ConfigRepository:
  - Config structure supports `default` and `scopes` keys at any level
  - Example: `['pricing' => ['default' => [...], 'scopes' => ['tenant-1' => [...]]]]`
- [ ] Scope resolution order:
  1. Check `config[key].scopes[scope].nestedKey` if scope provided
  2. Fall back to `config[key].default.nestedKey`
  3. Fall back to `config[key].nestedKey` (unscoped)
- [ ] Implement `withScope(string $scope): ConfigRepositoryInterface` method:
  - Returns a new ConfigRepository instance with default scope set
  - Allows `$tenantConfig->get('pricing.currency')` without passing scope every time
- [ ] Scope can be null to use unscoped values
- [ ] Unit tests covering:
  - Scoped value exists and is returned
  - Scope missing, falls back to default
  - Default missing, falls back to unscoped
  - withScope returns scoped repository
  - Scoped repository respects scope on all methods
  - Nested scoped values (multiple levels deep)

## Implementation Notes
<!-- Notes added during implementation -->
