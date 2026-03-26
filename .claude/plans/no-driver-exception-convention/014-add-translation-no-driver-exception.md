# Task 014: Add NoDriverException to Translation Package

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create a `NoDriverException` in the translation package following the standard pattern.

## Context
- Related files:
  - `packages/translation/src/Exceptions/TranslationException.php` (extends `MarkoException`)
- Base exception: `TranslationException` extends `MarkoException` — use `TranslationException`
- Driver packages: `marko/translation-file`

## Requirements (Test Descriptions)
- [ ] `it has DRIVER_PACKAGES constant listing marko/translation-file`
- [ ] `it provides suggestion with composer require command`
- [ ] `it includes context about resolving translation interfaces`
- [ ] `it extends TranslationException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern

## Implementation Notes
Create new file at `packages/translation/src/Exceptions/NoDriverException.php`.
