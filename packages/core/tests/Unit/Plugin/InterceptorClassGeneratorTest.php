<?php

declare(strict_types=1);

use Marko\Core\Exceptions\PluginException;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Core\Plugin\PluginRegistry;

// Test interfaces and classes
interface SampleInterface
{
    public function doSomething(): string;
}

interface ExtendedInterface extends SampleInterface
{
    public function doMore(): int;
}

interface NoParamInterface
{
    public function noParams(): string;
}

interface TypedParamInterface
{
    public function withTyped(
        string $name,
        int $count,
    ): string;
}

interface NullableParamInterface
{
    public function withNullable(?string $name): string;
}

interface DefaultParamInterface
{
    public function withDefault(string $name = 'default'): string;
}

interface VariadicParamInterface
{
    public function withVariadic(string ...$items): string;
}

interface UnionReturnInterface
{
    public function withUnionReturn(): string|int;
}

interface VoidReturnInterface
{
    public function withVoidReturn(): void;
}

class ConcreteSampleClass
{
    public function pluggedMethod(): string
    {
        return 'original';
    }

    public function unpluggedMethod(): string
    {
        return 'unplugged';
    }
}

readonly class ReadonlySampleClass
{
    public function __construct(
        public string $value = 'test',
    ) {}

    public function getValue(): string
    {
        return $this->value;
    }
}

// Helper to build a PluginRegistry with a plugin for ConcreteSampleClass::pluggedMethod
function makeRegistryWithPlugin(string $targetClass, string $targetMethod): PluginRegistry
{
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: 'FakePlugin',
        targetClass: $targetClass,
        beforeMethods: [$targetMethod => ['pluginMethod' => 'before' . ucfirst($targetMethod), 'sortOrder' => 10]],
    ));

    return $registry;
}

// ─────────────────────────────────────────────────────────────
// Interface Wrapper Strategy
// ─────────────────────────────────────────────────────────────

it('generates a class that implements both the target interface and PluginInterceptedInterface', function (): void {
    $generator = new InterceptorClassGenerator();
    $registry = new PluginRegistry();

    $className = $generator->generateInterfaceWrapper(SampleInterface::class, $registry);

    $reflection = new ReflectionClass($className);
    expect($reflection->implementsInterface(SampleInterface::class))->toBeTrue()
        ->and($reflection->implementsInterface(PluginInterceptedInterface::class))->toBeTrue();
});

it('generates methods for all interface methods with correct signatures', function (): void {
    $generator = new InterceptorClassGenerator();
    $registry = new PluginRegistry();

    $className = $generator->generateInterfaceWrapper(SampleInterface::class, $registry);

    $reflection = new ReflectionClass($className);
    expect($reflection->hasMethod('doSomething'))->toBeTrue();
});

it('generates a class initialized via initInterception rather than a generated constructor', function (): void {
    $generator = new InterceptorClassGenerator();
    $registry = new PluginRegistry();

    $code = $generator->generateInterfaceWrapperCode(SampleInterface::class, $registry);

    // No generated constructor — PluginInterceptor calls initInterception() post-construction
    expect($code)->not->toContain('public function __construct')
        ->and($code)->toContain('PluginInterception');
});

it('generates method bodies that delegate to interceptCall', function (): void {
    $generator = new InterceptorClassGenerator();
    $registry = new PluginRegistry();

    $code = $generator->generateInterfaceWrapperCode(SampleInterface::class, $registry);

    expect($code)->toContain('interceptCall');
});

it('handles methods with no parameters', function (): void {
    $generator = new InterceptorClassGenerator();
    $registry = new PluginRegistry();

    $className = $generator->generateInterfaceWrapper(NoParamInterface::class, $registry);

    $reflection = new ReflectionClass($className);
    $method = $reflection->getMethod('noParams');
    expect($method->getNumberOfParameters())->toBe(0);
});

it('handles methods with typed parameters', function (): void {
    $generator = new InterceptorClassGenerator();
    $registry = new PluginRegistry();

    $className = $generator->generateInterfaceWrapper(TypedParamInterface::class, $registry);

    $reflection = new ReflectionClass($className);
    $method = $reflection->getMethod('withTyped');
    $params = $method->getParameters();
    expect($params)->toHaveCount(2)
        ->and((string) $params[0]->getType())->toBe('string')
        ->and((string) $params[1]->getType())->toBe('int');
});

it('handles methods with nullable parameter types', function (): void {
    $generator = new InterceptorClassGenerator();
    $registry = new PluginRegistry();

    $className = $generator->generateInterfaceWrapper(NullableParamInterface::class, $registry);

    $reflection = new ReflectionClass($className);
    $method = $reflection->getMethod('withNullable');
    $params = $method->getParameters();
    expect($params[0]->allowsNull())->toBeTrue();
});

it('handles methods with default parameter values', function (): void {
    $generator = new InterceptorClassGenerator();
    $registry = new PluginRegistry();

    $className = $generator->generateInterfaceWrapper(DefaultParamInterface::class, $registry);

    $reflection = new ReflectionClass($className);
    $method = $reflection->getMethod('withDefault');
    $params = $method->getParameters();
    expect($params[0]->isOptional())->toBeTrue()
        ->and($params[0]->getDefaultValue())->toBe('default');
});

it('handles methods with variadic parameters', function (): void {
    $generator = new InterceptorClassGenerator();
    $registry = new PluginRegistry();

    $className = $generator->generateInterfaceWrapper(VariadicParamInterface::class, $registry);

    $reflection = new ReflectionClass($className);
    $method = $reflection->getMethod('withVariadic');
    $params = $method->getParameters();
    expect($params[0]->isVariadic())->toBeTrue();
});

it('handles methods with union return types', function (): void {
    $generator = new InterceptorClassGenerator();
    $registry = new PluginRegistry();

    $code = $generator->generateInterfaceWrapperCode(UnionReturnInterface::class, $registry);

    expect(str_contains($code, 'string|int') || str_contains($code, 'int|string'))->toBeTrue();
});

it('handles methods with void return type', function (): void {
    $generator = new InterceptorClassGenerator();
    $registry = new PluginRegistry();

    $code = $generator->generateInterfaceWrapperCode(VoidReturnInterface::class, $registry);

    // void methods should call interceptCall without return
    expect($code)->toContain('void')
        ->and($code)->not->toContain('return $this->interceptCall');
});

it('handles interfaces that extend other interfaces', function (): void {
    $generator = new InterceptorClassGenerator();
    $registry = new PluginRegistry();

    $className = $generator->generateInterfaceWrapper(ExtendedInterface::class, $registry);

    $reflection = new ReflectionClass($className);
    expect($reflection->hasMethod('doSomething'))->toBeTrue()
        ->and($reflection->hasMethod('doMore'))->toBeTrue();
});

it('returns cached class name on second call for same interface', function (): void {
    $generator = new InterceptorClassGenerator();
    $registry = new PluginRegistry();

    $first = $generator->generateInterfaceWrapper(SampleInterface::class, $registry);
    $second = $generator->generateInterfaceWrapper(SampleInterface::class, $registry);

    expect($first)->toBe($second);
});

// ─────────────────────────────────────────────────────────────
// Concrete Subclass Strategy
// ─────────────────────────────────────────────────────────────

it(
    'generates a class that extends the target concrete class and implements PluginInterceptedInterface',
    function (): void {
        $registry = makeRegistryWithPlugin(ConcreteSampleClass::class, 'pluggedMethod');
        $generator = new InterceptorClassGenerator();

        $className = $generator->generateConcreteSubclass(ConcreteSampleClass::class, $registry);

        $reflection = new ReflectionClass($className);
        expect($reflection->isSubclassOf(ConcreteSampleClass::class))->toBeTrue()
            ->and($reflection->implementsInterface(PluginInterceptedInterface::class))->toBeTrue();
    },
);

it('only overrides methods that have registered plugins', function (): void {
    $registry = makeRegistryWithPlugin(ConcreteSampleClass::class, 'pluggedMethod');
    $generator = new InterceptorClassGenerator();

    $className = $generator->generateConcreteSubclass(ConcreteSampleClass::class, $registry);

    $reflection = new ReflectionClass($className);
    // pluggedMethod should be overridden (declared in the generated class)
    expect($reflection->getMethod('pluggedMethod')->getDeclaringClass()->getName())->toBe($className);

    // unpluggedMethod should NOT be overridden (inherited from parent)
    expect($reflection->getMethod('unpluggedMethod')->getDeclaringClass()->getName())
        ->toBe(ConcreteSampleClass::class);
});

it('generates method bodies that delegate to interceptParentCall with parent callable', function (): void {
    $registry = makeRegistryWithPlugin(ConcreteSampleClass::class, 'pluggedMethod');
    $generator = new InterceptorClassGenerator();

    $code = $generator->generateConcreteSubclassCode(ConcreteSampleClass::class, $registry);

    expect($code)->toContain('interceptParentCall');
});

it('does not generate a constructor for concrete subclasses', function (): void {
    $registry = makeRegistryWithPlugin(ConcreteSampleClass::class, 'pluggedMethod');
    $generator = new InterceptorClassGenerator();

    $code = $generator->generateConcreteSubclassCode(ConcreteSampleClass::class, $registry);

    expect($code)->not->toContain('__construct');
});

it('throws PluginException when target class is readonly', function (): void {
    $registry = new PluginRegistry();
    $generator = new InterceptorClassGenerator();

    expect(fn () => $generator->generateConcreteSubclass(ReadonlySampleClass::class, $registry))
        ->toThrow(PluginException::class);
});

// ─────────────────────────────────────────────────────────────
// General
// ─────────────────────────────────────────────────────────────

it('generates classes with the PluginInterception trait', function (): void {
    $generator = new InterceptorClassGenerator();
    $registry = new PluginRegistry();

    $code = $generator->generateInterfaceWrapperCode(SampleInterface::class, $registry);

    expect($code)->toContain('PluginInterception');
});

it('generates valid PHP that passes eval without errors', function (): void {
    $generator = new InterceptorClassGenerator();
    $registry = makeRegistryWithPlugin(ConcreteSampleClass::class, 'pluggedMethod');

    // These calls internally eval the code — if eval fails, an exception is thrown
    $wrapperClass = $generator->generateInterfaceWrapper(SampleInterface::class, new PluginRegistry());
    $subclassClass = $generator->generateConcreteSubclass(ConcreteSampleClass::class, $registry);

    expect(class_exists($wrapperClass))->toBeTrue()
        ->and(class_exists($subclassClass))->toBeTrue();
});
