<?php

declare(strict_types=1);

namespace Marko\Media\Tests\Contracts;

use Marko\Media\Contracts\MediaRepositoryInterface;
use ReflectionClass;
use ReflectionNamedType;

it('defines findMany on MediaRepositoryInterface returning an array of Media', function (): void {
    $reflection = new ReflectionClass(MediaRepositoryInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('findMany'))->toBeTrue();

    $method = $reflection->getMethod('findMany');
    $params = $method->getParameters();
    $returnType = $method->getReturnType();

    expect($params)->toHaveCount(1)
        ->and($params[0]->getName())->toBe('ids')
        ->and($returnType)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($returnType->getName())->toBe('array');
});
