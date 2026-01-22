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
    $hasher = new BcryptPasswordHasher(cost: 4);

    expect($hasher)->toBeInstanceOf(PasswordHasherInterface::class);
});

it('hashes password with bcrypt algorithm', function () {
    $hasher = new BcryptPasswordHasher(cost: 4);

    $hash = $hasher->hash('secret');

    expect($hash)->toStartWith('$2y$')
        ->and(strlen($hash))->toBe(60);
});

it('verifies correct password returns true', function () {
    $hasher = new BcryptPasswordHasher(cost: 4);

    $hash = $hasher->hash('secret');

    expect($hasher->verify('secret', $hash))->toBeTrue();
});

it('verifies incorrect password returns false', function () {
    $hasher = new BcryptPasswordHasher(cost: 4);

    $hash = $hasher->hash('secret');

    expect($hasher->verify('wrong-password', $hash))->toBeFalse();
});

it('detects when rehash is needed', function () {
    $lowCostHasher = new BcryptPasswordHasher(cost: 4);
    $highCostHasher = new BcryptPasswordHasher(cost: 6);

    $hash = $lowCostHasher->hash('secret');

    expect($highCostHasher->needsRehash($hash))->toBeTrue();
});

it('supports configurable cost parameter', function () {
    $hasher = new BcryptPasswordHasher(cost: 5);

    $hash = $hasher->hash('secret');

    expect($hash)->toStartWith('$2y$05$');
});

it('uses default cost of 12', function () {
    // Verify default cost constant (without slow hashing)
    expect(BcryptPasswordHasher::DEFAULT_COST)->toBe(12);
});

it('hashes password to non-readable format', function () {
    $hasher = new BcryptPasswordHasher(cost: 4);
    $password = 'my-secret-password';

    $hash = $hasher->hash($password);

    expect($hash)->not->toBe($password)
        ->and($hash)->not->toContain($password)
        ->and(strlen($hash))->toBe(60);
});

it('produces different hash for same password', function () {
    $hasher = new BcryptPasswordHasher(cost: 4);
    $password = 'same-password';

    $hash1 = $hasher->hash($password);
    $hash2 = $hasher->hash($password);

    expect($hash1)->not->toBe($hash2)
        ->and($hasher->verify($password, $hash1))->toBeTrue()
        ->and($hasher->verify($password, $hash2))->toBeTrue();
});

it('detects rehash needed for lower cost', function () {
    $lowCostHasher = new BcryptPasswordHasher(cost: 4);
    $higherCostHasher = new BcryptPasswordHasher(cost: 6);

    $hashWithLowCost = $lowCostHasher->hash('secret');

    expect($higherCostHasher->needsRehash($hashWithLowCost))->toBeTrue();
});

it('detects no rehash needed for same cost', function () {
    $hasher = new BcryptPasswordHasher(cost: 4);

    $hash = $hasher->hash('secret');

    expect($hasher->needsRehash($hash))->toBeFalse();
});

it('uses custom cost when provided', function () {
    $hasher = new BcryptPasswordHasher(cost: 5);

    $hash = $hasher->hash('secret');

    expect($hash)->toStartWith('$2y$05$');
});

it('validates minimum cost requirement', function () {
    $hasher = new BcryptPasswordHasher(cost: 3);

    expect(fn () => $hasher->hash('secret'))
        ->toThrow(ValueError::class);
});
