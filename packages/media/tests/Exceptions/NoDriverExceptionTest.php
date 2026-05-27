<?php

declare(strict_types=1);

namespace Marko\Media\Tests\Exceptions;

use Marko\Media\Exceptions\MediaException;
use Marko\Media\Exceptions\NoDriverException;

it('media NoDriverException reads from known-drivers.php and includes docs URLs', function (): void {
    $exception = NoDriverException::noDriverInstalled();
    $suggestion = $exception->getSuggestion();

    expect($suggestion)
        ->toContain('marko/media-gd')
        ->and($suggestion)->toContain('marko/media-imagick')
        ->and($suggestion)->toContain('https://marko.build/docs/packages/media-gd/')
        ->and($suggestion)->toContain('https://marko.build/docs/packages/media-imagick/');
});

it('provides suggestion with composer require commands for all driver packages', function (): void {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception->getSuggestion())
        ->toContain('composer require marko/media-gd')
        ->and($exception->getSuggestion())->toContain('composer require marko/media-imagick');
});

it('includes context about resolving media/image processing interfaces', function (): void {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception->getContext())->not->toBeEmpty()
        ->and($exception->getContext())->toContain('media');
});

it('extends MediaException', function (): void {
    $exception = NoDriverException::noDriverInstalled();

    expect($exception)->toBeInstanceOf(MediaException::class);
});
