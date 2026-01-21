# Task 008: Scoped Configuration Support

## Status
completed

## Depends On
006 (completed)

## Description
Enhance ConfigRepository with robust scoped configuration support for multi-tenant applications.

## Requirements
- [x] Implement scope resolution logic in ConfigRepository:
  - Config structure supports `default` and `scopes` keys at any level
  - Example: `['pricing' => ['default' => [...], 'scopes' => ['tenant-1' => [...]]]]`
- [x] Scope resolution order:
  1. Check `config[key].scopes[scope].nestedKey` if scope provided
  2. Fall back to `config[key].default.nestedKey`
  3. Fall back to `config[key].nestedKey` (unscoped)
- [x] Implement `withScope(string $scope): ConfigRepositoryInterface` method:
  - Returns a new ConfigRepository instance with default scope set
  - Allows `$tenantConfig->get('pricing.currency')` without passing scope every time
- [x] Scope can be null to use unscoped values
- [x] Unit tests covering:
  - Scoped value exists and is returned
  - Scope missing, falls back to default
  - Default missing, falls back to unscoped
  - withScope returns scoped repository
  - Scoped repository respects scope on all methods
  - Nested scoped values (multiple levels deep)

## Implementation Notes
- The scope resolution logic was already partially implemented in the existing codebase
- Added `withScope(string $scope): ConfigRepositoryInterface` method to the interface and implementation
- Changed ConfigRepository from `readonly class` to regular class with `readonly` properties to support the `defaultScope` constructor parameter
- Updated `get()` and `has()` methods to use `$effectiveScope = $scope ?? $this->defaultScope`
- All typed accessor methods (getString, getInt, getBool, getFloat, getArray) automatically benefit from the scope resolution because they delegate to `get()`
- Added comprehensive tests covering all scope resolution scenarios including nested values multiple levels deep
