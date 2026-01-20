# Task 004: Rector Configuration

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the Rector configuration for automated code modernization. Rector automatically upgrades code to use modern PHP features like constructor promotion and modern string functions.

## Context
- Location: `/rector.php` (project root)
- Target PHP version: 8.5
- Directories to process: `packages/`, `demo/app/`, `demo/modules/`

## Requirements (Test Descriptions)
- [ ] `it has valid rector.php configuration`
- [ ] `it converts constructor property assignments to constructor promotion`
- [ ] `it modernizes strpos checks to str_contains`
- [ ] `it modernizes substr checks to str_starts_with`
- [ ] `it modernizes substr checks to str_ends_with`
- [ ] `it processes packages directory`
- [ ] `it processes demo/app directory`
- [ ] `it processes demo/modules directory`
- [ ] `rector runs without configuration errors`

## Acceptance Criteria
- All requirements have passing tests
- rector validates the configuration
- Code modernization rules work correctly

## Files to Create
```
rector.php
```

## PHP Configuration
```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\Rector\FuncCall\StrContainsRector;
use Rector\Php80\Rector\FuncCall\StrEndsWithRector;
use Rector\Php80\Rector\FuncCall\StrStartsWithRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/packages',
        __DIR__ . '/demo/app',
        __DIR__ . '/demo/modules',
    ])
    ->withRules([
        ClassPropertyAssignToConstructorPromotionRector::class,
        StrStartsWithRector::class,
        StrEndsWithRector::class,
        StrContainsRector::class,
    ]);
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
