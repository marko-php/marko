<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Repository;

use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Repository\AdminUserRepositoryInterface;
use Marko\Database\Repository\RepositoryInterface;
use ReflectionClass;

it('creates AdminUserRepositoryInterface with find, findByEmail, all, save, delete methods', function (): void {
    $reflection = new ReflectionClass(AdminUserRepositoryInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->implementsInterface(RepositoryInterface::class))->toBeTrue();

    // Check methods unique to AdminUserRepositoryInterface (not inherited)
    $expectedMethods = [
        'findByEmail',
        'getRolesForUser',
        'syncRoles',
    ];

    foreach ($expectedMethods as $method) {
        expect($reflection->hasMethod($method))->toBeTrue(
            "AdminUserRepositoryInterface should have method: $method",
        );

        $methodReflection = $reflection->getMethod($method);
        expect($methodReflection->isPublic())->toBeTrue();
    }

    // Verify inherited methods from RepositoryInterface are available
    $inheritedMethods = ['find', 'findAll', 'findBy', 'findOneBy', 'save', 'delete'];
    foreach ($inheritedMethods as $method) {
        expect($reflection->hasMethod($method))->toBeTrue(
            "AdminUserRepositoryInterface should inherit method: $method",
        );
    }
});

it('findByEmail method signature requires string and returns nullable AdminUser', function (): void {
    $reflection = new ReflectionClass(AdminUserRepositoryInterface::class);
    $method = $reflection->getMethod('findByEmail');

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('email')
        ->and($parameters[0]->getType()->getName())->toBe('string');

    $returnType = $method->getReturnType();
    expect($returnType->allowsNull())->toBeTrue()
        ->and($returnType->getName())->toBe(AdminUser::class);
});

it('getRolesForUser method signature requires int and returns array', function (): void {
    $reflection = new ReflectionClass(AdminUserRepositoryInterface::class);
    $method = $reflection->getMethod('getRolesForUser');

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('userId')
        ->and($parameters[0]->getType()->getName())->toBe('int');

    $returnType = $method->getReturnType();
    expect($returnType->getName())->toBe('array');
});

it('syncRoles method signature requires userId and roleIds', function (): void {
    $reflection = new ReflectionClass(AdminUserRepositoryInterface::class);
    $method = $reflection->getMethod('syncRoles');

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('userId')
        ->and($parameters[0]->getType()->getName())->toBe('int')
        ->and($parameters[1]->getName())->toBe('roleIds')
        ->and($parameters[1]->getType()->getName())->toBe('array');

    $returnType = $method->getReturnType();
    expect($returnType->getName())->toBe('void');
});
