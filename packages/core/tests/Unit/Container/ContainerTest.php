<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Exceptions\BindingException;
use Psr\Container\ContainerInterface as PsrContainerInterface;

// Test fixtures - classes used for testing
class SimpleClass {}

class DependencyClass {}

class ClassWithDependency
{
    public function __construct(
        public readonly DependencyClass $dependency,
    ) {}
}

class DeepDependency {}

class MiddleDependency
{
    public function __construct(
        public readonly DeepDependency $deep,
    ) {}
}

class ClassWithNestedDependencies
{
    public function __construct(
        public readonly MiddleDependency $middle,
        public readonly SimpleClass $simple,
    ) {}
}

interface UnboundInterface {}

class ClassWithInterfaceDependency
{
    public function __construct(
        public readonly UnboundInterface $dependency,
    ) {}
}

it('resolves a class with no constructor dependencies', function (): void {
    $container = new Container();

    $instance = $container->get(SimpleClass::class);

    expect($instance)->toBeInstanceOf(SimpleClass::class);
});

it('resolves a class with concrete class dependencies via autowiring', function (): void {
    $container = new Container();

    $instance = $container->get(ClassWithDependency::class);

    expect($instance)
        ->toBeInstanceOf(ClassWithDependency::class)
        ->and($instance->dependency)
        ->toBeInstanceOf(DependencyClass::class);
});

it('resolves nested dependencies recursively', function (): void {
    $container = new Container();

    $instance = $container->get(ClassWithNestedDependencies::class);

    expect($instance)
        ->toBeInstanceOf(ClassWithNestedDependencies::class)
        ->and($instance->middle)
        ->toBeInstanceOf(MiddleDependency::class)
        ->and($instance->middle->deep)
        ->toBeInstanceOf(DeepDependency::class)
        ->and($instance->simple)
        ->toBeInstanceOf(SimpleClass::class);
});

it('throws BindingException when dependency cannot be resolved', function (): void {
    $container = new Container();

    expect(fn () => $container->get(ClassWithInterfaceDependency::class))
        ->toThrow(BindingException::class);
});

it('returns same instance for shared bindings (singleton behavior)', function (): void {
    $container = new Container();
    $container->singleton(SimpleClass::class);

    $instance1 = $container->get(SimpleClass::class);
    $instance2 = $container->get(SimpleClass::class);

    expect($instance1)->toBe($instance2);
});

it('creates new instance for non-shared bindings', function (): void {
    $container = new Container();

    $instance1 = $container->get(SimpleClass::class);
    $instance2 = $container->get(SimpleClass::class);

    expect($instance1)->not->toBe($instance2);
});

it('implements PSR-11 ContainerInterface', function (): void {
    $container = new Container();

    expect($container)->toBeInstanceOf(PsrContainerInterface::class);
});

it('returns true from has() for resolvable classes', function (): void {
    $container = new Container();

    expect($container->has(SimpleClass::class))->toBeTrue()
        ->and($container->has(ClassWithDependency::class))->toBeTrue()
        ->and($container->has(ClassWithNestedDependencies::class))->toBeTrue();
});

it('returns false from has() for non-resolvable interfaces without binding', function (): void {
    $container = new Container();

    expect($container->has(UnboundInterface::class))->toBeFalse()
        ->and($container->has('NonExistentClass'))->toBeFalse();
});
