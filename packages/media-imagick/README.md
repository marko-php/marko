# marko/media-imagick

ImageMagick image processing for marko/media—resize, crop, and convert images with superior quality, AVIF support, and ICC color profile handling.

## Overview

`marko/media-imagick` provides an `ImageProcessorInterface` implementation backed by the Imagick PHP extension. It delivers higher-quality resampling (Lanczos filter), broader format support including AVIF and WebP, and ICC color profile preservation—advantages over the GD-based driver for production image pipelines. Requires `ext-imagick` installed separately via PECL.

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

## Usage

### Resize an Image

```php
$processor = new ImagickImageProcessor();

$outputPath = $processor->resize(
    imagePath: '/path/to/image.jpg',
    width: 800,
    height: 600,
    maintainAspect: true,
);
```

Set `maintainAspect: false` to force exact dimensions without preserving the aspect ratio.

### Crop an Image

```php
$outputPath = $processor->crop(
    imagePath: '/path/to/image.jpg',
    x: 100,
    y: 50,
    width: 400,
    height: 300,
);
```

### Convert to AVIF

AVIF is the key differentiator over the GD driver—it produces smaller files with better quality than WebP or JPEG, and is fully supported by Imagick:

```php
$outputPath = $processor->convert(
    imagePath: '/path/to/image.jpg',
    format: 'avif',
);
```

### Convert to WebP

```php
$outputPath = $processor->convert(
    imagePath: '/path/to/image.png',
    format: 'webp',
);
```

### Generate a Thumbnail

Produces a square-bounded thumbnail fitting within `maxDimension` on its longest side:

```php
$outputPath = $processor->thumbnail(
    imagePath: '/path/to/image.jpg',
    maxDimension: 150,
);
```

### Type-Hinting the Interface

Depend on the interface from `marko/media`, not the concrete class:

```php
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
public function resize(string $imagePath, int $width, int $height, bool $maintainAspect = true): string;
public function crop(string $imagePath, int $x, int $y, int $width, int $height): string;
public function convert(string $imagePath, string $format): string;
public function thumbnail(string $imagePath, int $maxDimension): string;
```

All methods return the absolute path to the processed output file in the system temp directory. All methods throw `ImagickProcessingException` on failure.
