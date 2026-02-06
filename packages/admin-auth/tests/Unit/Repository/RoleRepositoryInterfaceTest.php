<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Repository;

use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Repository\RoleRepositoryInterface;
use Marko\Database\Repository\RepositoryInterface;
use ReflectionClass;

it('creates RoleRepositoryInterface with find, findBySlug, all, save, delete methods', function (): void {
    $reflection = new ReflectionClass(RoleRepositoryInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->implementsInterface(RepositoryInterface::class))->toBeTrue();

    // Check methods unique to RoleRepositoryInterface (not inherited)
    $expectedMethods = [
        'findBySlug',
        'getPermissionsForRole',
        'syncPermissions',
        'isSlugUnique',
    ];

    foreach ($expectedMethods as $method) {
        expect($reflection->hasMethod($method))->toBeTrue(
            "RoleRepositoryInterface should have method: $method",
        );

        $methodReflection = $reflection->getMethod($method);
        expect($methodReflection->isPublic())->toBeTrue();
    }

    // Verify inherited methods from RepositoryInterface are available
    $inheritedMethods = ['find', 'findAll', 'findBy', 'findOneBy', 'save', 'delete'];
    foreach ($inheritedMethods as $method) {
        expect($reflection->hasMethod($method))->toBeTrue(
            "RoleRepositoryInterface should inherit method: $method",
        );
    }
});

it('findBySlug method signature requires string and returns nullable Role', function (): void {
    $reflection = new ReflectionClass(RoleRepositoryInterface::class);
    $method = $reflection->getMethod('findBySlug');

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('slug')
        ->and($parameters[0]->getType()->getName())->toBe('string');

    $returnType = $method->getReturnType();
    expect($returnType->allowsNull())->toBeTrue()
        ->and($returnType->getName())->toBe(Role::class);
});

it('getPermissionsForRole method signature requires int and returns array', function (): void {
    $reflection = new ReflectionClass(RoleRepositoryInterface::class);
    $method = $reflection->getMethod('getPermissionsForRole');

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('roleId')
        ->and($parameters[0]->getType()->getName())->toBe('int');

    $returnType = $method->getReturnType();
    expect($returnType->getName())->toBe('array');
});

it('syncPermissions method signature requires roleId and permissionIds', function (): void {
    $reflection = new ReflectionClass(RoleRepositoryInterface::class);
    $method = $reflection->getMethod('syncPermissions');

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('roleId')
        ->and($parameters[0]->getType()->getName())->toBe('int')
        ->and($parameters[1]->getName())->toBe('permissionIds')
        ->and($parameters[1]->getType()->getName())->toBe('array');

    $returnType = $method->getReturnType();
    expect($returnType->getName())->toBe('void');
});

it('isSlugUnique method signature requires slug and optional excludeId', function (): void {
    $reflection = new ReflectionClass(RoleRepositoryInterface::class);
    $method = $reflection->getMethod('isSlugUnique');

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('slug')
        ->and($parameters[0]->getType()->getName())->toBe('string')
        ->and($parameters[1]->getName())->toBe('excludeId')
        ->and($parameters[1]->getType()->allowsNull())->toBeTrue()
        ->and($parameters[1]->isDefaultValueAvailable())->toBeTrue();

    $returnType = $method->getReturnType();
    expect($returnType->getName())->toBe('bool');
});
