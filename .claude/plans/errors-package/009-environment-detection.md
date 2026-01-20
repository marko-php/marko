# Task 009: Environment Detection

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the Environment class that detects the current runtime environment. This determines whether we're running in CLI or web context, and whether we're in development or production mode.

## Context
- Related files: `packages/errors-simple/src/Environment.php`
- Production should be the default for safety
- Environment variables: `MARKO_ENV` or `APP_ENV`

## Requirements (Test Descriptions)
- [ ] `it detects CLI context from PHP_SAPI`
- [ ] `it detects web context from PHP_SAPI`
- [ ] `it detects development mode from MARKO_ENV`
- [ ] `it detects development mode from APP_ENV as fallback`
- [ ] `it defaults to production when no environment variable set`
- [ ] `it recognizes dev as development`
- [ ] `it recognizes development as development`
- [ ] `it recognizes local as development`
- [ ] `it recognizes production as production`
- [ ] `it recognizes prod as production`
- [ ] `it is case insensitive for environment values`
- [ ] `it provides isCli method`
- [ ] `it provides isWeb method`
- [ ] `it provides isDevelopment method`
- [ ] `it provides isProduction method`
- [ ] `it can be overridden for testing`

## Acceptance Criteria
- All requirements have passing tests
- Safe defaults (production mode when unknown)
- Code follows project standards
