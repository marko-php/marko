# Task 003: Rename dev-server → devserver

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Rename the `marko/dev-server` package to `marko/devserver` to conform to the hyphen-only-for-siblings naming convention.

## Context
- Directory: `packages/dev-server/` → `packages/devserver/`
- Composer name: `marko/dev-server` → `marko/devserver`
- Namespace: `Marko\DevServer\*` → `Marko\DevServer\*` (already single-token in namespace — verify)
- If the PHP namespace contains no hyphen, only the package/dir name changes

## Requirements (Test Descriptions)
- [x] `it has package at packages/devserver/ with correct directory structure`
- [x] `it declares composer.json name as marko/devserver`
- [x] `it uses PSR-4 namespace Marko\\DevServer\\ pointing to src/`
- [x] `it has no remaining references to marko/dev-server in its own composer.json`
- [x] `it passes its existing Pest test suite after rename`

## Acceptance Criteria
- Directory moved, composer.json updated, namespace verified/updated
- Package's own tests pass
- CLI entrypoint (if any) still resolves

## Implementation Notes
- Copied `packages/dev-server/` to `packages/devserver/` (dev-server left in place per task instructions)
- Updated `packages/devserver/composer.json` name from `marko/dev-server` to `marko/devserver`
- Namespace `Marko\DevServer\` was already hyphen-free — no PHP file changes needed
- Updated `packages/devserver/README.md` and `tests/PackageStructureTest.php` to reference `marko/devserver`
- Added `packages/devserver` path repository and `marko/devserver` require entry to root `composer.json`
- Updated root `autoload-dev` to point `Marko\\DevServer\\Tests\\` to `packages/devserver/tests/`
- Added `<exclude>packages/dev-server/tests</exclude>` to `phpunit.xml` to prevent function redeclaration collision
- Also added `<exclude>packages/rate-limiting/tests</exclude>` to fix pre-existing collision from `ratelimiter` task
- RenameTest at `packages/devserver/tests/Unit/RenameTest.php`; all 134 devserver tests pass
