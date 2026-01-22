<?php

declare(strict_types=1);

namespace Marko\Auth\Tests\Unit\Contracts;

use Marko\Auth\Contracts\UserProviderInterface;
use ReflectionMethod;

describe('UserProviderInterface', function (): void {
    it('creates UserProviderInterface with retrieveById method', function (): void {
        expect(interface_exists(UserProviderInterface::class))->toBeTrue()
            ->and(method_exists(UserProviderInterface::class, 'retrieveById'))->toBeTrue();
    });

    it('creates UserProviderInterface with retrieveByCredentials method', function (): void {
        expect(method_exists(UserProviderInterface::class, 'retrieveByCredentials'))->toBeTrue();
    });

    it('creates UserProviderInterface with validateCredentials method', function (): void {
        expect(method_exists(UserProviderInterface::class, 'validateCredentials'))->toBeTrue();
    });

    it('creates UserProviderInterface with retrieveByRememberToken method', function (): void {
        expect(method_exists(UserProviderInterface::class, 'retrieveByRememberToken'))->toBeTrue();
    });

    it('creates UserProviderInterface with updateRememberToken method', function (): void {
        expect(method_exists(UserProviderInterface::class, 'updateRememberToken'))->toBeTrue();
    });

    it('retrieveById returns nullable AuthenticatableInterface', function (): void {
        $reflection = new ReflectionMethod(UserProviderInterface::class, 'retrieveById');
        $returnType = $reflection->getReturnType();

        expect($returnType)->not->toBeNull()
            ->and($returnType->allowsNull())->toBeTrue()
            ->and($returnType->getName())->toBe('Marko\Auth\AuthenticatableInterface');
    });

    it('retrieveByCredentials accepts array of credentials', function (): void {
        $reflection = new ReflectionMethod(UserProviderInterface::class, 'retrieveByCredentials');
        $parameters = $reflection->getParameters();

        expect($parameters)->toHaveCount(1)
            ->and($parameters[0]->getName())->toBe('credentials')
            ->and($parameters[0]->getType()->getName())->toBe('array');
    });

    it('validateCredentials takes user and credentials array', function (): void {
        $reflection = new ReflectionMethod(UserProviderInterface::class, 'validateCredentials');
        $parameters = $reflection->getParameters();

        expect($parameters)->toHaveCount(2)
            ->and($parameters[0]->getName())->toBe('user')
            ->and($parameters[0]->getType()->getName())->toBe('Marko\Auth\AuthenticatableInterface')
            ->and($parameters[1]->getName())->toBe('credentials')
            ->and($parameters[1]->getType()->getName())->toBe('array')
            ->and($reflection->getReturnType()->getName())->toBe('bool');
    });
});
