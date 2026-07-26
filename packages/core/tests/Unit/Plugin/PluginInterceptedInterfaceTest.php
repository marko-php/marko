<?php

declare(strict_types=1);

namespace Marko\Core\Tests\Unit\Plugin;

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Core\Plugin\PluginInterception;
use Marko\Core\Plugin\PluginRegistry;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

it('declares initInterception on the interface, not only on the trait', function (): void {
    $reflection = new ReflectionClass(PluginInterceptedInterface::class);

    expect($reflection->hasMethod('initInterception'))->toBeTrue(
        'PluginInterceptor calls initInterception() through this interface, so the interface must declare it',
    );
});

it('declares initInterception with the signature the trait implements', function (): void {
    $interface = (new ReflectionClass(PluginInterceptedInterface::class))->getMethod('initInterception');
    $trait = (new ReflectionClass(PluginInterception::class))->getMethod('initInterception');

    $typeName = function (ReflectionParameter $p): string {
        $type = $p->getType();

        return $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;
    };

    expect(array_map($typeName, $interface->getParameters()))
        ->toBe(array_map($typeName, $trait->getParameters()))
        ->and(array_map($typeName, $interface->getParameters()))
        ->toBe(['object', 'string', ContainerInterface::class, PluginRegistry::class]);
});

it('is satisfied by the trait every interceptor uses', function (): void {
    $interceptor = new class () implements PluginInterceptedInterface
    {
        use PluginInterception;
    };

    expect($interceptor)->toBeInstanceOf(PluginInterceptedInterface::class);
});
