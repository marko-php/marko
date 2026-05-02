<?php

declare(strict_types=1);

use Marko\Log\File\Rotation\RotationStrategyInterface;
use Marko\Log\File\Rotation\SizeRotation;

function createTempRotationDir(): string
{
    $dir = sys_get_temp_dir() . '/marko-rotation-test-' . bin2hex(random_bytes(8));
    mkdir($dir, 0755, true);

    return $dir;
}

function cleanupRotationDir(
    string $dir,
): void {
    if (!is_dir($dir)) {
        return;
    }

    $files = glob($dir . '/*');

    if ($files !== false) {
        foreach ($files as $file) {
            unlink($file);
        }
    }

    rmdir($dir);
}

it('implements RotationStrategyInterface', function () {
    $rotation = new SizeRotation();

    expect($rotation)->toBeInstanceOf(RotationStrategyInterface::class);
});

it('returns base file path when file does not exist', function () {
    $dir = createTempRotationDir();
    $rotation = new SizeRotation(maxSize: 1024);

    $path = $rotation->getCurrentPath($dir, 'app');

    expect($path)->toBe("$dir/app.log");

    cleanupRotationDir($dir);
});

it('returns base file path when under max size', function () {
    $dir = createTempRotationDir();
    file_put_contents("$dir/app.log", 'Small content');

    $rotation = new SizeRotation(maxSize: 1024);
    $path = $rotation->getCurrentPath($dir, 'app');

    expect($path)->toBe("$dir/app.log");

    cleanupRotationDir($dir);
});

it('returns rotated path when base file exceeds max size', function () {
    $dir = createTempRotationDir();
    // Create file larger than max size
    file_put_contents("$dir/app.log", str_repeat('x', 100));

    $rotation = new SizeRotation(maxSize: 50);
    $path = $rotation->getCurrentPath($dir, 'app');

    expect($path)->toBe("$dir/app.1.log");

    cleanupRotationDir($dir);
});

it('returns next available rotated path', function () {
    $dir = createTempRotationDir();
    file_put_contents("$dir/app.log", str_repeat('x', 100));
    file_put_contents("$dir/app.1.log", str_repeat('x', 100));

    $rotation = new SizeRotation(maxSize: 50);
    $path = $rotation->getCurrentPath($dir, 'app');

    expect($path)->toBe("$dir/app.2.log");

    cleanupRotationDir($dir);
});

it('indicates rotation not needed for non-existent file', function () {
    $rotation = new SizeRotation(maxSize: 1024);

    expect($rotation->needsRotation('/non/existent/file.log'))->toBeFalse();
});

it('indicates rotation not needed for small file', function () {
    $dir = createTempRotationDir();
    file_put_contents("$dir/app.log", 'Small');

    $rotation = new SizeRotation(maxSize: 1024);

    expect($rotation->needsRotation("$dir/app.log"))->toBeFalse();

    cleanupRotationDir($dir);
});

it('indicates rotation needed for large file', function () {
    $dir = createTempRotationDir();
    file_put_contents("$dir/app.log", str_repeat('x', 200));

    $rotation = new SizeRotation(maxSize: 100);

    expect($rotation->needsRotation("$dir/app.log"))->toBeTrue();

    cleanupRotationDir($dir);
});

it('uses default max size of 10MB', function () {
    $rotation = new SizeRotation();

    $dir = createTempRotationDir();
    // File under 10MB should not need rotation
    file_put_contents("$dir/app.log", str_repeat('x', 1000));

    expect($rotation->needsRotation("$dir/app.log"))->toBeFalse();

    cleanupRotationDir($dir);
});
