# marko/media-imagick

ImageMagick image processing for marko/media — resize, crop, and convert images with superior quality, AVIF support, and ICC color profile handling.

## Installation

```bash
composer require marko/media-imagick
```

> **Requirement:** The Imagick PHP extension must be installed (`pecl install imagick`).

## Quick Example

```php
use Marko\MediaImagick\Driver\ImagickImageProcessor;

$processor = new ImagickImageProcessor();

$outputPath = $processor->resize(
    imagePath: '/path/to/image.jpg',
    width: 800,
    height: 600,
);
```

## Documentation

Full usage, API reference, and examples: [marko/media-imagick](https://marko.build/docs/packages/media-imagick/)
