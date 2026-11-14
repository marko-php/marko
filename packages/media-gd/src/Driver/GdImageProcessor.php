<?php

declare(strict_types=1);

namespace Marko\MediaGd\Driver;

use GdImage;
use Marko\Media\Contracts\ImageProcessorInterface;
use Marko\MediaGd\Exceptions\GdProcessingException;

class GdImageProcessor implements ImageProcessorInterface
{
    public const int DEFAULT_MAX_PIXELS = 50_000_000;

    public const int DEFAULT_MAX_DIMENSION = 16384;

    /**
     * @throws GdProcessingException
     */
    public function __construct(
        private int $maxPixels = self::DEFAULT_MAX_PIXELS,
        private int $maxDimension = self::DEFAULT_MAX_DIMENSION,
    ) {
        if (!$this->isGdAvailable()) {
            throw GdProcessingException::extensionUnavailable();
        }

        if ($maxPixels < 1) {
            throw GdProcessingException::invalidLimit('max_pixels', $maxPixels);
        }

        if ($maxDimension < 1) {
            throw GdProcessingException::invalidLimit('max_dimension', $maxDimension);
        }
    }

    protected function isGdAvailable(): bool
    {
        return function_exists('imagecreatetruecolor');
    }

    /**
     * @throws GdProcessingException
     */
    public function resize(
        string $imagePath,
        int $width,
        int $height,
        bool $maintainAspect = true,
    ): string {
        $this->assertTargetDimensions('resize', $width, $height);
        $source = $this->loadImage($imagePath);
        $extension = $this->detectFormat($imagePath);

        if ($maintainAspect) {
            [$width, $height] = $this->calculateAspectRatioDimensions(
                imagesx($source),
                imagesy($source),
                $width,
                $height,
            );
        }

        $canvas = imagecreatetruecolor($width, $height);
        $this->prepareCanvasAlpha($canvas, $extension);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        $outputPath = sys_get_temp_dir() . '/' . uniqid('marko-gd-', true) . '.' . $extension;
        $this->encode($canvas, $extension, $outputPath);

        return $outputPath;
    }

    /**
     * @throws GdProcessingException
     */
    public function crop(
        string $imagePath,
        int $x,
        int $y,
        int $width,
        int $height,
    ): string {
        $this->assertTargetDimensions('crop', $width, $height);

        if (
            $x < 0
            || $y < 0
        ) {
            throw GdProcessingException::invalidCropOffset($x, $y);
        }

        $source = $this->loadImage($imagePath);
        $extension = $this->detectFormat($imagePath);

        $canvas = imagecreatetruecolor($width, $height);
        $this->prepareCanvasAlpha($canvas, $extension);
        imagecopy($canvas, $source, 0, 0, $x, $y, $width, $height);

        $outputPath = sys_get_temp_dir() . '/' . uniqid('marko-gd-', true) . '.' . $extension;
        $this->encode($canvas, $extension, $outputPath);

        return $outputPath;
    }

    /**
     * @throws GdProcessingException
     */
    public function convert(
        string $imagePath,
        string $format,
    ): string {
        $source = $this->loadImage($imagePath);
        $extension = $this->normalizeFormat($format);
        $outputPath = sys_get_temp_dir() . '/' . uniqid('marko-gd-', true) . '.' . $extension;

        $this->encode($source, $extension, $outputPath);

        return $outputPath;
    }

    /**
     * @throws GdProcessingException
     */
    public function thumbnail(
        string $imagePath,
        int $maxDimension,
    ): string {
        $this->assertTargetDimensions('thumbnail', $maxDimension, $maxDimension);
        $source = $this->loadImage($imagePath);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth >= $sourceHeight) {
            $newWidth = $maxDimension;
            $newHeight = max(1, (int) round($sourceHeight * $maxDimension / $sourceWidth));
        } else {
            $newHeight = $maxDimension;
            $newWidth = max(1, (int) round($sourceWidth * $maxDimension / $sourceHeight));
        }

        return $this->resize($imagePath, $newWidth, $newHeight, false);
    }

    /**
     * @throws GdProcessingException
     */
    private function detectFormat(
        string $imagePath,
    ): string {
        $imageType = exif_imagetype($imagePath);

        if ($imageType === false) {
            throw GdProcessingException::processingFailed('detect-format', $imagePath);
        }

        $extension = image_type_to_extension($imageType, false);

        if ($extension === false) {
            throw GdProcessingException::processingFailed('detect-format', $imagePath);
        }

        return $this->normalizeFormat($extension);
    }

    private function prepareCanvasAlpha(
        GdImage $canvas,
        string $extension,
    ): void {
        if (!in_array($extension, ['png', 'webp'], true)) {
            return;
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, imagesx($canvas) - 1, imagesy($canvas) - 1, $transparent);
    }

    /**
     * @throws GdProcessingException
     */
    protected function encode(
        GdImage $image,
        string $extension,
        string $outputPath,
    ): void {
        $result = match ($extension) {
            'jpeg' => imagejpeg($image, $outputPath),
            'png' => imagepng($image, $outputPath),
            'webp' => imagewebp($image, $outputPath),
            'gif' => imagegif($image, $outputPath),
        };

        if ($result === false) {
            throw GdProcessingException::processingFailed('encode', $outputPath);
        }
    }

    /**
     * @throws GdProcessingException
     */
    private function loadImage(
        string $imagePath,
    ): GdImage {
        // Read the declared dimensions from the header before decoding: a tiny
        // file can declare a huge canvas, and GD would try to allocate all of
        // it, hitting memory_limit with an uncatchable fatal error.
        $size = @getimagesize($imagePath);

        if ($size === false) {
            throw GdProcessingException::processingFailed('load', $imagePath);
        }

        [$width, $height] = $size;

        if (!$this->withinLimits($width, $height)) {
            throw GdProcessingException::sourceTooLarge(
                $imagePath,
                $width,
                $height,
                $this->maxPixels,
                $this->maxDimension,
            );
        }

        $contents = file_get_contents($imagePath);

        if ($contents === false) {
            throw GdProcessingException::processingFailed('load', $imagePath);
        }

        $image = imagecreatefromstring($contents);

        if ($image === false) {
            throw GdProcessingException::processingFailed('load', $imagePath);
        }

        return $image;
    }

    /**
     * @throws GdProcessingException
     */
    private function normalizeFormat(
        string $format,
    ): string {
        $normalized = strtolower($format);

        if ($normalized === 'jpg') {
            $normalized = 'jpeg';
        }

        if (!in_array($normalized, ['jpeg', 'png', 'gif', 'webp'], true)) {
            throw GdProcessingException::unsupportedFormat($format);
        }

        return $normalized;
    }

    /**
     * @return array{int, int}
     */
    private function calculateAspectRatioDimensions(
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        int $targetHeight,
    ): array {
        $widthRatio = $targetWidth / $sourceWidth;
        $heightRatio = $targetHeight / $sourceHeight;
        $ratio = min($widthRatio, $heightRatio);

        return [
            max(1, (int) round($sourceWidth * $ratio)),
            max(1, (int) round($sourceHeight * $ratio)),
        ];
    }

    /**
     * @throws GdProcessingException
     */
    private function assertTargetDimensions(
        string $operation,
        int $width,
        int $height,
    ): void {
        if (
            $width < 1
            || $height < 1
            || !$this->withinLimits($width, $height)
        ) {
            throw GdProcessingException::invalidTargetDimensions(
                $operation,
                $width,
                $height,
                $this->maxPixels,
                $this->maxDimension,
            );
        }
    }

    private function withinLimits(
        int $width,
        int $height,
    ): bool {
        return $width <= $this->maxDimension
            && $height <= $this->maxDimension
            && $width * $height <= $this->maxPixels;
    }
}
