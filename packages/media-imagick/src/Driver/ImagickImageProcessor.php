<?php

declare(strict_types=1);

namespace Marko\MediaImagick\Driver;

use Imagick;
use ImagickException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Media\Contracts\ImageProcessorInterface;
use Marko\Media\Exceptions\ImageFormatException;
use Marko\Media\Image\ImageFormatSniffer;
use Marko\MediaImagick\Config\ImagickConfig;
use Marko\MediaImagick\Exceptions\ImagickProcessingException;

readonly class ImagickImageProcessor implements ImageProcessorInterface
{
    /**
     * @throws ImagickProcessingException
     */
    public function __construct(
        private ImagickConfig $config,
        private ImageFormatSniffer $sniffer = new ImageFormatSniffer(),
    ) {
        if (!class_exists('Imagick')) {
            throw new ImagickProcessingException(
                message: 'Imagick extension is not available',
                context: 'ImagickImageProcessor requires the Imagick PHP extension',
                suggestion: 'Install the Imagick PHP extension: pecl install imagick',
            );
        }
    }

    /**
     * @throws ImagickProcessingException|ImageFormatException|ConfigNotFoundException
     */
    public function resize(
        string $imagePath,
        int $width,
        int $height,
        bool $maintainAspect = true,
    ): string {
        $imagick = $this->open($imagePath);

        try {
            if ($maintainAspect) {
                $imagick->thumbnailImage($width, $height, true);
            } else {
                $imagick->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
            }

            return $this->write($imagick, $imagick->getImageFormat());
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
     * @throws ImagickProcessingException|ImageFormatException|ConfigNotFoundException
     */
    public function crop(
        string $imagePath,
        int $x,
        int $y,
        int $width,
        int $height,
    ): string {
        $imagick = $this->open($imagePath);

        try {
            $imagick->cropImage($width, $height, $x, $y);

            return $this->write($imagick, $imagick->getImageFormat());
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
     * @throws ImagickProcessingException|ImageFormatException|ConfigNotFoundException
     */
    public function convert(
        string $imagePath,
        string $format,
    ): string {
        $targetFormat = $this->normaliseFormat($format);
        $this->assertAllowedFormat($targetFormat);

        $imagick = $this->open($imagePath);

        try {
            $imagick->setImageFormat($targetFormat);

            return $this->write($imagick, $targetFormat);
        } catch (ImagickException $e) {
            throw new ImagickProcessingException(
                message: 'Failed to convert image',
                context: "While converting image at path: $imagePath to format: $targetFormat",
                suggestion: 'Verify the image file exists and the target format is supported',
                previous: $e,
            );
        }
    }

    /**
     * @throws ImagickProcessingException|ImageFormatException|ConfigNotFoundException
     */
    public function thumbnail(
        string $imagePath,
        int $maxDimension,
    ): string {
        $imagick = $this->open($imagePath);

        try {
            $imagick->thumbnailImage($maxDimension, $maxDimension, true, true);

            return $this->write($imagick, $imagick->getImageFormat());
        } catch (ImagickException $e) {
            throw new ImagickProcessingException(
                message: 'Failed to generate thumbnail',
                context: "While generating thumbnail for image at path: $imagePath",
                suggestion: 'Verify the image file exists and is readable',
                previous: $e,
            );
        }
    }

    /**
     * Read an image safely: sniff its format from magic bytes, check it
     * against the allowlist, apply resource limits, check its dimensions from
     * the header, and only then decode it with an explicit coder prefix so
     * ImageMagick can never pick a different (e.g. SVG, MVG, MSL, PS) coder.
     *
     * @throws ImagickProcessingException|ImageFormatException|ConfigNotFoundException
     */
    private function open(
        string $imagePath,
    ): Imagick {
        $format = $this->sniffer->sniff($imagePath);
        $this->assertAllowedFormat($format);

        $realPath = realpath($imagePath);

        if ($realPath === false) {
            throw ImageFormatException::notReadable($imagePath);
        }

        // PHP parses the header itself for the common formats, so oversized
        // images are refused before ImageMagick sees a single byte.
        $dimensions = getimagesize($realPath);

        if ($dimensions !== false) {
            $this->assertWithinDimensionLimits($imagePath, $dimensions[0], $dimensions[1]);
        }

        $source = $format . ':' . $realPath;
        $this->applyResourceLimits();

        try {
            $header = new Imagick();
            $header->pingImage($source);
            $this->assertWithinDimensionLimits($imagePath, $header->getImageWidth(), $header->getImageHeight());
            $header->clear();

            $imagick = new Imagick();
            $imagick->readImage($source);
        } catch (ImagickException $e) {
            throw new ImagickProcessingException(
                message: 'Failed to read image: ' . $e->getMessage(),
                context: "While reading $format image at path: $imagePath",
                suggestion: 'Verify the file is a valid, uncorrupted image within the configured resource limits',
                previous: $e,
            );
        }

        $this->assertAllowedFormat($imagick->getImageFormat());

        return $imagick;
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function applyResourceLimits(): void
    {
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_WIDTH, $this->config->maxWidth());
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_HEIGHT, $this->config->maxHeight());
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_AREA, $this->config->maxPixels());
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, $this->config->memoryBytes());
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_TIME, $this->config->timeSeconds());
    }

    /**
     * @throws ImagickProcessingException|ConfigNotFoundException
     */
    private function assertWithinDimensionLimits(
        string $imagePath,
        int $width,
        int $height,
    ): void {
        $maxWidth = $this->config->maxWidth();
        $maxHeight = $this->config->maxHeight();
        $maxPixels = $this->config->maxPixels();

        if (
            $width > $maxWidth
            || $height > $maxHeight
            || $width * $height > $maxPixels
        ) {
            throw ImagickProcessingException::imageTooLarge(
                $imagePath,
                $width,
                $height,
                $maxWidth,
                $maxHeight,
                $maxPixels,
            );
        }
    }

    /**
     * @throws ImagickException
     */
    private function write(
        Imagick $imagick,
        string $format,
    ): string {
        $format = strtolower($format);
        $outputPath = sys_get_temp_dir() . '/' . uniqid('imagick_', true) . '.' . $format;
        $imagick->writeImage(strtoupper($format) . ':' . $outputPath);
        $imagick->clear();

        return $outputPath;
    }

    private function normaliseFormat(
        string $format,
    ): string {
        $format = strtoupper($format);

        return match ($format) {
            'JPG' => 'JPEG',
            'TIF' => 'TIFF',
            default => $format,
        };
    }

    /**
     * @throws ImagickProcessingException|ConfigNotFoundException
     */
    private function assertAllowedFormat(
        string $format,
    ): void {
        $allowedFormats = $this->config->allowedRasterFormats();
        $upperFormat = strtoupper($format);

        if (!in_array($upperFormat, $allowedFormats, true)) {
            throw ImagickProcessingException::formatNotAllowed($upperFormat, $allowedFormats);
        }
    }
}
