# Task 005: ConfigRepositoryInterface

## Status
completed

## Depends On
001

## Description
Define the contract for configuration access with type-safe methods and scope support.

## Requirements
- [ ] Create `ConfigRepositoryInterface` at `packages/config/src/ConfigRepositoryInterface.php`
- [ ] Define `get(string $key, mixed $default = null, ?string $scope = null): mixed` method
- [ ] Define `has(string $key, ?string $scope = null): bool` method
- [ ] Define type-safe accessor methods:
  - `getString(string $key, ?string $default = null, ?string $scope = null): string`
  - `getInt(string $key, ?int $default = null, ?string $scope = null): int`
  - `getBool(string $key, ?bool $default = null, ?string $scope = null): bool`
  - `getFloat(string $key, ?float $default = null, ?string $scope = null): float`
  - `getArray(string $key, ?array $default = null, ?string $scope = null): array`
- [ ] Define `all(?string $scope = null): array` method to get entire config
- [ ] Document that dot notation is supported for nested keys (e.g., 'database.host')
- [ ] Document that type-safe methods throw ConfigNotFoundException if key not found and no default provided
- [ ] Add proper @throws annotations

## Implementation Notes
<!-- Notes added during implementation -->
