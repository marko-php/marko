# Task 003: Create Package Structure and module.php

**Status**: pending
**Depends on**: 001, 002
**Retry count**: 0

## Description
Create the complete package structure for `marko/env` including composer.json with proper autoloading (files autoload for functions.php), module.php with boot callback and sequence hints to load before marko/config.

## Context
- Related files:
  - `packages/env/composer.json` (new file)
  - `packages/env/module.php` (new file)
- Patterns to follow: Existing packages like marko/errors-simple for structure
- Critical: functions.php must be in Composer's `files` autoload so env() is available early
- Critical: module.php must have `'before' => ['marko/config']` in sequence

## Requirements (Test Descriptions)
- [ ] `it has valid composer.json with correct name marko/env`
- [ ] `it has composer.json with files autoload for functions.php`
- [ ] `it has composer.json with psr-4 autoload for src/`
- [ ] `it requires marko/core as dependency`
- [ ] `it has extra.marko.module set to true`
- [ ] `it has module.php with boot callback`
- [ ] `it has module.php with sequence before marko/config`
- [ ] `it boot callback loads .env from project base path`
- [ ] `it boot callback uses ProjectPaths from container`

## Acceptance Criteria
- All requirements have passing tests
- Package can be discovered by Marko module system
- env() function available after Composer autoload
- .env file loaded before config files

## Implementation Notes
(Left blank - filled in by programmer during implementation)
