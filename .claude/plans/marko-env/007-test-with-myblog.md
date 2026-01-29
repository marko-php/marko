# Task 007: Test with myblog Application

**Status**: pending
**Depends on**: 003, 005, 006
**Retry count**: 0

## Description
Integration test the marko/env package with the myblog application. Create .env file with APP_ENV=development, update config/database.php to use env() calls, and verify with curl that the error handler shows detailed errors. Do NOT commit changes to myblog.

## Context
- Related files:
  - `~/Sites/myblog/.env` (create for testing, don't commit)
  - `~/Sites/myblog/config/database.php` (update for testing)
  - `~/Sites/myblog/composer.json` (add marko/env dependency)
- myblog runs at http://localhost:9000
- Currently shows "An error occurred" (production mode)
- After changes, should show detailed error with context and suggestion

## Requirements (Test Descriptions)
- [ ] `it adds marko/env to myblog composer.json`
- [ ] `it runs composer update in myblog to install marko/env`
- [ ] `it creates .env file with APP_ENV=development`
- [ ] `it updates config/database.php to use env() for credentials`
- [ ] `it curl request to /blog returns 500 status code`
- [ ] `it curl response shows ConnectionException class name`
- [ ] `it curl response shows context about database connection`
- [ ] `it curl response shows suggestion to check MySQL`
- [ ] `it works without .env file using config defaults`

## Acceptance Criteria
- myblog displays detailed error page in development mode
- myblog displays simple error page when APP_ENV not set or set to production
- Config file uses env() with sensible defaults
- All changes are temporary (not committed)

## Implementation Notes
(Left blank - filled in by programmer during implementation)

## Cleanup
After testing, revert myblog changes:
- Remove .env file
- Restore original config/database.php
- Remove marko/env from composer.json
- Run composer update
