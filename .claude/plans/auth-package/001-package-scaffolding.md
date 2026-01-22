# Task 001: Package Scaffolding

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the basic package structure for marko/auth with composer.json, directory structure, and PSR-4 autoloading.

## Context
- Related files: packages/hashing/composer.json, packages/session/composer.json (patterns to follow)
- Patterns to follow: Existing package structure in other marko packages
- Package needs PSR-4 autoloading configured

## Requirements (Test Descriptions)
- [ ] `it creates marko/auth package with valid composer.json`
- [ ] `it has correct package name marko/auth`
- [ ] `it has type marko-module in composer.json`
- [ ] `it has MIT license in composer.json`
- [ ] `it requires PHP 8.5 or higher`
- [ ] `it has PSR-4 autoloading configured for Marko\Auth namespace`
- [ ] `it has src directory for source code`
- [ ] `it has tests directory for tests`
- [ ] `it has config directory for default configuration`

## Acceptance Criteria
- All requirements have passing tests
- Composer validates the package json file
- Directory structure matches architecture document
- PSR-4 namespace: Marko\Auth

## Implementation Notes
(Left blank - filled in by programmer during implementation)
