# Task 009: Application Integration

## Status
pending

## Depends On
006, 007

## Description
Integrate ConfigRepository with the Marko Application boot process so configuration is available to all modules.

## Requirements
- [ ] Update `packages/config/module.php` with bindings:
  - Bind `ConfigRepositoryInterface` to factory closure that creates ConfigRepository
  - Bind `ConfigLoader` to itself
  - Bind `ConfigMerger` to itself
  - Bind `ConfigDiscovery` to itself
- [ ] Create `ConfigServiceProvider` or factory that:
  - Uses ConfigDiscovery to find all config from loaded modules
  - Creates ConfigRepository with merged config
  - Makes it available in container
- [ ] Document integration with Application:
  - Config must be loaded early in boot (after module discovery)
  - ConfigRepository should be a singleton in the container
- [ ] Feature test covering:
  - Config loaded from module config/ directory
  - Config loaded from app config/ directory
  - App config overrides module config
  - ConfigRepository injectable via constructor

## Implementation Notes
<!-- Notes added during implementation -->
