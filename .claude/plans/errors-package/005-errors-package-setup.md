# Task 005: errors Package Setup

**Status**: completed
**Depends on**: 001, 002, 003, 004
**Retry count**: 0

## Description
Set up the `marko/errors` package with proper composer.json, module.php, and directory structure. This package contains only interfaces and value objects - no implementations.

## Context
- Related files: `packages/errors/composer.json`, `packages/errors/module.php`
- Patterns to follow: Existing packages (core, routing, cli)
- This is an interface-only package

## Requirements (Test Descriptions)
- [ ] `it has valid composer.json with name marko/errors`
- [ ] `it requires php 8.5 or higher`
- [ ] `it requires marko/core for MarkoException`
- [ ] `it has PSR-4 autoloading for Marko\\Errors namespace`
- [ ] `it has module.php that is enabled by default`
- [ ] `it exports ErrorHandlerInterface`
- [ ] `it exports ErrorReporterInterface`
- [ ] `it exports ErrorReport`
- [ ] `it exports Severity`

## Acceptance Criteria
- All requirements have passing tests
- Package can be required independently
- Autoloading works correctly
- Code follows project standards
