# Task 001: Create composer.json

**Status**: completed
**Depends on**: -
**Retry count**: 0

## Description
Create composer.json for marko/framework metapackage with all core dependencies.

## Context
- Metapackage type (no code, just dependencies)
- Bundles core packages for typical web applications
- Includes suggest section for optional packages

## Requirements (Test Descriptions)
- [x] `it has valid composer.json with correct name`
- [x] `it is type metapackage`
- [x] `it requires PHP 8.5`
- [x] `it requires core packages`
- [x] `it suggests optional packages`

## Implementation Notes
- Created packages/framework/composer.json as a metapackage
- Required core packages: marko/core, marko/routing, marko/cli, marko/errors, marko/errors-simple, marko/config, marko/hashing, marko/validation
- Suggested optional packages: marko/database, marko/database-mysql, marko/database-pgsql, marko/cache, marko/cache-file, marko/session, marko/session-file, marko/auth, marko/log, marko/log-file, marko/filesystem, marko/filesystem-local, marko/errors-advanced
- Added test namespace Marko\Framework\Tests\ to root composer.json autoload-dev
- Tests located in packages/framework/tests/PackageTest.php
