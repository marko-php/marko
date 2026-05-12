<?php

declare(strict_types=1);

use Marko\PageCache\Contracts\IdentityInterface;

it('defines IdentityInterface in the Marko PageCache Contracts namespace', function (): void {
    expect(interface_exists(IdentityInterface::class))->toBeTrue()
        ->and((new ReflectionClass(IdentityInterface::class))->getNamespaceName())
        ->toBe('Marko\PageCache\Contracts');
});

it('declares a getIdentities method that returns an array of strings', function (): void {
    $method = new ReflectionMethod(IdentityInterface::class, 'getIdentities');

    expect($method->getReturnType()->getName())->toBe('array')
        ->and($method->getReturnType()->allowsNull())->toBeFalse()
        ->and($method->getParameters())->toBeEmpty();
});

it('allows arbitrary classes to implement IdentityInterface', function (): void {
    $impl = new class () implements IdentityInterface
    {
        public function getIdentities(): array
        {
            return ['tag_1', 'tag_2'];
        }
    };

    expect($impl)->toBeInstanceOf(IdentityInterface::class)
        ->and($impl->getIdentities())->toBe(['tag_1', 'tag_2']);
});
