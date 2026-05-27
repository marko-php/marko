# Task 002: Pilot — create database known-drivers.php

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create the first `known-drivers.php` file for the pilot package (`marko/database`). This proves the file format and establishes the canonical shape every subsequent interface will mirror.

## Context
- New file: `packages/database/known-drivers.php`
- New test file: `packages/database/tests/KnownDriversTest.php` (verifies file structure)
- pgsql listed first (recommended default for new projects)
- Add-ons (`marko/database-readwrite`) are NOT listed here — they belong in skeleton suggest only

File contents:
```php
<?php

declare(strict_types=1);

return [
    'marko/database-pgsql' => 'PostgreSQL driver (recommended for new projects — strong JSON, FTS, pgvector support)',
    'marko/database-mysql' => 'MySQL/MariaDB driver',
];
```

**Description-string contract:** these exact description strings (including the em-dash `—` character, NOT a hyphen) are the canonical strings. Task 025 must write them verbatim into skeleton's `suggest` block; any divergence (typo, ASCII hyphen vs em-dash) will fail the `assertSkeletonSuggestContainsAll` test. The `_plan.md` Architecture Notes section and task 025 already reference these exact strings — DO NOT alter them when implementing.

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file in the database package`
- [ ] `it lists marko/database-pgsql as the first entry (recommended default)`
- [ ] `it lists marko/database-mysql as the second entry`
- [ ] `it does not list marko/database-readwrite (add-on, not a driver)`
- [ ] `it returns a flat package-to-description associative array`
- [ ] `it uses declare strict_types`

## Acceptance Criteria
- `packages/database/known-drivers.php` exists with the specified contents
- File returns an array (no nested keys, no objects)
- pgsql is the first entry
- `packages/database/tests/KnownDriversTest.php` verifies all requirements (note: this is distinct from the larger `KnownDriversValidationTest.php` created in task 005 — this test verifies file shape; that test verifies cross-package sync)
- Code follows code standards
