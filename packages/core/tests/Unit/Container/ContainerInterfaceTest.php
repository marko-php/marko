<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;

interface ContainerInterfaceTestServiceInterface {}

class ContainerInterfaceTestService implements ContainerInterfaceTestServiceInterface {}

class ContainerInterfaceTestOtherService {}

it('declares resolved instances on the container contract', function (): void {
    $reflection = new ReflectionClass(ContainerInterface::class);

    expect($reflection->hasMethod('resolvedInstances'))->toBeTrue();

    $method = $reflection->getMethod('resolvedInstances');
    $parameter = $method->getParameters()[0];

    expect($parameter->getName())->toBe('interface')
        ->and($parameter->allowsNull())->toBeTrue()
        ->and($parameter->isDefaultValueAvailable())->toBeTrue()
        ->and($parameter->getDefaultValue())->toBeNull();
});

it('resolves already built instances through the interface type', function (): void {
    $container = new Container();
    $container->singleton(ContainerInterfaceTestService::class);
    $instance = $container->get(ContainerInterfaceTestService::class);

    $typed = $container;
    assert($typed instanceof ContainerInterface);

    $resolved = $typed->resolvedInstances();

    expect($resolved)->toHaveKey(ContainerInterfaceTestService::class)
        ->and($resolved[ContainerInterfaceTestService::class])->toBe($instance);
});

it('filters resolved instances by interface through the interface type', function (): void {
    $container = new Container();
    $container->singleton(ContainerInterfaceTestService::class);
    $container->singleton(ContainerInterfaceTestOtherService::class);
    $service = $container->get(ContainerInterfaceTestService::class);
    $container->get(ContainerInterfaceTestOtherService::class);

    $typed = $container;
    assert($typed instanceof ContainerInterface);

    $filtered = $typed->resolvedInstances(ContainerInterfaceTestServiceInterface::class);

    expect($filtered)->toHaveCount(1)
        ->and($filtered[ContainerInterfaceTestService::class])->toBe($service);
});

it('documents that the accessor never forces instantiation', function (): void {
    $method = (new ReflectionClass(ContainerInterface::class))->getMethod('resolvedInstances');
    $methodDoc = $method->getDocComment();

    expect($methodDoc)
        ->not->toBeFalse()
        ->and($methodDoc)
        ->toContain('Never')
        ->and($methodDoc)
        ->toContain('instantiation');
});
