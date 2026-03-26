<?php

declare(strict_types=1);

namespace Marko\Media\Tests\Exceptions;

use Marko\Media\Exceptions\MediaException;
use Marko\Media\Exceptions\NoDriverException;
use ReflectionClass;

it('has DRIVER_PACKAGES constant listing marko/media-gd and marko/media-imagick', function (): void {
    $reflection = new ReflectionClass(NoDriverException::class);
    $constants = $reflection->getConstants();

    expect($constants)->toHaveKey('DRIVER_PACKAGES')
        ->and($constants['DRIVER_PACKAGES'])->toBe(['marko/media-gd', 'marko/media-imagick']);
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
