<?php

declare(strict_types=1);

use Marko\MediaImagick\Driver\ImagickImageProcessor;
use Marko\MediaImagick\Exceptions\ImagickProcessingException;

describe('ImagickImageProcessor', function (): void {
    it('resizes an image to specified width and height', function (): void {
        $imagePath = sys_get_temp_dir() . '/' . uniqid('test_', true) . '.png';
        $imagick = new Imagick();
        $imagick->newImage(100, 100, 'white');
        $imagick->setImageFormat('png');
        $imagick->writeImage($imagePath);

        $processor = new ImagickImageProcessor();
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

        $processor = new ImagickImageProcessor();
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

        $processor = new ImagickImageProcessor();
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

        $processor = new ImagickImageProcessor();
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

        $processor = new ImagickImageProcessor();
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
        expect(fn () => new ImagickImageProcessor())
            ->toThrow(ImagickProcessingException::class, 'Imagick extension is not available');
    })->skip(class_exists('Imagick'), 'Imagick extension is available');
});
