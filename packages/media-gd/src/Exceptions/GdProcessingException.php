<?php

declare(strict_types=1);

namespace Marko\MediaGd\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Marko\Core\Exceptions\UnsupportedFeature;

class GdProcessingException extends MarkoException
{
    public static function extensionUnavailable(): self
    {
        return new self(
            message: 'The GD extension is not available',
            context: 'Attempting to instantiate GdImageProcessor',
            suggestion: 'Install and enable the GD extension: php-gd or ext-gd',
        );
    }

    public static function processingFailed(
        string $operation,
        string $imagePath,
    ): self {
        return new self(
            message: "GD image processing failed during '$operation' on '$imagePath'",
            context: "Processing image at path: $imagePath",
            suggestion: 'Verify the image file exists, is readable, and is a valid JPEG, PNG, GIF, or WebP image',
        );
    }

    public static function invalidLimit(
        string $name,
        int $value,
    ): self {
        return new self(
            message: "Invalid GD image limit '$name': $value",
            context: 'Configuring GdImageProcessor dimension limits',
            suggestion: "Set media-gd.$name to a positive integer in config/media-gd.php",
        );
    }

    public static function sourceTooLarge(
        string $imagePath,
        int $width,
        int $height,
        int $maxPixels,
        int $maxDimension,
    ): self {
        return new self(
            message: "Image '$imagePath' is {$width}x$height, which exceeds the GD limit of $maxDimension px per side or $maxPixels total pixels",
            context: 'Checking image dimensions before decoding with GD',
            suggestion: 'Reject or downscale the image before processing, or raise media-gd.max_pixels / media-gd.max_dimension (and memory_limit) if such images are expected',
        );
    }

    public static function invalidTargetDimensions(
        string $operation,
        int $width,
        int $height,
        int $maxPixels,
        int $maxDimension,
    ): self {
        return new self(
            message: "Invalid target dimensions {$width}x$height for '$operation'",
            context: "Validating requested output size for GD '$operation'",
            suggestion: "Width and height must each be between 1 and $maxDimension, with at most $maxPixels total pixels",
        );
    }

    public static function invalidCropOffset(
        int $x,
        int $y,
    ): self {
        return new self(
            message: "Invalid crop offset ($x, $y)",
            context: "Validating crop coordinates for GD 'crop'",
            suggestion: 'Crop x and y offsets must be zero or greater',
        );
    }

    public static function unsupportedFormat(
        string $format,
    ): UnsupportedGdFeatureException {
        return new UnsupportedGdFeatureException(new UnsupportedFeature(
            feature: "image format '$format'",
            driver: 'GD',
            suggestion: 'Use one of the supported formats: jpeg, png, gif, webp',
            context: 'Converting image format',
            message: "Unsupported image format: '$format'",
        ));
    }
}
