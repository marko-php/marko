# Task 001: Make DatabaseConfig Injectable

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Update DatabaseConfig to use `getcwd()` as the default base path, making it injectable by the DI container without requiring manual configuration. Also add a binding in marko/database's module.php.

## Context
- Related files:
  - `packages/database/src/Config/DatabaseConfig.php` (modify)
  - `packages/database/module.php` (create if not exists, or modify)
  - `packages/database/tests/DatabaseConfigTest.php` (update tests)
- Patterns to follow: Other config classes in the framework

## Requirements (Test Descriptions)
- [x] `it uses getcwd as default base path when no path provided`
- [x] `it accepts explicit base path to override default`
- [x] `it loads config from basePath/config/database.php`
- [x] `it throws ConfigurationException when config file not found`
- [x] `it throws ConfigurationException when required keys missing`

## Acceptance Criteria
- All requirements have passing tests
- DatabaseConfig can be instantiated with no constructor arguments
- Existing functionality preserved when explicit path provided
- Code follows project standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
