# Task 025: GD Image Processor

**Status**: pending
**Depends on**: 021
**Retry count**: 0

## Description
Create the marko/media-gd package that implements ImageProcessorInterface using PHP's GD extension for image resize, crop, convert, and thumbnail generation.

## Context
- New package at `packages/media-gd/`
- Namespace: `Marko\MediaGd`
- Depends on: marko/core, marko/media
- Requires: ext-gd
- Study `packages/cache-file/` for driver package scaffolding pattern (composer.json, module.php with binding)
- Study `packages/media/src/Contracts/ImageProcessorInterface.php` from task 021 for interface to implement
- GD supports: JPEG, PNG, WebP, GIF (no AVIF — that's Imagick's advantage)
- GD is bundled with most PHP installations — lightweight default choice
- Use imagecreatetruecolor, imagecopyresampled, imagewebp, imagejpeg, imagepng, etc.

## Requirements (Test Descriptions)
- [ ] `it resizes an image to specified width and height`
- [ ] `it crops an image to specified region coordinates`
- [ ] `it converts image format between JPEG, PNG, WebP, and GIF`
- [ ] `it generates thumbnail at specified maximum dimension`
- [ ] `it preserves aspect ratio during resize when requested`
- [ ] `it throws GdProcessingException when GD extension is unavailable`

## Acceptance Criteria
- All requirements have passing tests
- GdImageProcessor in `src/Driver/GdImageProcessor.php`
- GdProcessingException in `src/Exceptions/GdProcessingException.php`
- Bound in module.php as ImageProcessorInterface implementation
- composer.json requires ext-gd
- Tests use small fixture images or GD-created test images
- Code follows code standards

## Implementation Notes
