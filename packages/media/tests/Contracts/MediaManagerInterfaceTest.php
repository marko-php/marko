<?php

declare(strict_types=1);

namespace Marko\Media\Tests\Contracts;

use Marko\Media\Contracts\MediaManagerInterface;
use Marko\Media\Entity\Media;
use Marko\Media\Value\UploadedFile;
use ReflectionClass;
use ReflectionNamedType;

it('defines MediaManagerInterface with upload, retrieve, delete, and exists methods', function (): void {
    $reflection = new ReflectionClass(MediaManagerInterface::class);

    expect($reflection->isInterface())->toBeTrue();

    // upload method
    $upload = $reflection->getMethod('upload');
    $uploadParam = $upload->getParameters()[0];
    $uploadParamType = $uploadParam->getType();
    $uploadReturn = $upload->getReturnType();

    expect($reflection->hasMethod('upload'))->toBeTrue()
        ->and($upload->getParameters())->toHaveCount(1)
        ->and($uploadParam->getName())->toBe('file')
        ->and($uploadParamType)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($uploadParamType->getName())->toBe(UploadedFile::class)
        ->and($uploadReturn)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($uploadReturn->getName())->toBe(Media::class);

    // retrieve method
    $retrieve = $reflection->getMethod('retrieve');
    $retrieveParam = $retrieve->getParameters()[0];
    $retrieveParamType = $retrieveParam->getType();
    $retrieveReturn = $retrieve->getReturnType();

    expect($reflection->hasMethod('retrieve'))->toBeTrue()
        ->and($retrieve->getParameters())->toHaveCount(1)
        ->and($retrieveParam->getName())->toBe('media')
        ->and($retrieveParamType)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($retrieveParamType->getName())->toBe(Media::class)
        ->and($retrieveReturn)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($retrieveReturn->getName())->toBe('string');

    // delete method
    $delete = $reflection->getMethod('delete');
    $deleteParam = $delete->getParameters()[0];
    $deleteReturn = $delete->getReturnType();

    expect($reflection->hasMethod('delete'))->toBeTrue()
        ->and($delete->getParameters())->toHaveCount(1)
        ->and($deleteParam->getName())->toBe('media')
        ->and($deleteReturn)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($deleteReturn->getName())->toBe('void');

    // exists method
    $exists = $reflection->getMethod('exists');
    $existsParam = $exists->getParameters()[0];
    $existsReturn = $exists->getReturnType();

    expect($reflection->hasMethod('exists'))->toBeTrue()
        ->and($exists->getParameters())->toHaveCount(1)
        ->and($existsParam->getName())->toBe('media')
        ->and($existsReturn)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($existsReturn->getName())->toBe('bool');
});
