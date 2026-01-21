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

class ClassWithDefaultScalarValue
{
    public function __construct(
        public readonly string $name = 'default',
        public readonly ?int $count = null,
    ) {}
}

class ClassWithMixedDependencies
{
    public function __construct(
        public readonly SimpleClass $simple,
        public readonly string $name = 'default',
    ) {}
}

class ClassWithClosureParameter
{
    public function __construct(
        public readonly SimpleClass $simple,
        public readonly ?Closure $factory = null,
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

it('resolves closure bindings by calling the closure with container', function (): void {
    $container = new Container();
    $container->bind(UnboundInterface::class, fn (Container $c) => new class () implements UnboundInterface {});

    $instance = $container->get(UnboundInterface::class);

    expect($instance)->toBeInstanceOf(UnboundInterface::class);
});

it('passes container to closure bindings for dependency resolution', function (): void {
    $container = new Container();
    $container->bind(UnboundInterface::class, function (Container $c) {
        $dependency = $c->get(DependencyClass::class);

        return new class ($dependency) implements UnboundInterface
        {
            public function __construct(public readonly DependencyClass $dep) {}
        };
    });

    $instance = $container->get(UnboundInterface::class);

    expect($instance)->toBeInstanceOf(UnboundInterface::class)
        ->and($instance->dep)->toBeInstanceOf(DependencyClass::class);
});

it('caches closure binding result when marked as singleton', function (): void {
    $container = new Container();
    $container->singleton(UnboundInterface::class);
    $container->bind(UnboundInterface::class, fn (Container $c) => new class () implements UnboundInterface {});

    $instance1 = $container->get(UnboundInterface::class);
    $instance2 = $container->get(UnboundInterface::class);

    expect($instance1)->toBe($instance2);
});

it('uses default values for scalar parameters', function (): void {
    $container = new Container();

    $instance = $container->get(ClassWithDefaultScalarValue::class);

    expect($instance)->toBeInstanceOf(ClassWithDefaultScalarValue::class)
        ->and($instance->name)->toBe('default')
        ->and($instance->count)->toBeNull();
});

it('resolves class dependencies while using default scalar values', function (): void {
    $container = new Container();

    $instance = $container->get(ClassWithMixedDependencies::class);

    expect($instance)->toBeInstanceOf(ClassWithMixedDependencies::class)
        ->and($instance->simple)->toBeInstanceOf(SimpleClass::class)
        ->and($instance->name)->toBe('default');
});

it('uses default null for nullable Closure parameters', function (): void {
    $container = new Container();

    $instance = $container->get(ClassWithClosureParameter::class);

    expect($instance)->toBeInstanceOf(ClassWithClosureParameter::class)
        ->and($instance->simple)->toBeInstanceOf(SimpleClass::class)
        ->and($instance->factory)->toBeNull();
});
