---
title: marko/media-imagick
description: ImageMagick image processing for marko/media — resize, crop, and convert images with superior quality, AVIF support, and ICC color profile handling.
---

ImageMagick image processing for [`marko/media`](/docs/packages/media/) --- resize, crop, and convert images with superior quality, AVIF support, and ICC color profile handling. Provides an `ImageProcessorInterface` implementation backed by the Imagick PHP extension, delivering higher-quality resampling (Lanczos filter), broader format support including AVIF and WebP, and ICC color profile preservation --- advantages over the GD-based driver for production image pipelines. Requires `ext-imagick` installed separately via PECL.

## Installation

```bash
composer require marko/media-imagick
```

> **Requirement:** The Imagick PHP extension must be installed before use:
>
> ```bash
> pecl install imagick
> ```
>
> If the extension is absent, `ImagickImageProcessor` throws `ImagickProcessingException` on construction.

## Configuration

The package ships with a `config/media-imagick.php` file that controls which raster formats the processor accepts:

```php title="config/media-imagick.php"
return [
    'allowed_raster_formats' => ['JPEG', 'PNG', 'GIF', 'WEBP', 'AVIF'],
];
```

Every processing method (`resize`, `crop`, `convert`, `thumbnail`) checks the input image's format against this list before doing any work. Non-allowlisted formats and format mismatches throw `ImagickProcessingException`. Add formats to the list to permit additional input types supported by your ImageMagick build.

## Usage

### Resize an Image

```php
use Marko\Media\Contracts\ImageProcessorInterface;

class ThumbnailService
{
    public function __construct(
        private ImageProcessorInterface $imageProcessor,
    ) {}

    public function resize(string $imagePath): string
    {
        return $this->imageProcessor->resize(
            imagePath: $imagePath,
            width: 800,
            height: 600,
            maintainAspect: true,
        );
    }
}
```

Set `maintainAspect: false` to force exact dimensions without preserving the aspect ratio.

### Crop an Image

```php
use Marko\Media\Contracts\ImageProcessorInterface;

$outputPath = $this->imageProcessor->crop(
    imagePath: '/path/to/image.jpg',
    x: 100,
    y: 50,
    width: 400,
    height: 300,
);
```

### Convert to AVIF

AVIF is the key differentiator over the GD driver --- it produces smaller files with better quality than WebP or JPEG, and is fully supported by Imagick:

```php
use Marko\Media\Contracts\ImageProcessorInterface;

$outputPath = $this->imageProcessor->convert(
    imagePath: '/path/to/image.jpg',
    format: 'avif',
);
```

### Convert to WebP

```php
use Marko\Media\Contracts\ImageProcessorInterface;

$outputPath = $this->imageProcessor->convert(
    imagePath: '/path/to/image.png',
    format: 'webp',
);
```

### Generate a Thumbnail

Produces a square-bounded thumbnail fitting within `maxDimension` on its longest side:

```php
use Marko\Media\Contracts\ImageProcessorInterface;

$outputPath = $this->imageProcessor->thumbnail(
    imagePath: '/path/to/image.jpg',
    maxDimension: 150,
);
```

### Type-Hinting the Interface

Depend on the interface from [`marko/media`](/docs/packages/media/), not the concrete class:

```php
use Marko\Media\Contracts\ImageProcessorInterface;

public function __construct(
    private ImageProcessorInterface $imageProcessor,
) {}
```

## Supported Formats

| Format | Notes |
|--------|-------|
| JPEG   | Full read/write support |
| PNG    | Full read/write support |
| WebP   | Full read/write support |
| GIF    | Full read/write support |
| AVIF   | Full read/write support (key advantage over GD) |
| TIFF   | Full read/write support |
| BMP    | Full read/write support |
| HEIC   | Read/write (requires libheif) |

Additional formats depend on the libraries linked against your ImageMagick build. Run `convert -list format` on the command line to see your full list.

## Advantages Over marko/media-gd

| Feature | marko/media-imagick | marko/media-gd |
|---------|---------------------|----------------|
| Resize quality | Lanczos filter | Bicubic |
| AVIF support | Yes | No |
| ICC color profiles | Preserved | Dropped |
| Format support | 100+ formats | JPEG, PNG, GIF, WebP |
| Memory usage | Moderate | Lower |

Choose `marko/media-gd` when `ext-gd` is sufficient and memory is constrained. Choose `marko/media-imagick` when quality, AVIF, or broad format support matters.

## API Reference

```php
use Marko\MediaImagick\Driver\ImagickImageProcessor;

public function resize(string $imagePath, int $width, int $height, bool $maintainAspect = true): string;
public function crop(string $imagePath, int $x, int $y, int $width, int $height): string;
public function convert(string $imagePath, string $format): string;
public function thumbnail(string $imagePath, int $maxDimension): string;
```

All methods return the absolute path to the processed output file in the system temp directory. All methods check the input format against `allowed_raster_formats` before processing and throw `ImagickProcessingException` on failure or if the format is not allowed.
