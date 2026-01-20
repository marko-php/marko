# Task 001: Package Structure

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the foundational package structure for `marko/routing` including composer.json with dependency on marko/core, directory layout, and PSR-4 autoloading. This is a separate package that extends core with routing capabilities.

## Context
- Location: `packages/routing/`
- Namespace: `Marko\Routing`
- Depends on `marko/core` for DI container and module system
- Follow the same package structure as marko/core

## Requirements (Test Descriptions)
- [ ] `it has a valid composer.json with correct package name marko/routing`
- [ ] `it has PSR-4 autoloading configured for Marko\Routing namespace`
- [ ] `it requires marko/core as a dependency`
- [ ] `it requires PHP 8.5 or higher`
- [ ] `it has src directory for source code`
- [ ] `it has tests directory for tests`
- [ ] `it has pestphp/pest as dev dependency`

## Acceptance Criteria
- All requirements have passing tests
- composer.json validates with `composer validate`
- Directory structure matches core package pattern

## Files to Create
```
packages/routing/
  composer.json
  src/
    .gitkeep
  tests/
    .gitkeep
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
