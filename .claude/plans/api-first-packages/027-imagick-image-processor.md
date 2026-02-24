# Task 027: ImageMagick Image Processor

**Status**: pending
**Depends on**: 021
**Retry count**: 0

## Description
Create the marko/media-imagick package that implements ImageProcessorInterface using PHP's Imagick extension for image processing with advanced features.

## Context
- New package at `packages/media-imagick/`
- Namespace: `Marko\MediaImagick`
- Depends on: marko/core, marko/media
- Requires: ext-imagick
- Study `packages/media-gd/` from task 025 for parallel driver structure
- Study `packages/cache-redis/` for driver package with external extension dependency
- Imagick advantages over GD: AVIF support, better quality, ICC profiles, more format options
- Same interface as GD driver — applications swap by changing the binding
- Use Imagick class: readImageBlob, resizeImage, cropImage, setImageFormat, writeImage

## Requirements (Test Descriptions)
- [ ] `it resizes an image to specified width and height`
- [ ] `it crops an image to specified region coordinates`
- [ ] `it converts image format between JPEG, PNG, WebP, GIF, and AVIF`
- [ ] `it generates thumbnail at specified maximum dimension`
- [ ] `it preserves aspect ratio during resize when requested`
- [ ] `it throws ImagickProcessingException when Imagick extension is unavailable`

## Acceptance Criteria
- All requirements have passing tests
- ImagickImageProcessor in `src/Driver/ImagickImageProcessor.php`
- ImagickProcessingException in `src/Exceptions/ImagickProcessingException.php`
- Bound in module.php as ImageProcessorInterface implementation
- composer.json requires ext-imagick
- Tests can be skipped when ext-imagick is not available (use appropriate Pest skip)
- Code follows code standards

## Implementation Notes
