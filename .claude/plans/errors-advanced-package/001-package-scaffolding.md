# Task 001: Package Scaffolding

**Status**: completed
**Depends on**: -
**Retry count**: 0

## Description
Create the package scaffolding for marko/errors-advanced including composer.json, directory structure, and module.php.

## Context
- Follow existing package patterns (see packages/errors-simple/)
- Zero external dependencies beyond marko/errors and marko/core

## Requirements (Test Descriptions)
- [x] `it has valid composer.json with correct name`
- [x] `it has required PHP version 8.5`
- [x] `it depends on marko/errors`
- [x] `it has PSR-4 autoload configuration`
- [x] `it has src directory structure`
- [x] `it has tests directory`

## Acceptance Criteria
- All requirements have passing tests
- composer.json validates
- Directory structure matches architecture

## Implementation Notes
Created the errors-advanced package scaffolding:
- packages/errors-advanced/composer.json - Package definition with marko/errors dependency
- packages/errors-advanced/src/ - Source directory for future implementation
- packages/errors-advanced/tests/Unit/PackageSetupTest.php - Package setup tests
- Updated root composer.json with autoload namespaces for Marko\ErrorsAdvanced
