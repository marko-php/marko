<?php

declare(strict_types=1);

namespace Marko\Media\Tests\Contracts;

use Marko\Media\Contracts\UrlGeneratorInterface;
use Marko\Media\Entity\Media;
use ReflectionClass;
use ReflectionNamedType;

it('defines UrlGeneratorInterface for generating public URLs for media', function (): void {
    $reflection = new ReflectionClass(UrlGeneratorInterface::class);

    expect($reflection->isInterface())->toBeTrue();

    // url method
    $url = $reflection->getMethod('url');
    $urlParams = $url->getParameters();
    $urlParamType = $urlParams[0]->getType();
    $urlReturn = $url->getReturnType();

    expect($reflection->hasMethod('url'))->toBeTrue()
        ->and($urlParams)->toHaveCount(1)
        ->and($urlParams[0]->getName())->toBe('media')
        ->and($urlParamType)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($urlParamType->getName())->toBe(Media::class)
        ->and($urlReturn)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($urlReturn->getName())->toBe('string');
});
