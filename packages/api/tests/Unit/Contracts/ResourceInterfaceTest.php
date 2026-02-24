<?php

declare(strict_types=1);

use Marko\Api\Contracts\ResourceInterface;
use Marko\Routing\Http\Response;

it('defines ResourceInterface with toArray and toResponse methods', function () {
    expect(interface_exists(ResourceInterface::class))->toBeTrue()
        ->and(method_exists(ResourceInterface::class, 'toArray'))->toBeTrue()
        ->and(method_exists(ResourceInterface::class, 'toResponse'))->toBeTrue();
});

it('requires toArray to return an array', function () {
    $reflection = new ReflectionMethod(ResourceInterface::class, 'toArray');

    expect($reflection->getReturnType()?->getName())->toBe('array');
});

it('requires toResponse to return a Response', function () {
    $reflection = new ReflectionMethod(ResourceInterface::class, 'toResponse');

    expect($reflection->getReturnType()?->getName())->toBe(Response::class);
});
