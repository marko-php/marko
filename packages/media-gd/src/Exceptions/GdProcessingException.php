<?php

declare(strict_types=1);

namespace Marko\MediaGd\Exceptions;

use Marko\Core\Exceptions\MarkoException;

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

    public static function unsupportedFormat(
        string $format,
    ): self {
        return new self(
            message: "Unsupported image format: '$format'",
            context: 'Converting image format',
            suggestion: 'Use one of the supported formats: jpeg, png, gif, webp',
        );
    }
}
