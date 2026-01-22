<?php

declare(strict_types=1);

use Marko\Filesystem\Exceptions\FilesystemException;
use Marko\Filesystem\Exceptions\PermissionException;

it('extends FilesystemException', function () {
    $exception = PermissionException::cannotRead('file.txt');

    expect($exception)->toBeInstanceOf(FilesystemException::class);
});

it('creates cannot read exception', function () {
    $exception = PermissionException::cannotRead('secret.txt');

    expect($exception->getMessage())->toBe("Cannot read file: 'secret.txt'")
        ->and($exception->getContext())->toBe('File is not readable')
        ->and($exception->getSuggestion())->toContain('read access');
});

it('creates cannot write exception', function () {
    $exception = PermissionException::cannotWrite('/readonly/file.txt');

    expect($exception->getMessage())->toBe("Cannot write to: '/readonly/file.txt'")
        ->and($exception->getContext())->toBe('Directory or file is not writable')
        ->and($exception->getSuggestion())->toContain('write access');
});

it('creates cannot delete exception', function () {
    $exception = PermissionException::cannotDelete('protected.txt');

    expect($exception->getMessage())->toBe("Cannot delete: 'protected.txt'")
        ->and($exception->getContext())->toBe('Insufficient permissions to delete');
});
