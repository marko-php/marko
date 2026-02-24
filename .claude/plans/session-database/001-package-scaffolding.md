# Task 001: Package Scaffolding and Module Tests

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the session-database package with composer.json, module.php, Pest.php, and module tests.

## Context
- Namespace: `Marko\Session\Database\`
- Package name: `marko/session-database`
- Dependencies: marko/core, marko/config, marko/session, marko/database
- Reference: packages/session-file/ (template for everything)

## Requirements (Test Descriptions)
- [ ] `it binds SessionHandlerInterface to DatabaseSessionHandler`
- [ ] `it returns valid module configuration array`
- [ ] `it has marko module flag in composer.json`
- [ ] `it has correct PSR-4 autoloading namespace`
