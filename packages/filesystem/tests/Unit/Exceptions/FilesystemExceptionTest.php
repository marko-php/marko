<?php

declare(strict_types=1);

use Marko\Filesystem\Exceptions\FilesystemException;

it('stores message correctly', function () {
    $exception = new FilesystemException('Test error');

    expect($exception->getMessage())->toBe('Test error');
});

it('stores context correctly', function () {
    $exception = new FilesystemException('Test error', 'test context');

    expect($exception->getContext())->toBe('test context');
});

it('stores suggestion correctly', function () {
    $exception = new FilesystemException('Test error', 'context', 'try this');

    expect($exception->getSuggestion())->toBe('try this');
});

it('has empty context by default', function () {
    $exception = new FilesystemException('Test error');

    expect($exception->getContext())->toBe('');
});

it('has empty suggestion by default', function () {
    $exception = new FilesystemException('Test error');

    expect($exception->getSuggestion())->toBe('');
});

it('creates from throwable', function () {
    $original = new Exception('Original error');
    $exception = FilesystemException::fromThrowable($original);

    expect($exception->getMessage())->toBe('Original error')
        ->and($exception->getPrevious())->toBe($original)
        ->and($exception->getContext())->toContain('Exception');
});
