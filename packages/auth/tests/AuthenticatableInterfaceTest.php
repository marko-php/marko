<?php

declare(strict_types=1);

use Marko\Auth\AuthenticatableInterface;

it('creates AuthenticatableInterface with getAuthIdentifier method', function () {
    $interface = new ReflectionClass(AuthenticatableInterface::class);

    expect($interface->isInterface())->toBeTrue()
        ->and($interface->hasMethod('getAuthIdentifier'))->toBeTrue();

    $method = $interface->getMethod('getAuthIdentifier');

    expect($method->isPublic())->toBeTrue()
        ->and($method->getNumberOfParameters())->toBe(0)
        ->and((string) $method->getReturnType())->toBeIn(['int|string', 'string|int']);
});

it('creates AuthenticatableInterface with getAuthIdentifierName method', function () {
    $interface = new ReflectionClass(AuthenticatableInterface::class);

    expect($interface->hasMethod('getAuthIdentifierName'))->toBeTrue();

    $method = $interface->getMethod('getAuthIdentifierName');

    expect($method->isPublic())->toBeTrue()
        ->and($method->getNumberOfParameters())->toBe(0)
        ->and((string) $method->getReturnType())->toBe('string');
});

it('creates AuthenticatableInterface with getAuthPassword method', function () {
    $interface = new ReflectionClass(AuthenticatableInterface::class);

    expect($interface->hasMethod('getAuthPassword'))->toBeTrue();

    $method = $interface->getMethod('getAuthPassword');

    expect($method->isPublic())->toBeTrue()
        ->and($method->getNumberOfParameters())->toBe(0)
        ->and((string) $method->getReturnType())->toBe('string');
});

it('creates AuthenticatableInterface with getRememberToken method', function () {
    $interface = new ReflectionClass(AuthenticatableInterface::class);

    expect($interface->hasMethod('getRememberToken'))->toBeTrue();

    $method = $interface->getMethod('getRememberToken');

    expect($method->isPublic())->toBeTrue()
        ->and($method->getNumberOfParameters())->toBe(0)
        ->and((string) $method->getReturnType())->toBe('?string');
});

it('creates AuthenticatableInterface with setRememberToken method', function () {
    $interface = new ReflectionClass(AuthenticatableInterface::class);

    expect($interface->hasMethod('setRememberToken'))->toBeTrue();

    $method = $interface->getMethod('setRememberToken');

    expect($method->isPublic())->toBeTrue()
        ->and($method->getNumberOfParameters())->toBe(1)
        ->and((string) $method->getReturnType())->toBe('void');

    $param = $method->getParameters()[0];

    expect($param->getName())->toBe('token')
        ->and((string) $param->getType())->toBe('?string');
});

it('creates AuthenticatableInterface with getRememberTokenName method', function () {
    $interface = new ReflectionClass(AuthenticatableInterface::class);

    expect($interface->hasMethod('getRememberTokenName'))->toBeTrue();

    $method = $interface->getMethod('getRememberTokenName');

    expect($method->isPublic())->toBeTrue()
        ->and($method->getNumberOfParameters())->toBe(0)
        ->and((string) $method->getReturnType())->toBe('string');
});
