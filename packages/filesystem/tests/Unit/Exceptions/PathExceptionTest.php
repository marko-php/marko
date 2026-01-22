<?php

declare(strict_types=1);

use Marko\Filesystem\Exceptions\FilesystemException;
use Marko\Filesystem\Exceptions\PathException;

it('extends FilesystemException', function () {
    $exception = PathException::traversalAttempt('../etc/passwd');

    expect($exception)->toBeInstanceOf(FilesystemException::class);
});

it('creates traversal attempt exception', function () {
    $exception = PathException::traversalAttempt('../etc/passwd');

    expect($exception->getMessage())->toBe('Path traversal attempt detected')
        ->and($exception->getContext())->toBe('Attempted path: ../etc/passwd')
        ->and($exception->getSuggestion())->toContain("without '..' sequences");
});

it('creates invalid path exception', function () {
    $exception = PathException::invalidPath('bad*path', 'Contains invalid characters');

    expect($exception->getMessage())->toBe("Invalid path: 'bad*path'")
        ->and($exception->getContext())->toBe('Contains invalid characters');
});
