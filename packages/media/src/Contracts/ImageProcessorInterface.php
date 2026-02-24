<?php

declare(strict_types=1);

namespace Marko\Media\Contracts;

interface ImageProcessorInterface
{
    /**
     * Resize an image to the specified dimensions.
     */
    public function resize(
        string $imagePath,
        int $width,
        int $height,
        bool $maintainAspect = true,
    ): string;

    /**
     * Crop an image at the specified coordinates and dimensions.
     */
    public function crop(
        string $imagePath,
        int $x,
        int $y,
        int $width,
        int $height,
    ): string;

    /**
     * Convert an image to the specified format.
     */
    public function convert(
        string $imagePath,
        string $format,
    ): string;
}
