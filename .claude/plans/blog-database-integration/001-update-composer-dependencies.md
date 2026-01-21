# Task 001: Update Composer Dependencies

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Add `marko/database` as a dependency to the blog package's composer.json. This enables the blog to use database interfaces without coupling to any specific driver.

## Context
- Related files: `packages/blog/composer.json`
- Patterns to follow: Other packages that depend on database (check existing composer.json files)
- The dependency should be on `marko/database` (interfaces), NOT on `marko/database-mysql` or `marko/database-pgsql`

## Requirements (Test Descriptions)
- [x] `it requires marko/database as a dependency`
- [x] `it adds path repository for database package in development`
- [x] `it does not depend on any specific database driver`

## Acceptance Criteria
- All requirements have passing tests
- composer.json is valid JSON
- Dependencies follow project patterns (@dev for local development)

## Implementation Notes
(Left blank - filled in by programmer during implementation)
