# Task 007: ConfigDiscovery

## Status
pending

## Depends On
004

## Description
Implement ConfigDiscovery to scan module config/ directories and merge configuration from all sources.

## Requirements
- [ ] Create `ConfigDiscovery` class at `packages/config/src/ConfigDiscovery.php`
- [ ] Constructor accepts:
  - `ConfigLoader $loader`
  - `ConfigMerger $merger`
- [ ] Implement `discover(array $modulePaths, string $rootConfigPath): array` method:
  - Scans each module path for `config/` directory
  - Loads all `.php` files from each config directory
  - Merges configs in order (later modules override earlier)
  - Finally merges root config path (highest priority)
  - Returns fully merged configuration array
- [ ] Config files are keyed by filename without extension:
  - `config/database.php` becomes `['database' => [...]]`
  - `config/cache.php` becomes `['cache' => [...]]`
- [ ] Handle missing config directories gracefully (skip)
- [ ] Unit tests covering:
  - Single module with config
  - Multiple modules with overlapping config
  - Module without config directory (skip gracefully)
  - Root config overrides module config
  - Multiple config files per module
  - Priority order is respected

## Implementation Notes
<!-- Notes added during implementation -->
