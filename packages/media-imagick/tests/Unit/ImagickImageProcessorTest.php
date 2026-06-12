<?php

declare(strict_types=1);

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\MediaImagick\Config\ImagickConfig;
use Marko\MediaImagick\Driver\ImagickImageProcessor;
use Marko\MediaImagick\Exceptions\ImagickProcessingException;
use Marko\Testing\Fake\FakeConfigRepository;

function makeImagickConfig(
    array $allowedRasterFormats = ['JPEG', 'PNG', 'GIF', 'WEBP'],
): ImagickConfig {
    $configRepo = new FakeConfigRepository([
        'media-imagick.allowed_raster_formats' => $allowedRasterFormats,
    ]);

    return new ImagickConfig($configRepo);
}

describe('ImagickImageProcessor', function (): void {
    it('resizes an image to specified width and height', function (): void {
        $imagePath = sys_get_temp_dir() . '/' . uniqid('test_', true) . '.png';
        $imagick = new Imagick();
        $imagick->newImage(100, 100, 'white');
        $imagick->setImageFormat('png');
        $imagick->writeImage($imagePath);

        $processor = new ImagickImageProcessor(makeImagickConfig());
        $result = $processor->resize($imagePath, 50, 50, false);

        $resultImagick = new Imagick($result);

        expect($resultImagick->getImageWidth())->toBe(50)
            ->and($resultImagick->getImageHeight())->toBe(50);

        @unlink($imagePath);
        @unlink($result);
    })->skip(!class_exists('Imagick'), 'Imagick extension not available');

    it('crops an image to specified region coordinates', function (): void {
        $imagePath = sys_get_temp_dir() . '/' . uniqid('test_', true) . '.png';
        $imagick = new Imagick();
        $imagick->newImage(100, 100, 'white');
        $imagick->setImageFormat('png');
        $imagick->writeImage($imagePath);

        $processor = new ImagickImageProcessor(makeImagickConfig());
        $result = $processor->crop($imagePath, 10, 10, 40, 30);

        $resultImagick = new Imagick($result);

        expect($resultImagick->getImageWidth())->toBe(40)
            ->and($resultImagick->getImageHeight())->toBe(30);

        @unlink($imagePath);
        @unlink($result);
    })->skip(!class_exists('Imagick'), 'Imagick extension not available');

    it('converts image format between JPEG, PNG, WebP, GIF, and AVIF', function (): void {
        $imagePath = sys_get_temp_dir() . '/' . uniqid('test_', true) . '.png';
        $imagick = new Imagick();
        $imagick->newImage(50, 50, 'white');
        $imagick->setImageFormat('png');
        $imagick->writeImage($imagePath);

        $processor = new ImagickImageProcessor(makeImagickConfig());
        $result = $processor->convert($imagePath, 'jpeg');

        $resultImagick = new Imagick($result);

        expect(strtolower($resultImagick->getImageFormat()))->toBe('jpeg');

        @unlink($imagePath);
        @unlink($result);
    })->skip(!class_exists('Imagick'), 'Imagick extension not available');

    it('generates thumbnail at specified maximum dimension', function (): void {
        $imagePath = sys_get_temp_dir() . '/' . uniqid('test_', true) . '.png';
        $imagick = new Imagick();
        $imagick->newImage(200, 100, 'white');
        $imagick->setImageFormat('png');
        $imagick->writeImage($imagePath);

        $processor = new ImagickImageProcessor(makeImagickConfig());
        $result = $processor->thumbnail($imagePath, 80);

        $resultImagick = new Imagick($result);

        expect($resultImagick->getImageWidth())->toBeLessThanOrEqual(80)
            ->and($resultImagick->getImageHeight())->toBeLessThanOrEqual(80);

        @unlink($imagePath);
        @unlink($result);
    })->skip(!class_exists('Imagick'), 'Imagick extension not available');

    it('preserves aspect ratio during resize when requested', function (): void {
        $imagePath = sys_get_temp_dir() . '/' . uniqid('test_', true) . '.png';
        $imagick = new Imagick();
        $imagick->newImage(200, 100, 'white');
        $imagick->setImageFormat('png');
        $imagick->writeImage($imagePath);

        $processor = new ImagickImageProcessor(makeImagickConfig());
        $result = $processor->resize($imagePath, 80, 80, true);

        $resultImagick = new Imagick($result);
        $width = $resultImagick->getImageWidth();
        $height = $resultImagick->getImageHeight();

        expect($width)->toBeLessThanOrEqual(80)
            ->and($height)->toBeLessThanOrEqual(80)
            ->and($width)->toBeGreaterThan($height);

        @unlink($imagePath);
        @unlink($result);
    })->skip(!class_exists('Imagick'), 'Imagick extension not available');

    it('throws ImagickProcessingException when Imagick extension is unavailable', function (): void {
        expect(fn () => new ImagickImageProcessor(makeImagickConfig()))
            ->toThrow(ImagickProcessingException::class, 'Imagick extension is not available');
    })->skip(class_exists('Imagick'), 'Imagick extension is available');

    it('throws an ImagickProcessingException when the image format is not in the raster allowlist', function (): void {
        // Config only allows JPEG and PNG — SVG is not in the list
        $config = makeImagickConfig(allowedRasterFormats: ['JPEG', 'PNG']);
        $processor = new ImagickImageProcessor($config);

        $imagePath = sys_get_temp_dir() . '/' . uniqid('test_svg_', true) . '.svg';
        // Create a minimal SVG file that Imagick can open
        file_put_contents($imagePath, '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>');

        expect(fn () => $processor->resize($imagePath, 10, 10))
            ->toThrow(ImagickProcessingException::class, 'SVG');

        @unlink($imagePath);
    })->skip(!class_exists('Imagick'), 'Imagick extension not available');

    it('processes and writes an allowlisted raster image successfully', function (): void {
        $config = makeImagickConfig(allowedRasterFormats: ['JPEG', 'PNG', 'GIF', 'WEBP']);
        $processor = new ImagickImageProcessor($config);

        $imagePath = sys_get_temp_dir() . '/' . uniqid('test_', true) . '.png';
        $imagick = new Imagick();
        $imagick->newImage(100, 100, 'white');
        $imagick->setImageFormat('png');
        $imagick->writeImage($imagePath);

        $result = $processor->resize($imagePath, 50, 50, false);

        $resultImagick = new Imagick($result);

        expect($resultImagick->getImageWidth())->toBe(50)
            ->and($resultImagick->getImageHeight())->toBe(50);

        @unlink($imagePath);
        @unlink($result);
    })->skip(!class_exists('Imagick'), 'Imagick extension not available');

    it(
        'reads the Imagick raster allowlist from configuration and throws ConfigNotFoundException when the key is missing',
        function (): void {
            $config = new ImagickConfig(new FakeConfigRepository([]));

            expect(fn () => $config->allowedRasterFormats())
                ->toThrow(ConfigNotFoundException::class);
        },
    )->skip(!class_exists('Imagick'), 'Imagick extension not available');
});
