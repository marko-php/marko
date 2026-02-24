<?php

declare(strict_types=1);

namespace Marko\Media\Tests\Contracts;

use Marko\Media\Contracts\ImageProcessorInterface;
use ReflectionClass;
use ReflectionNamedType;

it('defines ImageProcessorInterface with resize, crop, and convert methods', function (): void {
    $reflection = new ReflectionClass(ImageProcessorInterface::class);

    expect($reflection->isInterface())->toBeTrue();

    // resize method
    $resize = $reflection->getMethod('resize');
    $resizeParams = $resize->getParameters();
    $resizeReturn = $resize->getReturnType();

    expect($reflection->hasMethod('resize'))->toBeTrue()
        ->and($resizeParams)->toHaveCount(4)
        ->and($resizeParams[0]->getName())->toBe('imagePath')
        ->and($resizeParams[1]->getName())->toBe('width')
        ->and($resizeParams[2]->getName())->toBe('height')
        ->and($resizeParams[3]->getName())->toBe('maintainAspect')
        ->and($resizeParams[3]->isDefaultValueAvailable())->toBeTrue()
        ->and($resizeParams[3]->getDefaultValue())->toBeTrue()
        ->and($resizeReturn)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($resizeReturn->getName())->toBe('string');

    // crop method
    $crop = $reflection->getMethod('crop');
    $cropParams = $crop->getParameters();
    $cropReturn = $crop->getReturnType();

    expect($reflection->hasMethod('crop'))->toBeTrue()
        ->and($cropParams)->toHaveCount(5)
        ->and($cropParams[0]->getName())->toBe('imagePath')
        ->and($cropParams[1]->getName())->toBe('x')
        ->and($cropParams[2]->getName())->toBe('y')
        ->and($cropParams[3]->getName())->toBe('width')
        ->and($cropParams[4]->getName())->toBe('height')
        ->and($cropReturn)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($cropReturn->getName())->toBe('string');

    // convert method
    $convert = $reflection->getMethod('convert');
    $convertParams = $convert->getParameters();
    $convertReturn = $convert->getReturnType();

    expect($reflection->hasMethod('convert'))->toBeTrue()
        ->and($convertParams)->toHaveCount(2)
        ->and($convertParams[0]->getName())->toBe('imagePath')
        ->and($convertParams[1]->getName())->toBe('format')
        ->and($convertReturn)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($convertReturn->getName())->toBe('string');
});
