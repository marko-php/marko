<?php

declare(strict_types=1);

use Marko\Auth\Contracts\PasswordHasherInterface;
use Marko\Auth\Hashing\BcryptPasswordHasher;

it('creates PasswordHasherInterface with hash method', function () {
    $interface = new ReflectionClass(PasswordHasherInterface::class);

    expect($interface->isInterface())->toBeTrue()
        ->and($interface->hasMethod('hash'))->toBeTrue();

    $method = $interface->getMethod('hash');
    expect($method->getNumberOfRequiredParameters())->toBe(1);

    $param = $method->getParameters()[0];
    expect($param->getName())->toBe('password')
        ->and($param->getType()->getName())->toBe('string');

    expect($method->getReturnType()->getName())->toBe('string');
});

it('creates PasswordHasherInterface with verify method', function () {
    $interface = new ReflectionClass(PasswordHasherInterface::class);

    expect($interface->hasMethod('verify'))->toBeTrue();

    $method = $interface->getMethod('verify');
    expect($method->getNumberOfRequiredParameters())->toBe(2);

    $params = $method->getParameters();
    expect($params[0]->getName())->toBe('password')
        ->and($params[0]->getType()->getName())->toBe('string')
        ->and($params[1]->getName())->toBe('hash')
        ->and($params[1]->getType()->getName())->toBe('string');

    expect($method->getReturnType()->getName())->toBe('bool');
});

it('creates PasswordHasherInterface with needsRehash method', function () {
    $interface = new ReflectionClass(PasswordHasherInterface::class);

    expect($interface->hasMethod('needsRehash'))->toBeTrue();

    $method = $interface->getMethod('needsRehash');
    expect($method->getNumberOfRequiredParameters())->toBe(1);

    $param = $method->getParameters()[0];
    expect($param->getName())->toBe('hash')
        ->and($param->getType()->getName())->toBe('string');

    expect($method->getReturnType()->getName())->toBe('bool');
});

it('creates BcryptPasswordHasher implementing interface', function () {
    $hasher = new BcryptPasswordHasher();

    expect($hasher)->toBeInstanceOf(PasswordHasherInterface::class);
});

it('hashes password with bcrypt algorithm', function () {
    $hasher = new BcryptPasswordHasher();

    $hash = $hasher->hash('secret');

    expect($hash)->toStartWith('$2y$')
        ->and(strlen($hash))->toBe(60);
});

it('verifies correct password returns true', function () {
    $hasher = new BcryptPasswordHasher();

    $hash = $hasher->hash('secret');

    expect($hasher->verify('secret', $hash))->toBeTrue();
});

it('verifies incorrect password returns false', function () {
    $hasher = new BcryptPasswordHasher();

    $hash = $hasher->hash('secret');

    expect($hasher->verify('wrong-password', $hash))->toBeFalse();
});

it('detects when rehash is needed', function () {
    $lowCostHasher = new BcryptPasswordHasher(cost: 4);
    $highCostHasher = new BcryptPasswordHasher(cost: 10);

    $hash = $lowCostHasher->hash('secret');

    expect($highCostHasher->needsRehash($hash))->toBeTrue();
});

it('supports configurable cost parameter', function () {
    $hasher = new BcryptPasswordHasher(cost: 10);

    $hash = $hasher->hash('secret');

    expect($hash)->toStartWith('$2y$10$');
});

it('uses default cost of 12', function () {
    $hasher = new BcryptPasswordHasher();

    $hash = $hasher->hash('secret');

    expect($hash)->toStartWith('$2y$12$');
});
