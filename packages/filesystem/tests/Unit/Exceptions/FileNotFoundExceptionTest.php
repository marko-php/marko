<?php

declare(strict_types=1);

use Marko\Filesystem\Exceptions\FileNotFoundException;
use Marko\Filesystem\Exceptions\FilesystemException;

it('extends FilesystemException', function () {
    $exception = FileNotFoundException::forPath('missing.txt');

    expect($exception)->toBeInstanceOf(FilesystemException::class);
});

it('creates exception with path in message', function () {
    $exception = FileNotFoundException::forPath('uploads/file.txt');

    expect($exception->getMessage())->toBe("File not found: 'uploads/file.txt'");
});

it('creates exception with context', function () {
    $exception = FileNotFoundException::forPath('documents/report.pdf');

    expect($exception->getContext())->toBe('Requested path: documents/report.pdf');
});

it('creates exception with suggestion', function () {
    $exception = FileNotFoundException::forPath('test.txt');

    expect($exception->getSuggestion())->toBe('Verify the file exists and the path is correct');
});
