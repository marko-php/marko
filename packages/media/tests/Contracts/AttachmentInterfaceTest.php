<?php

declare(strict_types=1);

namespace Marko\Media\Tests\Contracts;

use Marko\Media\Contracts\AttachmentInterface;
use Marko\Media\Entity\Media;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

it('defines AttachmentInterface for associating media with entities', function (): void {
    $reflection = new ReflectionClass(AttachmentInterface::class);

    expect($reflection->isInterface())->toBeTrue();

    // attach method
    $attach = $reflection->getMethod('attach');
    $attachParams = $attach->getParameters();
    $attachParam0Type = $attachParams[0]->getType();
    $attachableIdType = $attachParams[2]->getType();
    $attachReturn = $attach->getReturnType();

    expect($reflection->hasMethod('attach'))->toBeTrue()
        ->and($attachParams)->toHaveCount(3)
        ->and($attachParams[0]->getName())->toBe('media')
        ->and($attachParam0Type)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($attachParam0Type->getName())->toBe(Media::class)
        ->and($attachParams[1]->getName())->toBe('attachableType')
        ->and($attachParams[2]->getName())->toBe('attachableId')
        ->and($attachableIdType)->toBeInstanceOf(ReflectionUnionType::class)
        ->and($attachReturn)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($attachReturn->getName())->toBe('void');

    // detach method
    $detach = $reflection->getMethod('detach');
    $detachParams = $detach->getParameters();
    $detachReturn = $detach->getReturnType();

    expect($reflection->hasMethod('detach'))->toBeTrue()
        ->and($detachParams)->toHaveCount(3)
        ->and($detachParams[0]->getName())->toBe('media')
        ->and($detachParams[1]->getName())->toBe('attachableType')
        ->and($detachParams[2]->getName())->toBe('attachableId')
        ->and($detachReturn)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($detachReturn->getName())->toBe('void');

    // findByAttachable method
    $findBy = $reflection->getMethod('findByAttachable');
    $findByParams = $findBy->getParameters();
    $findByReturn = $findBy->getReturnType();

    expect($reflection->hasMethod('findByAttachable'))->toBeTrue()
        ->and($findByParams)->toHaveCount(2)
        ->and($findByParams[0]->getName())->toBe('attachableType')
        ->and($findByParams[1]->getName())->toBe('attachableId')
        ->and($findByReturn)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($findByReturn->getName())->toBe('array');
});
