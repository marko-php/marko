# Task 006: Create dev-server package scaffold

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `marko/dev-server` package directory structure with `composer.json`, `module.php`, and register it in the root `composer.json` autoload maps. Also create the `DevServerException` class for package-specific errors.

## Context
- Related files: Root `composer.json` (autoload and autoload-dev entries), any existing package `composer.json` for reference (e.g., `packages/queue/composer.json`)
- Namespace: `Marko\DevServer`
- Package name: `marko/dev-server`
- Directory: `packages/dev-server/`
- Requires: `marko/core` (for Command attribute, CommandInterface, Input, Output)
- The package should also require `marko/config` for ConfigRepositoryInterface
- Follow existing package patterns for composer.json structure (no hardcoded version)

## Requirements (Test Descriptions)
- [ ] `it has valid composer.json with correct name and dependencies`
- [ ] `it has PSR-4 autoloading configured`
- [ ] `it has module.php with marko module marker`
- [ ] `it has DevServerException extending MarkoException`
- [ ] `it has config/dev.php with default values`

## Acceptance Criteria
- All requirements have passing tests
- Package structure matches conventions of existing packages
- Root composer.json updated with autoload entries
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
