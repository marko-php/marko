<?php

declare(strict_types=1);

use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

it('ViewInterface has render method returning Response', function () {
    expect(interface_exists(ViewInterface::class))->toBeTrue()
        ->and(method_exists(ViewInterface::class, 'render'))->toBeTrue();

    $reflection = new ReflectionMethod(ViewInterface::class, 'render');

    expect($reflection->isPublic())->toBeTrue();

    $parameters = $reflection->getParameters();
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('template')
        ->and($parameters[0]->getType()?->getName())->toBe('string')
        ->and($parameters[1]->getName())->toBe('data')
        ->and($parameters[1]->getType()?->getName())->toBe('array')
        ->and($parameters[1]->isDefaultValueAvailable())->toBeTrue()
        ->and($parameters[1]->getDefaultValue())->toBe([]);

    $returnType = $reflection->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe(Response::class);
});

it('ViewInterface has renderToString method returning string', function () {
    expect(method_exists(ViewInterface::class, 'renderToString'))->toBeTrue();

    $reflection = new ReflectionMethod(ViewInterface::class, 'renderToString');

    expect($reflection->isPublic())->toBeTrue();

    $parameters = $reflection->getParameters();
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('template')
        ->and($parameters[0]->getType()?->getName())->toBe('string')
        ->and($parameters[1]->getName())->toBe('data')
        ->and($parameters[1]->getType()?->getName())->toBe('array')
        ->and($parameters[1]->isDefaultValueAvailable())->toBeTrue()
        ->and($parameters[1]->getDefaultValue())->toBe([]);

    $returnType = $reflection->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('string');
});
