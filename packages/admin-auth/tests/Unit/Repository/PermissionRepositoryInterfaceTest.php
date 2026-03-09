<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Repository;

use Marko\AdminAuth\Contracts\PermissionRegistryInterface;
use Marko\AdminAuth\Entity\Permission;
use Marko\AdminAuth\Repository\PermissionRepositoryInterface;
use Marko\Database\Repository\RepositoryInterface;
use ReflectionClass;

it('extends RepositoryInterface with permission-specific methods', function (): void {
    $reflection = new ReflectionClass(PermissionRepositoryInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->implementsInterface(RepositoryInterface::class))->toBeTrue();

    // Check methods unique to PermissionRepositoryInterface (not inherited)
    $expectedMethods = [
        'findByKey',
        'findByGroup',
        'syncFromRegistry',
    ];

    foreach ($expectedMethods as $method) {
        $methodReflection = $reflection->getMethod($method);
        expect($reflection->hasMethod($method))->toBeTrue(
            "PermissionRepositoryInterface should have method: $method",
        )
            ->and($methodReflection->isPublic())->toBeTrue();
    }

    // Verify inherited methods from RepositoryInterface are available
    $inheritedMethods = ['find', 'findAll', 'findBy', 'findOneBy', 'save', 'delete'];
    foreach ($inheritedMethods as $method) {
        expect($reflection->hasMethod($method))->toBeTrue(
            "PermissionRepositoryInterface should inherit method: $method",
        );
    }
});

it('findByKey method signature requires string and returns nullable Permission', function (): void {
    $reflection = new ReflectionClass(PermissionRepositoryInterface::class);
    $method = $reflection->getMethod('findByKey');

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('key')
        ->and($parameters[0]->getType()->getName())->toBe('string');

    $returnType = $method->getReturnType();
    expect($returnType->allowsNull())->toBeTrue()
        ->and($returnType->getName())->toBe(Permission::class);
});

it('findByGroup method signature requires string and returns array', function (): void {
    $reflection = new ReflectionClass(PermissionRepositoryInterface::class);
    $method = $reflection->getMethod('findByGroup');

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('group')
        ->and($parameters[0]->getType()->getName())->toBe('string');

    $returnType = $method->getReturnType();
    expect($returnType->getName())->toBe('array');
});

it('syncFromRegistry method signature returns void', function (): void {
    $reflection = new ReflectionClass(PermissionRepositoryInterface::class);
    $method = $reflection->getMethod('syncFromRegistry');

    $returnType = $method->getReturnType();
    expect($returnType->getName())->toBe('void');
});

it('updates PermissionRepositoryInterface to accept registry parameter', function (): void {
    $reflection = new ReflectionClass(PermissionRepositoryInterface::class);
    $method = $reflection->getMethod('syncFromRegistry');

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1)
        ->and($parameters[0]->getName())->toBe('registry')
        ->and($parameters[0]->getType()->getName())->toBe(PermissionRegistryInterface::class);
});
