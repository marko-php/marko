# Task 001: Package Structure

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the foundational package structure for `marko/core` including composer.json, directory layout, and the module manifest. This establishes the PSR-4 autoloading and package metadata.

## Context
- Location: `packages/core/`
- Namespace: `Marko\Core`
- This is the first package in the monorepo
- Follow the Package Internal Structure from architecture docs

## Requirements (Test Descriptions)
- [ ] `it has a valid composer.json with correct package name marko/core`
- [ ] `it has PSR-4 autoloading configured for Marko\Core namespace`
- [ ] `it has a module.php manifest with name marko/core`
- [ ] `it has src directory for source code`
- [ ] `it has tests/Unit directory for unit tests`
- [ ] `it has tests/Feature directory for feature tests`
- [ ] `it requires PHP 8.5 or higher`
- [ ] `it requires psr/container for PSR-11 ContainerInterface`
- [ ] `it requires pestphp/pest as dev dependency for testing`

## Acceptance Criteria
- All requirements have passing tests
- composer.json validates with `composer validate`
- Directory structure matches architecture standard
- module.php returns valid array structure

## Files to Create
```
packages/core/
  composer.json
  module.php
  src/
    .gitkeep
  tests/
    Unit/
      .gitkeep
    Feature/
      .gitkeep
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
