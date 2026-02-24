<?php

declare(strict_types=1);

use Marko\MediaGd\Driver\GdImageProcessor;
use Marko\MediaGd\Exceptions\GdProcessingException;

class GdImageProcessorWithoutGd extends GdImageProcessor
{
    protected function isGdAvailable(): bool
    {
        return false;
    }
}

function createTestImage(
    int $width = 100,
    int $height = 100,
): string {
    $path = sys_get_temp_dir() . '/marko-test-' . uniqid() . '.png';
    $image = imagecreatetruecolor($width, $height);
    $color = imagecolorallocate($image, 255, 0, 0);
    imagefill($image, 0, 0, $color);
    imagepng($image, $path);

    return $path;
}

it('throws GdProcessingException when GD extension is unavailable', function (): void {
    expect(fn () => new GdImageProcessorWithoutGd())
        ->toThrow(GdProcessingException::class, 'The GD extension is not available');
});

it('preserves aspect ratio during resize when requested', function (): void {
    $processor = new GdImageProcessor();
    $sourcePath = createTestImage(200, 100);

    $resultPath = $processor->resize($sourcePath, 50, 50, true);

    [$width, $height] = getimagesize($resultPath);

    expect($width)->toBe(50)
        ->and($height)->toBe(25);

    unlink($sourcePath);
    unlink($resultPath);
})->skip(!extension_loaded('gd'), 'GD extension not available');

it('generates thumbnail at specified maximum dimension', function (): void {
    $processor = new GdImageProcessor();
    $sourcePath = createTestImage(200, 100);

    $resultPath = $processor->thumbnail($sourcePath, 50);

    [$width, $height] = getimagesize($resultPath);

    expect($width)->toBe(50)
        ->and($height)->toBe(25);

    unlink($sourcePath);
    unlink($resultPath);
})->skip(!extension_loaded('gd'), 'GD extension not available');

it('converts image format between JPEG, PNG, WebP, and GIF', function (): void {
    $processor = new GdImageProcessor();
    $sourcePath = createTestImage(50, 50);

    $jpegPath = $processor->convert($sourcePath, 'jpeg');
    $webpPath = $processor->convert($sourcePath, 'webp');
    $gifPath = $processor->convert($sourcePath, 'gif');

    expect(str_ends_with($jpegPath, '.jpeg'))->toBeTrue()
        ->and(str_ends_with($webpPath, '.webp'))->toBeTrue()
        ->and(str_ends_with($gifPath, '.gif'))->toBeTrue()
        ->and(file_exists($jpegPath))->toBeTrue()
        ->and(file_exists($webpPath))->toBeTrue()
        ->and(file_exists($gifPath))->toBeTrue();

    unlink($sourcePath);
    unlink($jpegPath);
    unlink($webpPath);
    unlink($gifPath);
})->skip(!extension_loaded('gd'), 'GD extension not available');

it('crops an image to specified region coordinates', function (): void {
    $processor = new GdImageProcessor();
    $sourcePath = createTestImage(100, 100);

    $resultPath = $processor->crop($sourcePath, 10, 10, 40, 30);

    [$width, $height] = getimagesize($resultPath);

    expect($width)->toBe(40)
        ->and($height)->toBe(30);

    unlink($sourcePath);
    unlink($resultPath);
})->skip(!extension_loaded('gd'), 'GD extension not available');

it('resizes an image to specified width and height', function (): void {
    $processor = new GdImageProcessor();
    $sourcePath = createTestImage(100, 100);

    $resultPath = $processor->resize($sourcePath, 50, 30, false);

    [$width, $height] = getimagesize($resultPath);

    expect($width)->toBe(50)
        ->and($height)->toBe(30);

    unlink($sourcePath);
    unlink($resultPath);
})->skip(!extension_loaded('gd'), 'GD extension not available');
