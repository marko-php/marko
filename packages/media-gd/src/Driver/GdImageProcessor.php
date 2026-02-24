<?php

declare(strict_types=1);

namespace Marko\MediaGd\Driver;

use GdImage;
use Marko\Media\Contracts\ImageProcessorInterface;
use Marko\MediaGd\Exceptions\GdProcessingException;

class GdImageProcessor implements ImageProcessorInterface
{
    /**
     * @throws GdProcessingException
     */
    public function __construct()
    {
        if (!$this->isGdAvailable()) {
            throw GdProcessingException::extensionUnavailable();
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
        $source = $this->loadImage($imagePath);

        if ($maintainAspect) {
            [$width, $height] = $this->calculateAspectRatioDimensions(
                imagesx($source),
                imagesy($source),
                $width,
                $height,
            );
        }

        $canvas = imagecreatetruecolor($width, $height);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        $outputPath = sys_get_temp_dir() . '/' . uniqid('marko-gd-', true) . '.png';
        imagepng($canvas, $outputPath);

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
        $source = $this->loadImage($imagePath);

        $canvas = imagecreatetruecolor($width, $height);
        imagecopy($canvas, $source, 0, 0, $x, $y, $width, $height);

        $outputPath = sys_get_temp_dir() . '/' . uniqid('marko-gd-', true) . '.png';
        imagepng($canvas, $outputPath);

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

        match ($extension) {
            'jpeg' => imagejpeg($source, $outputPath),
            'png' => imagepng($source, $outputPath),
            'webp' => imagewebp($source, $outputPath),
            'gif' => imagegif($source, $outputPath),
        };

        return $outputPath;
    }

    /**
     * @throws GdProcessingException
     */
    public function thumbnail(
        string $imagePath,
        int $maxDimension,
    ): string {
        $source = $this->loadImage($imagePath);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth >= $sourceHeight) {
            $newWidth = $maxDimension;
            $newHeight = (int) round($sourceHeight * $maxDimension / $sourceWidth);
        } else {
            $newHeight = $maxDimension;
            $newWidth = (int) round($sourceWidth * $maxDimension / $sourceHeight);
        }

        return $this->resize($imagePath, $newWidth, $newHeight, false);
    }

    /**
     * @throws GdProcessingException
     */
    private function loadImage(
        string $imagePath,
    ): GdImage {
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
            (int) round($sourceWidth * $ratio),
            (int) round($sourceHeight * $ratio),
        ];
    }
}
