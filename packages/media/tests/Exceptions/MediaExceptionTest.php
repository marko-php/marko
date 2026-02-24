<?php

declare(strict_types=1);

namespace Marko\Media\Tests\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Marko\Media\Exceptions\FileNotFoundException;
use Marko\Media\Exceptions\MediaException;
use Marko\Media\Exceptions\UploadException;

it('throws MediaException with context and suggestion for media operation failures', function (): void {
    // MediaException extends MarkoException
    $exception = new MediaException(
        message: 'Media operation failed',
        context: 'Attempted to upload file.jpg',
        suggestion: 'Check disk configuration',
    );

    expect($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toBe('Media operation failed')
        ->and($exception->getContext())->toBe('Attempted to upload file.jpg')
        ->and($exception->getSuggestion())->toBe('Check disk configuration');

    // UploadException extends MediaException and has static factory
    $uploadException = UploadException::invalidMimeType('application/exe', ['image/jpeg', 'image/png']);
    expect($uploadException)->toBeInstanceOf(MediaException::class)
        ->toBeInstanceOf(MarkoException::class)
        ->and($uploadException->getMessage())->toContain('application/exe')
        ->and($uploadException->getContext())->not->toBeEmpty()
        ->and($uploadException->getSuggestion())->not->toBeEmpty();

    // FileNotFoundException extends MediaException and has static factory
    $fileException = FileNotFoundException::forPath('/storage/images/photo.jpg', 'local');
    expect($fileException)->toBeInstanceOf(MediaException::class)
        ->toBeInstanceOf(MarkoException::class)
        ->and($fileException->getMessage())->toContain('/storage/images/photo.jpg')
        ->and($fileException->getContext())->not->toBeEmpty()
        ->and($fileException->getSuggestion())->not->toBeEmpty();
});
