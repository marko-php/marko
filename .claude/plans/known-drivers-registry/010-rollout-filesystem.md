# Task 010: Roll out known-drivers pattern — marko/filesystem

**Status**: pending
**Depends on**: 001, 005
**Retry count**: 0

## Description
Apply the pilot pattern to `marko/filesystem`. Two drivers: `filesystem-local`, `filesystem-s3`. Both bind `FilesystemInterface` and are mutually exclusive.

## Context
- Interface: `Marko\Filesystem\FilesystemInterface`
- Drivers: `marko/filesystem-local`, `marko/filesystem-s3`
- Recommended-first ordering: `filesystem-local` (zero-infrastructure default; s3 for cloud/distributed)
- Confirm both have `module.php` binding `FilesystemInterface`

**Description text for known-drivers.php:**
- `marko/filesystem-local` → `'Local disk filesystem driver (recommended default; zero infrastructure required)'`
- `marko/filesystem-s3` → `'Amazon S3 filesystem driver (for cloud and distributed deployments)'`

## Sub-steps
1. Create `packages/filesystem/known-drivers.php`
2. Refactor `packages/filesystem/src/Exceptions/NoDriverException.php`. Update existing `packages/filesystem/tests/Unit/Exceptions/NoDriverExceptionTest.php` to match the new output format.
3. Add mutual `conflict` blocks to both driver composer.json files
4. Add `packages/filesystem/tests/KnownDriversValidationTest.php`
5. Verify `marko/testing` is in `packages/filesystem/composer.json` `require-dev`; add it if missing

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing both filesystem drivers`
- [ ] `it lists marko/filesystem-local first as the recommended driver`
- [ ] `filesystem NoDriverException reads from known-drivers.php and includes docs URLs`
- [ ] `each filesystem driver declares conflict with the sibling driver`
- [ ] `validation test confirms conflict blocks match known-drivers list`

## Acceptance Criteria
- `packages/filesystem/known-drivers.php` exists
- `NoDriverException` refactored
- Both driver composer.json files have correctly-populated `conflict` blocks
- Validation test passes
- Existing filesystem tests still pass
- Code follows code standards
