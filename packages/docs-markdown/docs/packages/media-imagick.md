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

The package ships with a `config/media-imagick.php` file that controls which raster formats the processor accepts and the ImageMagick resource limits applied to every read:

```php title="config/media-imagick.php"
return [
    'allowed_raster_formats' => ['JPEG', 'PNG', 'GIF', 'WEBP', 'AVIF'],

    'limits' => [
        'max_width' => 16384,          // pixels
        'max_height' => 16384,         // pixels
        'max_pixels' => 50_000_000,    // width x height (50 megapixels)
        'memory_bytes' => 268_435_456, // 256 MiB of pixel cache before spilling to disk
        'time_seconds' => 60,
    ],
];
```

`allowed_raster_formats` applies to both input images and `convert()` targets. Only formats that `ImageFormatSniffer` from [`marko/media`](/docs/packages/media/) recognises can be enabled: `JPEG`, `PNG`, `GIF`, `WEBP`, `AVIF`, `HEIC`, `TIFF` and `BMP`. `convert()` accepts `jpg` and `tif` as aliases for `JPEG` and `TIFF`.

The `limits` are applied with `Imagick::setResourceLimit()` before every read. ImageMagick resource limits are process-wide, so they also affect any other Imagick code running in the same PHP process.

## Security

ImageMagick ships coders for SVG, MVG, MSL, PostScript, PDF, `url:`, `text:` and more --- several of which can fetch URLs, read local files or invoke Ghostscript. `ImagickImageProcessor` never lets ImageMagick choose the coder for an input file. Before ImageMagick touches a file, every processing method:

1. Rejects paths that start with an ImageMagick coder prefix (`msl:`, `url:`, `PNG:`, ...) or a PHP stream wrapper (`phar://`, `http://`, `data:`), and paths that are not readable regular files.
2. Detects the format from the file's magic bytes with `ImageFormatSniffer`. SVG, MVG, MSL, PostScript, PDF and any other non-raster content throw `ImageFormatException`, whatever the file extension.
3. Checks the detected format against `allowed_raster_formats` (throws `ImagickProcessingException`).
4. Checks the header dimensions against `max_width`, `max_height` and `max_pixels` (throws `ImagickProcessingException`), so decompression bombs are refused before decoding.
5. Reads the file with an explicit coder prefix (for example `PNG:/real/path.png`), so ImageMagick decodes it with the detected raster coder only.

### Recommended policy.xml

Defence in depth: also restrict ImageMagick itself with a `policy.xml`, so that any other code on the server using ImageMagick is covered too. Find the active file with `convert -list policy` (or `magick -list policy`) --- usually `/etc/ImageMagick-7/policy.xml` or `/etc/ImageMagick-6/policy.xml`. This policy disables every coder except the raster formats Marko processes:

```xml title="policy.xml"
<policymap>
  <!-- Resource limits (independent of the per-request limits set by marko/media-imagick) -->
  <policy domain="resource" name="memory" value="256MiB"/>
  <policy domain="resource" name="map" value="512MiB"/>
  <policy domain="resource" name="width" value="16KP"/>
  <policy domain="resource" name="height" value="16KP"/>
  <policy domain="resource" name="area" value="50MP"/>
  <policy domain="resource" name="disk" value="1GiB"/>
  <policy domain="resource" name="time" value="60"/>

  <!-- Deny every coder, then allow only the raster formats you process -->
  <policy domain="delegate" rights="none" pattern="*"/>
  <policy domain="coder" rights="none" pattern="*"/>
  <policy domain="coder" rights="read|write" pattern="{JPEG,PNG,GIF,WEBP,AVIF,HEIC}"/>

  <!-- No indirect reads (@file) -->
  <policy domain="path" rights="none" pattern="@*"/>
</policymap>
```

Adjust the allowed coder list to match `allowed_raster_formats`. ImageMagick also publishes a hardened [`policy-secure.xml`](https://imagemagick.org/source/policy-secure.xml) you can start from.

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
| TIFF   | Read/write --- add `TIFF` to `allowed_raster_formats` to enable |
| BMP    | Read/write --- add `BMP` to `allowed_raster_formats` to enable |
| HEIC   | Read/write (requires libheif) --- add `HEIC` to `allowed_raster_formats` to enable |

Availability depends on the libraries linked against your ImageMagick build; run `convert -list format` to see what yours supports. Formats outside this table (SVG, PDF, PostScript, etc.) are never processed, even if your ImageMagick build can read them --- see [Security](#security).

## Advantages Over marko/media-gd

| Feature | marko/media-imagick | marko/media-gd |
|---------|---------------------|----------------|
| Resize quality | Lanczos filter | Bicubic |
| AVIF support | Yes | No |
| ICC color profiles | Preserved | Dropped |
| Format support | 8 raster formats | JPEG, PNG, GIF, WebP |
| Memory usage | Moderate | Lower |

Choose `marko/media-gd` when `ext-gd` is sufficient and memory is constrained. Choose `marko/media-imagick` when quality, AVIF, or broad format support matters.

## API Reference

```php
use Marko\MediaImagick\Driver\ImagickImageProcessor;

public function resize(
    string $imagePath,
    int $width,
    int $height,
    bool $maintainAspect = true,
): string;
public function crop(
    string $imagePath,
    int $x,
    int $y,
    int $width,
    int $height,
): string;
public function convert(
    string $imagePath,
    string $format,
): string;
public function thumbnail(
    string $imagePath,
    int $maxDimension,
): string;
```

All methods return the absolute path to the processed output file in the system temp directory. All methods run the pre-read checks described under [Security](#security): they throw `ImageFormatException` (from `marko/media`) for unsafe paths and non-raster content, and `ImagickProcessingException` for formats outside `allowed_raster_formats`, images exceeding the configured `limits`, and processing failures. `convert()` also checks the target `$format` against `allowed_raster_formats`.
