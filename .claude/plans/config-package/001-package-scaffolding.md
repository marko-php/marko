# Task 001: Package Scaffolding

## Status
completed

## Depends On
None

## Description
Create the basic package structure for `marko/config` including composer.json and module.php.

## Requirements
- [ ] Create `packages/config/composer.json` with:
  - Name: `marko/config`
  - Description: "Configuration loading and merging for Marko Framework"
  - Type: `marko-module`
  - License: MIT
  - PHP requirement: ^8.5
  - PSR-4 autoload for `Marko\Config\` namespace pointing to `src/`
  - Dev autoload for `Marko\Config\Tests\` namespace pointing to `tests/`
  - Dependency on `marko/core`
- [ ] Create `packages/config/module.php` with:
  - `enabled` => true
  - Empty `bindings` array (bindings added in later tasks)
- [ ] Create `packages/config/src/` directory (empty placeholder file or .gitkeep)
- [ ] Create `packages/config/tests/` directory structure (Unit/, Feature/)

## Implementation Notes
<!-- Notes added during implementation -->
