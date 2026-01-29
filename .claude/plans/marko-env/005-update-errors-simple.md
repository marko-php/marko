# Task 005: Update errors-simple Environment Class

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Update the `marko/errors-simple` Environment class to use the new `env()` helper function for detecting development vs production environment, replacing the direct `getenv()` calls.

## Context
- Related files: `packages/errors-simple/src/Environment.php`
- The Environment class currently uses `getenv()` directly
- After this change, it will use `env('APP_ENV', 'production')`
- Default to 'production' for security (explicit opt-in to development mode)

## Requirements (Test Descriptions)
- [ ] `it uses env() helper instead of direct getenv()`
- [ ] `it defaults APP_ENV to production when not set`
- [ ] `it detects development mode when APP_ENV is dev`
- [ ] `it detects development mode when APP_ENV is development`
- [ ] `it detects development mode when APP_ENV is local`
- [ ] `it is case insensitive for APP_ENV values`
- [ ] `it returns production mode for any other APP_ENV value`
- [ ] `it adds marko/env as a dependency in composer.json`

## Acceptance Criteria
- All requirements have passing tests
- Existing tests still pass
- errors-simple depends on marko/env
- Default behavior is secure (production mode)

## Implementation Notes
(Left blank - filled in by programmer during implementation)
