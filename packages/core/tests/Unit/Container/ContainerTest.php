<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Exceptions\BindingException;
use Psr\Container\ContainerInterface as PsrContainerInterface;

// Test fixtures - classes used for testing
class SimpleClass {}

class DependencyClass {}

readonly class ClassWithDependency
{
    public function __construct(
        public DependencyClass $dependency,
    ) {}
}

class DeepDependency {}

readonly class MiddleDependency
{
    public function __construct(
        public DeepDependency $deep,
    ) {}
}

readonly class ClassWithNestedDependencies
{
    public function __construct(
        public MiddleDependency $middle,
        public SimpleClass $simple,
    ) {}
}

interface UnboundInterface {}

readonly class ClassWithInterfaceDependency
{
    public function __construct(
        public UnboundInterface $dependency,
    ) {}
}

readonly class ClassWithDefaultScalarValue
{
    public function __construct(
        public string $name = 'default',
        public ?int $count = null,
    ) {}
}

readonly class ClassWithMixedDependencies
{
    public function __construct(
        public SimpleClass $simple,
        public string $name = 'default',
    ) {}
}

readonly class ClassWithClosureParameter
{
    public function __construct(
        public SimpleClass $simple,
        public ?Closure $factory = null,
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

        return new readonly class ($dependency) implements UnboundInterface
        {
            public function __construct(public DependencyClass $dep) {}
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

it('calls a closure with no parameters', function (): void {
    $container = new Container();

    $result = $container->call(fn () => 'hello');

    expect($result)->toBe('hello');
});

it('resolves nullable typed parameters to null when not bound in container', function (): void {
    $container = new Container();

    $result = $container->call(fn (?UnboundInterface $dep) => $dep);

    expect($result)->toBeNull();
});

it('returns the callable return value', function (): void {
    $container = new Container();

    $result = $container->call(fn () => 42);

    expect($result)->toBe(42);
});

it('throws BindingException for unresolvable callable parameters', function (): void {
    $container = new Container();

    expect(fn () => $container->call(fn (string $name) => $name))
        ->toThrow(BindingException::class, "Cannot resolve parameter '\$name' in callable");
});

it('uses default values for scalar parameters in callables', function (): void {
    $container = new Container();

    $result = $container->call(fn (string $name = 'world') => "hello $name");

    expect($result)->toBe('hello world');
});

it('resolves multiple parameters from the container', function (): void {
    $container = new Container();

    $result = $container->call(fn (SimpleClass $simple, DependencyClass $dep) => [$simple, $dep]);

    expect($result[0])->toBeInstanceOf(SimpleClass::class)
        ->and($result[1])->toBeInstanceOf(DependencyClass::class);
});

it('resolves typed parameters from the container when calling a closure', function (): void {
    $container = new Container();

    $result = $container->call(fn (SimpleClass $simple) => $simple);

    expect($result)->toBeInstanceOf(SimpleClass::class);
});

it('uses default null for nullable Closure parameters', function (): void {
    $container = new Container();

    $instance = $container->get(ClassWithClosureParameter::class);

    expect($instance)->toBeInstanceOf(ClassWithClosureParameter::class)
        ->and($instance->simple)->toBeInstanceOf(SimpleClass::class)
        ->and($instance->factory)->toBeNull();
});
