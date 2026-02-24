<?php

declare(strict_types=1);

namespace Marko\MediaImagick\Driver;

use Imagick;
use ImagickException;
use Marko\Media\Contracts\ImageProcessorInterface;
use Marko\MediaImagick\Exceptions\ImagickProcessingException;

class ImagickImageProcessor implements ImageProcessorInterface
{
    /**
     * @throws ImagickProcessingException
     */
    public function __construct()
    {
        if (!class_exists('Imagick')) {
            throw new ImagickProcessingException(
                message: 'Imagick extension is not available',
                context: 'ImagickImageProcessor requires the Imagick PHP extension',
                suggestion: 'Install the Imagick PHP extension: pecl install imagick',
            );
        }
    }

    /**
     * @throws ImagickProcessingException
     */
    public function resize(
        string $imagePath,
        int $width,
        int $height,
        bool $maintainAspect = true,
    ): string {
        try {
            $imagick = new Imagick($imagePath);

            if ($maintainAspect) {
                $imagick->thumbnailImage($width, $height, true);
            } else {
                $imagick->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
            }

            $format = strtolower($imagick->getImageFormat());
            $outputPath = sys_get_temp_dir() . '/' . uniqid('imagick_', true) . '.' . $format;
            $imagick->writeImage($outputPath);
            $imagick->clear();

            return $outputPath;
        } catch (ImagickException $e) {
            throw new ImagickProcessingException(
                message: 'Failed to resize image',
                context: "While resizing image at path: $imagePath",
                suggestion: 'Verify the image file exists and is readable',
                previous: $e,
            );
        }
    }

    /**
     * @throws ImagickProcessingException
     */
    public function crop(
        string $imagePath,
        int $x,
        int $y,
        int $width,
        int $height,
    ): string {
        try {
            $imagick = new Imagick($imagePath);
            $imagick->cropImage($width, $height, $x, $y);

            $format = strtolower($imagick->getImageFormat());
            $outputPath = sys_get_temp_dir() . '/' . uniqid('imagick_', true) . '.' . $format;
            $imagick->writeImage($outputPath);
            $imagick->clear();

            return $outputPath;
        } catch (ImagickException $e) {
            throw new ImagickProcessingException(
                message: 'Failed to crop image',
                context: "While cropping image at path: $imagePath",
                suggestion: 'Verify the image file exists and the crop coordinates are within bounds',
                previous: $e,
            );
        }
    }

    /**
     * @throws ImagickProcessingException
     */
    public function convert(
        string $imagePath,
        string $format,
    ): string {
        try {
            $imagick = new Imagick($imagePath);
            $imagick->setImageFormat($format);

            $outputPath = sys_get_temp_dir() . '/' . uniqid('imagick_', true) . '.' . strtolower($format);
            $imagick->writeImage($outputPath);
            $imagick->clear();

            return $outputPath;
        } catch (ImagickException $e) {
            throw new ImagickProcessingException(
                message: 'Failed to convert image',
                context: "While converting image at path: $imagePath to format: $format",
                suggestion: 'Verify the image file exists and the target format is supported',
                previous: $e,
            );
        }
    }

    /**
     * @throws ImagickProcessingException
     */
    public function thumbnail(
        string $imagePath,
        int $maxDimension,
    ): string {
        try {
            $imagick = new Imagick($imagePath);
            $imagick->thumbnailImage($maxDimension, $maxDimension, true, true);

            $format = strtolower($imagick->getImageFormat());
            $outputPath = sys_get_temp_dir() . '/' . uniqid('imagick_', true) . '.' . $format;
            $imagick->writeImage($outputPath);
            $imagick->clear();

            return $outputPath;
        } catch (ImagickException $e) {
            throw new ImagickProcessingException(
                message: 'Failed to generate thumbnail',
                context: "While generating thumbnail for image at path: $imagePath",
                suggestion: 'Verify the image file exists and is readable',
                previous: $e,
            );
        }
    }
}
