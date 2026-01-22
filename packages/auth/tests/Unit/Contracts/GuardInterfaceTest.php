<?php

declare(strict_types=1);

namespace Marko\Auth\Tests\Unit\Contracts;

use Marko\Auth\AuthenticatableInterface;
use Marko\Auth\Contracts\GuardInterface;
use Marko\Auth\Contracts\UserProviderInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

test('it creates GuardInterface with check method returning bool', function (): void {
    expect(interface_exists(GuardInterface::class))->toBeTrue();

    $reflection = new ReflectionClass(GuardInterface::class);
    expect($reflection->isInterface())->toBeTrue();

    expect($reflection->hasMethod('check'))->toBeTrue();

    $method = $reflection->getMethod('check');
    expect($method->getNumberOfParameters())->toBe(0);

    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('bool');
});

test('it creates GuardInterface with guest method returning bool', function (): void {
    $reflection = new ReflectionClass(GuardInterface::class);

    expect($reflection->hasMethod('guest'))->toBeTrue();

    $method = $reflection->getMethod('guest');
    expect($method->getNumberOfParameters())->toBe(0);

    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('bool');
});

test('it creates GuardInterface with user method returning nullable AuthenticatableInterface', function (): void {
    $reflection = new ReflectionClass(GuardInterface::class);

    expect($reflection->hasMethod('user'))->toBeTrue();

    $method = $reflection->getMethod('user');
    expect($method->getNumberOfParameters())->toBe(0);

    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($returnType->allowsNull())->toBeTrue()
        ->and($returnType->getName())->toBe(AuthenticatableInterface::class);
});

test('it creates GuardInterface with id method returning nullable identifier', function (): void {
    $reflection = new ReflectionClass(GuardInterface::class);

    expect($reflection->hasMethod('id'))->toBeTrue();

    $method = $reflection->getMethod('id');
    expect($method->getNumberOfParameters())->toBe(0);

    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType)->toBeInstanceOf(ReflectionUnionType::class);

    // Should be int|string|null
    $types = $returnType->getTypes();
    $typeNames = array_map(fn (ReflectionNamedType $t) => $t->getName(), $types);
    sort($typeNames);
    expect($typeNames)->toBe(['int', 'null', 'string']);
});

test('it creates GuardInterface with attempt method', function (): void {
    $reflection = new ReflectionClass(GuardInterface::class);

    expect($reflection->hasMethod('attempt'))->toBeTrue();

    $method = $reflection->getMethod('attempt');
    $parameters = $method->getParameters();

    // Should have credentials array and optional remember bool
    expect(count($parameters))->toBeGreaterThanOrEqual(1);

    $credentialsParam = $parameters[0];
    expect($credentialsParam->getName())->toBe('credentials')
        ->and($credentialsParam->getType()->getName())->toBe('array');

    // Should return bool
    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('bool');
});

test('it creates GuardInterface with login method', function (): void {
    $reflection = new ReflectionClass(GuardInterface::class);

    expect($reflection->hasMethod('login'))->toBeTrue();

    $method = $reflection->getMethod('login');
    $parameters = $method->getParameters();

    // Should have user parameter
    expect(count($parameters))->toBeGreaterThanOrEqual(1);

    $userParam = $parameters[0];
    expect($userParam->getName())->toBe('user')
        ->and($userParam->getType()->getName())->toBe(AuthenticatableInterface::class);

    // Should return void
    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('void');
});

test('it creates GuardInterface with loginById method', function (): void {
    $reflection = new ReflectionClass(GuardInterface::class);

    expect($reflection->hasMethod('loginById'))->toBeTrue();

    $method = $reflection->getMethod('loginById');
    $parameters = $method->getParameters();

    // Should have id parameter
    expect(count($parameters))->toBeGreaterThanOrEqual(1);

    $idParam = $parameters[0];
    expect($idParam->getName())->toBe('id');

    // Id should be int|string
    $idType = $idParam->getType();
    expect($idType)->toBeInstanceOf(ReflectionUnionType::class);
    $typeNames = array_map(fn (ReflectionNamedType $t) => $t->getName(), $idType->getTypes());
    sort($typeNames);
    expect($typeNames)->toBe(['int', 'string']);

    // Should return nullable AuthenticatableInterface
    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($returnType->allowsNull())->toBeTrue()
        ->and($returnType->getName())->toBe(AuthenticatableInterface::class);
});

test('it creates GuardInterface with logout method', function (): void {
    $reflection = new ReflectionClass(GuardInterface::class);

    expect($reflection->hasMethod('logout'))->toBeTrue();

    $method = $reflection->getMethod('logout');
    expect($method->getNumberOfParameters())->toBe(0);

    // Should return void
    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('void');
});

test('it creates GuardInterface with setProvider method', function (): void {
    $reflection = new ReflectionClass(GuardInterface::class);

    expect($reflection->hasMethod('setProvider'))->toBeTrue();

    $method = $reflection->getMethod('setProvider');
    $parameters = $method->getParameters();

    // Should have provider parameter
    expect(count($parameters))->toBe(1);

    $providerParam = $parameters[0];
    expect($providerParam->getName())->toBe('provider')
        ->and($providerParam->getType()->getName())->toBe(UserProviderInterface::class);

    // Should return void
    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe('void');
});

test('it creates GuardInterface with getName method', function (): void {
    $reflection = new ReflectionClass(GuardInterface::class);

    expect($reflection->hasMethod('getName'))->toBeTrue();

    $method = $reflection->getMethod('getName');
    expect($method->getNumberOfParameters())->toBe(0);

    // Should return string
    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull()
        ->and($returnType)->toBeInstanceOf(ReflectionNamedType::class)
        ->and($returnType->getName())->toBe('string');
});
