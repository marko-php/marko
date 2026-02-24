<?php

declare(strict_types=1);

use Marko\Encryption\Contracts\EncryptorInterface;

describe('EncryptorInterface', function (): void {
    it('defines encrypt and decrypt methods', function (): void {
        $reflection = new ReflectionClass(EncryptorInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('encrypt'))->toBeTrue()
            ->and($reflection->hasMethod('decrypt'))->toBeTrue();
    });

    it('encrypt method accepts string and returns string', function (): void {
        $method = new ReflectionMethod(EncryptorInterface::class, 'encrypt');
        $params = $method->getParameters();

        expect($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('value')
            ->and($params[0]->getType()->getName())->toBe('string')
            ->and($method->getReturnType()->getName())->toBe('string');
    });

    it('decrypt method accepts string and returns string', function (): void {
        $method = new ReflectionMethod(EncryptorInterface::class, 'decrypt');
        $params = $method->getParameters();

        expect($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('encrypted')
            ->and($params[0]->getType()->getName())->toBe('string')
            ->and($method->getReturnType()->getName())->toBe('string');
    });
});
