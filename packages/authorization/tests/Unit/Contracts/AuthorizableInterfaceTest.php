<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Unit\Contracts;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authorization\AuthorizableInterface;
use ReflectionClass;

it('defines AuthorizableInterface with getAuthIdentifier method', function (): void {
    $reflection = new ReflectionClass(AuthorizableInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('getAuthIdentifier'))->toBeTrue();
});

it('defines AuthorizableInterface extending AuthenticatableInterface', function (): void {
    $reflection = new ReflectionClass(AuthorizableInterface::class);

    expect($reflection->isSubclassOf(AuthenticatableInterface::class))->toBeTrue();
});

it('defines AuthorizableInterface with getCan method returning Gate access', function (): void {
    $reflection = new ReflectionClass(AuthorizableInterface::class);

    expect($reflection->hasMethod('can'))->toBeTrue();

    $method = $reflection->getMethod('can');

    expect($method->getReturnType()->getName())->toBe('bool')
        ->and($method->getParameters())->toHaveCount(2);

    $abilityParam = $method->getParameters()[0];
    $argumentsParam = $method->getParameters()[1];

    expect($abilityParam->getName())->toBe('ability')
        ->and($abilityParam->getType()->getName())->toBe('string')
        ->and($argumentsParam->getName())->toBe('arguments')
        ->and($argumentsParam->isVariadic())->toBeTrue();
});
