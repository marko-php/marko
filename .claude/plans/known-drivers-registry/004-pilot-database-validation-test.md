# Task 004: Pilot — add validation test in marko/database

**Status**: pending
**Depends on**: 001, 002, 003
**Retry count**: 0

## Description
Add a validation test in `marko/database` that uses the `KnownDriversValidator` helper (from task 001) to mechanically enforce sync between `database/known-drivers.php` and (when present) skeleton's `suggest` block. This is the canonical pattern that tasks 007-023 will replicate for every other interface package.

## Context
- New file: `packages/database/tests/KnownDriversValidationTest.php`
- Reference: the `KnownDriversValidator` API created in task 001
- The test must skip gracefully when skeleton's composer.json isn't on disk OR doesn't have a `suggest` key yet
- The test must fail loudly when:
  - `known-drivers.php` is missing
  - Skeleton IS on disk with a `suggest` key but is missing a known driver entry or has a mismatched description

**Test file shape:**
```php
<?php

declare(strict_types=1);

use Marko\Testing\KnownDrivers\KnownDriversValidator;

$knownDriversPath = __DIR__ . '/../known-drivers.php';
$skeletonComposerPath = __DIR__ . '/../../skeleton/composer.json';

test('skeleton suggest block contains all database drivers', function () use ($knownDriversPath, $skeletonComposerPath) {
    KnownDriversValidator::assertSkeletonSuggestContainsAll($knownDriversPath, $skeletonComposerPath);
});

test('every database driver follows marko slash prefix pattern', function () use ($knownDriversPath) {
    KnownDriversValidator::assertDocsUrlsResolveToValidPattern($knownDriversPath);
});
```

## Requirements (Test Descriptions)
- [ ] `skeleton suggest block contains all database drivers`
- [ ] `every database driver follows marko slash prefix pattern`

## Acceptance Criteria
- Test file exists at `packages/database/tests/KnownDriversValidationTest.php`
- Test passes in the monorepo (where skeleton is present after task 024 runs; skip behavior kicks in before)
- Test would also pass in a standalone `marko/database` install (where skeleton may not be present) — verify by mentally walking through the skip logic
- **`marko/testing` added to `packages/database/composer.json` `require-dev`** (verified at plan creation: NOT currently a dev dependency). Use `"marko/testing": "self.version"` to match monorepo conventions.
- Code follows code standards
