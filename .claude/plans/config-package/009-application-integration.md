# Task 009: Application Integration

## Status
completed

## Depends On
006, 007 (both completed)

## Description
Integrate ConfigRepository with the Marko Application boot process so configuration is available to all modules.

## Requirements
- [x] Update `packages/config/module.php` with bindings:
  - Bind `ConfigRepositoryInterface` to factory closure that creates ConfigRepository
  - Bind `ConfigLoader` to itself
  - Bind `ConfigMerger` to itself
  - Bind `ConfigDiscovery` to itself
- [x] Create `ConfigServiceProvider` or factory that:
  - Uses ConfigDiscovery to find all config from loaded modules
  - Creates ConfigRepository with merged config
  - Makes it available in container
- [x] Document integration with Application:
  - Config must be loaded early in boot (after module discovery)
  - ConfigRepository should be a singleton in the container
- [x] Feature test covering:
  - Config loaded from module config/ directory
  - Config loaded from app config/ directory
  - App config overrides module config
  - ConfigRepository injectable via constructor

## Implementation Notes
### Files Created/Modified
- `packages/config/module.php` - Added bindings for ConfigRepositoryInterface, ConfigLoader, ConfigMerger, ConfigDiscovery, and ConfigServiceProvider
- `packages/config/src/ConfigServiceProvider.php` - New service provider that creates ConfigRepository from discovered config files
- `packages/config/src/ConfigRepository.php` - Added missing `withScope()` method (pre-existing bug fix)
- `packages/config/tests/Unit/ModuleBindingsTest.php` - Tests for module.php bindings
- `packages/config/tests/Unit/ConfigServiceProviderTest.php` - Unit tests for ConfigServiceProvider
- `packages/config/tests/Feature/ConfigIntegrationTest.php` - Feature tests for full integration
- `packages/config/tests/Feature/fixtures/` - Test fixtures for integration tests
- `packages/config/tests/PackageStructureTest.php` - Updated to expect non-empty bindings

### Integration Pattern
The ConfigServiceProvider class documents the recommended integration with Application boot:
1. After module bindings are registered, bootstrap config
2. Get module paths from loaded modules
3. Call ConfigServiceProvider::createRepository() with module paths and app config path
4. Register the ConfigRepository as a singleton instance in the container
