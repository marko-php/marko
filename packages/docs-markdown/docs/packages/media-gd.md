---
title: marko/media-gd
description: GD image processing for marko/media — resize, crop, and convert images using PHP's built-in GD extension.
---

GD image processing for [marko/media](/docs/packages/media/) --- resize, crop, and convert images using PHP's built-in GD extension with no additional system dependencies. Covers the common image operations --- resize with aspect ratio preservation, crop by coordinates, format conversion, and thumbnail generation --- without requiring any system-level image library. Resize and crop operations preserve the source format and handle alpha-channel transparency for PNG and WebP. Encode failures (e.g. disk write errors) throw `GdProcessingException` immediately rather than silently producing an empty file. For advanced needs like AVIF encoding or higher-quality resampling, see `marko/media-imagick`.

Implements `ImageProcessorInterface` from `marko/media`. Requires `ext-gd`, which ships with most PHP installations. Throws `GdProcessingException` if the extension is unavailable.

**Supported formats:** JPEG, PNG, WebP, GIF

## Installation

```bash
composer require marko/media-gd
```

This automatically installs [marko/media](/docs/packages/media/).

## Usage

### Binding the Processor

Installing the package binds `GdImageProcessor` as the `ImageProcessorInterface` implementation, built with the [dimension limits](#dimension-limits) from `media-gd` config. Inject `ImageProcessorInterface` wherever you need it.

### Resize with Aspect Ratio

Resize an image to fit within 800x600, preserving the original proportions:

```php
use Marko\Media\Contracts\ImageProcessorInterface;

class ImageService
{
    public function __construct(
        private ImageProcessorInterface $imageProcessor,
    ) {}

    public function resizeForWeb(
        string $imagePath,
    ): string {
        return $this->imageProcessor->resize(
            imagePath: $imagePath,
            width: 800,
            height: 600,
        );
    }
}
```

Pass `maintainAspect: false` to force exact dimensions:

```php
$outputPath = $this->imageProcessor->resize(
    imagePath: $imagePath,
    width: 800,
    height: 600,
    maintainAspect: false,
);
```

### Crop

Extract a region starting at pixel coordinates (50, 100), 400px wide and 300px tall:

```php
$outputPath = $this->imageProcessor->crop(
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
$outputPath = $this->imageProcessor->convert(
    imagePath: $imagePath,
    format: 'webp',
);
```

Accepted format strings: `jpeg` (or `jpg`), `png`, `webp`, `gif`. Passing an unsupported format throws `GdProcessingException`.

### Thumbnail

Generate a thumbnail where the longest side is at most 150px:

```php
$outputPath = $this->imageProcessor->thumbnail(
    imagePath: $imagePath,
    maxDimension: 150,
);
```

## Dimension Limits

GD decodes every image into an uncompressed bitmap of roughly 5 bytes per pixel, so a tiny file whose header declares a huge canvas (a "decompression bomb") can exhaust `memory_limit` and kill the worker with an uncatchable fatal error. To prevent that, `GdImageProcessor` reads the declared size with `getimagesize()` **before** decoding, and throws `GdProcessingException` if the image is wider or taller than `max_dimension`, or if width × height exceeds `max_pixels`.

The same limits apply to requested output sizes: `resize()`, `crop()`, and `thumbnail()` reject a target width or height below 1 or above the limits, and `crop()` rejects negative offsets --- all with `GdProcessingException`, never a raw `ValueError` or fatal error.

Defaults ship in the package's `config/media-gd.php`; override them in your app's `config/media-gd.php`:

```php title="config/media-gd.php"
return [
    'max_pixels' => 50_000_000, // 50 MP total (~250 MB decoded)
    'max_dimension' => 16384,   // max width or height in pixels
];
```

| Key | Default | Description |
|---|---|---|
| `max_pixels` | `50000000` | Largest width × height GD will decode or create |
| `max_dimension` | `16384` | Largest width or height, in pixels, GD will decode or create |

Raise `memory_limit` alongside these if you need to process larger images. Both values must be positive integers; anything else throws `GdProcessingException` when the processor is built.

## Requirements

- PHP 8.5+
- `ext-gd` (bundled with most PHP installations; enable via `php-gd` or `--with-gd`)

If GD is unavailable, instantiating `GdImageProcessor` throws `GdProcessingException` with a message explaining how to enable the extension.

## API Reference

### GdImageProcessor

Implements `ImageProcessorInterface`. See [marko/media](/docs/packages/media/) for the interface contract.

| Method | Description |
|---|---|
| `resize(string $imagePath, int $width, int $height, bool $maintainAspect = true): string` | Resize an image; preserves aspect ratio by default |
| `crop(string $imagePath, int $x, int $y, int $width, int $height): string` | Extract a region by pixel coordinates |
| `convert(string $imagePath, string $format): string` | Convert to another format (`jpeg`, `png`, `webp`, `gif`) |
| `thumbnail(string $imagePath, int $maxDimension): string` | Generate a thumbnail constrained to `maxDimension` on the longest side |

All methods return the file path to the processed image (written to the system temp directory).

### Exceptions

| Exception | Thrown When |
|---|---|
| `GdProcessingException` | The GD extension is unavailable, the source image cannot be loaded or exceeds the dimension limits, a target size or crop offset is invalid, an unsupported format is requested (an `UnsupportedGdFeatureException`, which also implements `UnsupportedFeatureExceptionInterface`), or the encode step fails (e.g. write error) |
