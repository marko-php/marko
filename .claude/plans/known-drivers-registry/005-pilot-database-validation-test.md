# Task 005: Pilot — add validation test in marko/database

**Status**: pending
**Depends on**: 001, 002, 003, 004
**Retry count**: 0

## Description
Add a validation test in `marko/database` that uses the `KnownDriversValidator` helper (from task 001) to mechanically enforce sync between `database/known-drivers.php`, each driver's composer.json `conflict` block, and (when present) skeleton's `suggest` block. This is the canonical pattern that tasks 008-024 will replicate for every other interface package.

## Context
- New file: `packages/database/tests/KnownDriversValidationTest.php`
- Reference: the `KnownDriversValidator` API created in task 001
- The test must skip gracefully when:
  - A driver package (`marko/database-mysql`, `marko/database-pgsql`) isn't on disk → that specific driver assertion is skipped
  - Skeleton's composer.json isn't on disk → the skeleton-parity assertion is skipped entirely
- The test must fail loudly when:
  - `known-drivers.php` is missing
  - A driver IS on disk but its conflict block is wrong (missing a sibling, listing a non-driver, etc.)
  - Skeleton IS on disk but its suggest block is missing a known driver entry or has a mismatched description

**Test file shape:**
```php
<?php

declare(strict_types=1);

use Marko\Testing\KnownDrivers\KnownDriversValidator;

$knownDriversPath = __DIR__ . '/../known-drivers.php';
$packagesDir = __DIR__ . '/../../';
$skeletonComposerPath = __DIR__ . '/../../skeleton/composer.json';

test('every database driver declares conflict with siblings', function () use ($knownDriversPath, $packagesDir) {
    KnownDriversValidator::assertConflictBlocksMatch($knownDriversPath, $packagesDir);
});

test('skeleton suggest block contains all database drivers', function () use ($knownDriversPath, $skeletonComposerPath) {
    KnownDriversValidator::assertSkeletonSuggestContainsAll($knownDriversPath, $skeletonComposerPath);
});

test('every database driver follows marko slash prefix pattern', function () use ($knownDriversPath) {
    KnownDriversValidator::assertDocsUrlsResolveToValidPattern($knownDriversPath);
});
```

## Requirements (Test Descriptions)
- [ ] `every database driver declares conflict with siblings`
- [ ] `skeleton suggest block contains all database drivers`
- [ ] `every database driver follows marko slash prefix pattern`

## Acceptance Criteria
- Test file exists at `packages/database/tests/KnownDriversValidationTest.php`
- Test passes in the monorepo (where all packages are present)
- Test would also pass in a standalone `marko/database` install (where skeleton and drivers may not be present) — verify by mentally walking through the skip logic, or actually running with packages temporarily removed
- **`marko/testing` added to `packages/database/composer.json` `require-dev`** (verified at plan creation: NOT currently a dev dependency). Use `"marko/testing": "self.version"` to match monorepo conventions.
- Code follows code standards
