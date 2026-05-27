# Task 013: Roll out known-drivers pattern — marko/media

**Status**: pending
**Depends on**: 001, 005
**Retry count**: 0

## Description
Apply the pilot pattern to `marko/media`. Two drivers: `media-gd`, `media-imagick`. Both bind `ImageProcessorInterface` and are mutually exclusive.

## Context
- Interface: `Marko\Media\Contracts\ImageProcessorInterface`
- Drivers: `marko/media-gd`, `marko/media-imagick`
- Recommended-first ordering: `media-gd` (ext-gd ships with most PHP builds; imagick requires the ImageMagick library installed separately)
- Confirmed in audit: both bind `ImageProcessorInterface` via module.php

**Description text for known-drivers.php:**
- `marko/media-gd` → `'GD image processor (recommended — ships with most PHP installations)'`
- `marko/media-imagick` → `'ImageMagick image processor (higher fidelity; requires ext-imagick and ImageMagick library installed)'`

## Sub-steps
1. Create `packages/media/known-drivers.php`
2. Refactor `packages/media/src/Exceptions/NoDriverException.php`. Update existing `packages/media/tests/Exceptions/NoDriverExceptionTest.php` to match the new output format.
3. Add mutual `conflict` blocks to both driver composer.json files
4. Add `packages/media/tests/KnownDriversValidationTest.php`
5. Verify `marko/testing` is in `packages/media/composer.json` `require-dev`; add it if missing

## Requirements (Test Descriptions)
- [ ] `it ships a known-drivers.php file listing both media drivers`
- [ ] `it lists marko/media-gd first as the recommended driver`
- [ ] `media NoDriverException reads from known-drivers.php and includes docs URLs`
- [ ] `each media driver declares conflict with the sibling driver`
- [ ] `validation test confirms conflict blocks match known-drivers list`

## Acceptance Criteria
- `packages/media/known-drivers.php` exists
- `NoDriverException` refactored
- Both driver composer.json files have correctly-populated `conflict` blocks
- Validation test passes
- Existing media tests still pass
- Code follows code standards
