<?php

declare(strict_types=1);

use Marko\View\TemplateResolverInterface;

it('TemplateResolverInterface has resolve method', function () {
    expect(interface_exists(TemplateResolverInterface::class))->toBeTrue()
        ->and(method_exists(TemplateResolverInterface::class, 'resolve'))->toBeTrue();

    $reflection = new ReflectionMethod(TemplateResolverInterface::class, 'resolve');

    expect($reflection->isPublic())->toBeTrue();

    $parameters = $reflection->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('template')
        ->and($parameters[0]->getType()?->getName())->toBe('string');

    $returnType = $reflection->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('string');
});

it('TemplateResolverInterface has getSearchedPaths method', function () {
    expect(method_exists(TemplateResolverInterface::class, 'getSearchedPaths'))->toBeTrue();

    $reflection = new ReflectionMethod(TemplateResolverInterface::class, 'getSearchedPaths');

    expect($reflection->isPublic())->toBeTrue();

    $parameters = $reflection->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('template')
        ->and($parameters[0]->getType()?->getName())->toBe('string');

    $returnType = $reflection->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('array');
});
