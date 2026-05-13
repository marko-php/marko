<?php

declare(strict_types=1);

use Marko\Scope\Registry\ScopeRegistryInterface;

it('defines ScopeRegistryInterface with hasAxis, getAxis, listAxes, getHierarchy methods', function (): void {
    $reflection = new ReflectionClass(ScopeRegistryInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('hasAxis'))->toBeTrue()
        ->and($reflection->hasMethod('getAxis'))->toBeTrue()
        ->and($reflection->hasMethod('listAxes'))->toBeTrue()
        ->and($reflection->hasMethod('getHierarchy'))->toBeTrue();
});
