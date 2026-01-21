# Task 006: ConfigRepository Implementation

## Status
pending

## Depends On
003, 004, 005

## Description
Implement the ConfigRepository class providing configuration access with dot notation and scoped configuration.

## Requirements
- [ ] Create `ConfigRepository` class at `packages/config/src/ConfigRepository.php`
- [ ] Implement `ConfigRepositoryInterface`
- [ ] Constructor accepts:
  - `array $config` - The merged configuration array
- [ ] Implement dot notation parsing in `get()`:
  - `get('database.host')` navigates to `$config['database']['host']`
  - Returns default if any part of path doesn't exist
- [ ] Implement `has()` with dot notation support
- [ ] Implement type-safe accessors:
  - Throw `ConfigNotFoundException` when key not found and no default
  - Validate type and throw `ConfigException` on type mismatch
- [ ] Implement scoped configuration:
  - When scope provided, first check `$config[$topKey]['scopes'][$scope][$restOfKey]`
  - Fall back to `$config[$topKey]['default'][$restOfKey]`
  - Then fall back to unscoped value
- [ ] Class should be readonly (config is immutable after construction)
- [ ] Unit tests covering:
  - Simple key access
  - Dot notation access (2+ levels deep)
  - Default value when key missing
  - has() returns true/false correctly
  - Each type-safe accessor with valid type
  - Type mismatch throws exception
  - Missing key without default throws ConfigNotFoundException
  - Scoped access with scope-specific value
  - Scoped access falls back to default
  - Scoped access falls back to unscoped
  - all() returns entire config

## Implementation Notes
<!-- Notes added during implementation -->
