# Task 012: Add NoDriverException to Media Package

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create a `NoDriverException` in the media package following the standard pattern.

## Context
- Related files:
  - `packages/media/src/Exceptions/MediaException.php` (extends `MarkoException`)
- Base exception: `MediaException` extends `MarkoException` — use `MediaException`
- Driver packages: `marko/media-gd`, `marko/media-imagick`

## Requirements (Test Descriptions)
- [ ] `it has DRIVER_PACKAGES constant listing marko/media-gd and marko/media-imagick`
- [ ] `it provides suggestion with composer require commands for all driver packages`
- [ ] `it includes context about resolving media/image processing interfaces`
- [ ] `it extends MediaException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern

## Implementation Notes
Create new file at `packages/media/src/Exceptions/NoDriverException.php`.
