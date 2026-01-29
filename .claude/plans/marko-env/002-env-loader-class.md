# Task 002: Create EnvLoader Class

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the EnvLoader class that parses `.env` files and populates both `$_ENV` and `putenv()` with the values. The loader should handle comments, empty lines, quoted values, and whitespace gracefully.

## Context
- Related files: `packages/env/src/EnvLoader.php` (new file)
- Patterns to follow: Simple, focused class. No variable interpolation or complex features.
- The loader silently returns if no `.env` file exists (apps work without it)

## Requirements (Test Descriptions)
- [ ] `it loads environment variables from .env file`
- [ ] `it populates $_ENV superglobal with values`
- [ ] `it sets values via putenv() for getenv() access`
- [ ] `it skips comment lines starting with #`
- [ ] `it skips empty lines`
- [ ] `it skips lines without equals sign`
- [ ] `it trims whitespace from variable names`
- [ ] `it trims whitespace from values`
- [ ] `it removes double quotes from quoted values`
- [ ] `it removes single quotes from quoted values`
- [ ] `it handles values containing equals signs`
- [ ] `it silently returns when .env file does not exist`
- [ ] `it does not overwrite existing environment variables`
- [ ] `it handles inline comments after values`

## Acceptance Criteria
- All requirements have passing tests
- Class is in Marko\Env namespace
- Uses strict types
- Code follows project standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
