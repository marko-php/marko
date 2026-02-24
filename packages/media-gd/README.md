# Marko Media GD

GD image processing for marko/media--resize, crop, and convert images using PHP's built-in GD extension with no additional system dependencies.

## Overview

This package implements `ImageProcessorInterface` using PHP's bundled GD extension. It covers the common image operations--resize with aspect ratio preservation, crop by coordinates, format conversion, and thumbnail generation--without requiring any system-level image library. For advanced needs like AVIF encoding or higher-quality resampling, see `marko/media-imagick`.

Requires `ext-gd`, which ships with most PHP installations. Throws `GdProcessingException` if the extension is unavailable.

**Supported formats:** JPEG, PNG, WebP, GIF

## Installation

```bash
composer require marko/media-gd
```

## Usage

### Binding the Processor

Register `GdImageProcessor` as the `ImageProcessorInterface` implementation in your `module.php`:

```php
use Marko\Media\Contracts\ImageProcessorInterface;
use Marko\MediaGd\Driver\GdImageProcessor;

return [
    'bindings' => [
        ImageProcessorInterface::class => GdImageProcessor::class,
    ],
];
```

### Resize with Aspect Ratio

Resize an image to fit within 800x600, preserving the original proportions:

```php
use Marko\Media\Contracts\ImageProcessorInterface;

class ImageService
{
    public function __construct(
        private ImageProcessorInterface $processor,
    ) {}

    public function resizeForWeb(
        string $imagePath,
    ): string {
        return $this->processor->resize(
            imagePath: $imagePath,
            width: 800,
            height: 600,
        );
    }
}
```

Pass `maintainAspect: false` to force exact dimensions:

```php
$outputPath = $this->processor->resize(
    imagePath: $imagePath,
    width: 800,
    height: 600,
    maintainAspect: false,
);
```

### Crop

Extract a region starting at pixel coordinates (50, 100), 400px wide and 300px tall:

```php
$outputPath = $this->processor->crop(
    imagePath: $imagePath,
    x: 50,
    y: 100,
    width: 400,
    height: 300,
);
```

### Format Conversion

Convert an image to WebP:

```php
$outputPath = $this->processor->convert(
    imagePath: $imagePath,
    format: 'webp',
);
```

Accepted format strings: `jpeg` (or `jpg`), `png`, `webp`, `gif`. Passing an unsupported format throws `GdProcessingException`.

### Thumbnail

Generate a thumbnail where the longest side is at most 150px:

```php
$outputPath = $this->processor->thumbnail(
    imagePath: $imagePath,
    maxDimension: 150,
);
```

## Requirements

- PHP 8.5+
- `ext-gd` (bundled with most PHP installations; enable via `php-gd` or `--with-gd`)

If GD is unavailable, instantiating `GdImageProcessor` throws `GdProcessingException` with a message explaining how to enable the extension.

## API Reference

### GdImageProcessor

Implements `ImageProcessorInterface`. See `marko/media` for the interface contract.

```php
public function resize(string $imagePath, int $width, int $height, bool $maintainAspect = true): string;
public function crop(string $imagePath, int $x, int $y, int $width, int $height): string;
public function convert(string $imagePath, string $format): string;
public function thumbnail(string $imagePath, int $maxDimension): string;
```

All methods return the file path to the processed image (written to the system temp directory).

### Exceptions

- `GdProcessingException` -- Thrown when the GD extension is unavailable, the source image cannot be loaded, or an unsupported format is requested
