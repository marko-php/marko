# Task 001: Add known-engines.php to marko/view

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create `packages/view/known-engines.php` — the source of truth listing every core view engine the framework promises to maintain template parity for. Read by `CrossEngineTemplateParityTest` (task 010) to enforce that every template provider package has siblings for every registered engine.

## Context
- New file: `packages/view/known-engines.php`
- New test file: `packages/view/tests/KnownEnginesTest.php`
- Format: keyed by short name; value contains extension and driver package
- This is distinct from `known-drivers.php` (PR #91 — lists installable driver packages). `known-engines.php` is the parity-test registry of core engines whose templates must be kept in sync across UI modules.

**File contents:**
```php
<?php

declare(strict_types=1);

return [
    'twig' => [
        'extension' => '.twig',
        'driver' => 'marko/view-twig',
    ],
    'latte' => [
        'extension' => '.latte',
        'driver' => 'marko/view-latte',
    ],
];
```

Ordering: Twig first (broader ecosystem familiarity — matches the established Marko default).

## Requirements (Test Descriptions)
- [ ] `it ships a known-engines.php file in marko/view`
- [ ] `it registers twig with extension .twig and driver marko/view-twig`
- [ ] `it registers latte with extension .latte and driver marko/view-latte`
- [ ] `it lists twig first as the recommended engine`
- [ ] `it returns an array keyed by short engine name with extension and driver fields`
- [ ] `it uses declare strict_types`

## Acceptance Criteria
- `packages/view/known-engines.php` exists with the specified contents
- File returns an array; every entry has `extension` and `driver` keys
- Test file verifies all requirements
- Code follows code standards (strict_types, no magic methods)
