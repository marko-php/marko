<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
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

final class FinalSampleClass
{
    public function getValue(): string
    {
        return 'final';
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

it('throws PluginException instead of a php fatal when target class is final', function (): void {
    $registry = new PluginRegistry();
    $generator = new InterceptorClassGenerator();

    expect(fn () => $generator->generateConcreteSubclass(FinalSampleClass::class, $registry))
        ->toThrow(
            PluginException::class,
            "Cannot create interceptor for 'FinalSampleClass': final classes cannot be extended",
        );
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

// ─────────────────────────────────────────────────────────────
// Default Value Rendering
// ─────────────────────────────────────────────────────────────

enum DefaultValueStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

class DefaultValueObject {}

class DefaultValueSampleClass
{
    public const string DEFAULT_ROLE = 'editor';

    private const string SECRET_DEFAULT = 'hidden';

    /**
     * @param array<string> $allow
     * @return array<string>
     */
    public function withArray(
        array $allow = ['admin'],
    ): array {
        return $allow;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function withNestedArray(
        array $options = ['strict' => true, 'roles' => ['admin', 'owner'], 'limit' => 5],
    ): array {
        return $options;
    }

    public function withQuotedString(
        string $value = 'a"b',
    ): string {
        return $value;
    }

    public function withEscapedString(
        string $value = "back\\slash'quote\0nul",
    ): string {
        return $value;
    }

    public function withFloat(
        float $ratio = 0.1,
        float $whole = 2.0,
    ): float {
        return $ratio + $whole;
    }

    public function withEnum(
        DefaultValueStatus $status = DefaultValueStatus::Inactive,
    ): DefaultValueStatus {
        return $status;
    }

    /**
     * @param array<DefaultValueStatus> $statuses
     * @return array<DefaultValueStatus>
     */
    public function withEnumArray(
        array $statuses = [DefaultValueStatus::Active, DefaultValueStatus::Inactive],
    ): array {
        return $statuses;
    }

    public function withSelfConstant(
        string $role = self::DEFAULT_ROLE,
    ): string {
        return $role;
    }

    public function withPrivateConstant(
        string $value = self::SECRET_DEFAULT,
    ): string {
        return $value;
    }

    public function withGlobalConstant(
        int $level = PHP_INT_MAX,
        string $eol = PHP_EOL,
    ): string {
        return $level . $eol;
    }

    public function withScalars(
        ?string $nothing = null,
        bool $yes = true,
        bool $no = false,
        int $negative = -5,
    ): string {
        return var_export([$nothing, $yes, $no, $negative], true);
    }
}

class DefaultValueObjectClass
{
    public function withObject(
        DefaultValueObject $object = new DefaultValueObject(),
    ): DefaultValueObject {
        return $object;
    }
}

class DefaultValueObjectInArrayClass
{
    /**
     * @param array<DefaultValueObject> $objects
     * @return array<DefaultValueObject>
     */
    public function withObjects(
        array $objects = [new DefaultValueObject()],
    ): array {
        return $objects;
    }
}

interface DefaultValueInterface
{
    public const array DEFAULT_ROLES = ['admin', 'owner'];

    /**
     * @param array<string> $roles
     * @return array<string>
     */
    public function withRoles(
        array $roles = self::DEFAULT_ROLES,
        string $quoted = 'it\'s "quoted"',
    ): array;
}

/**
 * Build a plugged subclass of DefaultValueSampleClass (no plugins run) and return a live instance.
 */
function makeDefaultValueInterceptor(ContainerInterface $container): DefaultValueSampleClass
{
    $methods = [
        'withArray',
        'withNestedArray',
        'withQuotedString',
        'withEscapedString',
        'withFloat',
        'withEnum',
        'withEnumArray',
        'withSelfConstant',
        'withPrivateConstant',
        'withGlobalConstant',
        'withScalars',
    ];
    $beforeMethods = [];

    foreach ($methods as $method) {
        $beforeMethods[$method] = ['pluginMethod' => 'before' . ucfirst($method), 'sortOrder' => 10];
    }

    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: 'FakeDefaultValuePlugin',
        targetClass: DefaultValueSampleClass::class,
        beforeMethods: $beforeMethods,
    ));

    $className = new InterceptorClassGenerator()->generateConcreteSubclass(DefaultValueSampleClass::class, $registry);

    /** @var DefaultValueSampleClass&PluginInterceptedInterface $instance */
    $instance = new $className();
    $instance->initInterception(
        pluginTarget: $instance,
        pluginTargetClass: DefaultValueSampleClass::class,
        pluginContainer: $container,
        // Empty registry at call time: no plugins run, so the parent receives exactly what the generated method forwards
        pluginRegistry: new PluginRegistry(),
    );

    return $instance;
}

/**
 * @return array<string, mixed>
 */
function generatedDefaults(
    string $className,
    string $method,
): array {
    $defaults = [];

    foreach (new ReflectionMethod($className, $method)->getParameters() as $param) {
        $defaults[$param->getName()] = $param->getDefaultValue();
    }

    return $defaults;
}

it('preserves non-empty array defaults in generated subclass methods', function (): void {
    $instance = makeDefaultValueInterceptor($this->createStub(ContainerInterface::class));

    expect($instance->withArray())->toBe(['admin'])
        ->and($instance->withNestedArray())->toBe(['strict' => true, 'roles' => ['admin', 'owner'], 'limit' => 5])
        ->and(generatedDefaults($instance::class, 'withArray'))->toBe(['allow' => ['admin']]);
});

it('preserves string defaults containing double quotes', function (): void {
    $instance = makeDefaultValueInterceptor($this->createStub(ContainerInterface::class));

    expect($instance->withQuotedString())->toBe('a"b');
});

it('preserves string defaults containing backslashes, single quotes and NUL bytes', function (): void {
    $instance = makeDefaultValueInterceptor($this->createStub(ContainerInterface::class));

    expect($instance->withEscapedString())->toBe("back\\slash'quote\0nul");
});

it('preserves float defaults without losing precision or type', function (): void {
    $instance = makeDefaultValueInterceptor($this->createStub(ContainerInterface::class));

    expect(generatedDefaults($instance::class, 'withFloat'))->toBe(['ratio' => 0.1, 'whole' => 2.0])
        ->and($instance->withFloat())->toBe(2.1);
});

it('preserves enum case defaults, including enums nested in arrays', function (): void {
    $instance = makeDefaultValueInterceptor($this->createStub(ContainerInterface::class));

    expect($instance->withEnum())->toBe(DefaultValueStatus::Inactive)
        ->and($instance->withEnumArray())->toBe([DefaultValueStatus::Active, DefaultValueStatus::Inactive]);
});

it('preserves class constant defaults, including self:: and private constants', function (): void {
    $instance = makeDefaultValueInterceptor($this->createStub(ContainerInterface::class));

    expect($instance->withSelfConstant())->toBe('editor')
        ->and($instance->withPrivateConstant())->toBe('hidden');
});

it('preserves global constant defaults', function (): void {
    $instance = makeDefaultValueInterceptor($this->createStub(ContainerInterface::class));

    expect($instance->withGlobalConstant())->toBe(PHP_INT_MAX . PHP_EOL);
});

it('preserves null, bool and negative int defaults', function (): void {
    $instance = makeDefaultValueInterceptor($this->createStub(ContainerInterface::class));

    expect(generatedDefaults($instance::class, 'withScalars'))
        ->toBe(['nothing' => null, 'yes' => true, 'no' => false, 'negative' => -5]);
});

it('preserves array constant and quoted string defaults in interface wrappers', function (): void {
    $className = new InterceptorClassGenerator()->generateInterfaceWrapper(
        DefaultValueInterface::class,
        new PluginRegistry(),
    );

    expect(generatedDefaults($className, 'withRoles'))
        ->toBe(['roles' => ['admin', 'owner'], 'quoted' => 'it\'s "quoted"']);
});

it('throws PluginException at generation time when a plugged method has an object default', function (): void {
    $registry = makeRegistryWithPlugin(DefaultValueObjectClass::class, 'withObject');
    $generator = new InterceptorClassGenerator();

    expect(fn () => $generator->generateConcreteSubclassCode(DefaultValueObjectClass::class, $registry))
        ->toThrow(
            PluginException::class,
            "Cannot create interceptor for 'DefaultValueObjectClass::withObject()': parameter \$object has an object default value (DefaultValueObject)",
        );
});

it('throws PluginException at generation time when an array default contains an object', function (): void {
    $registry = makeRegistryWithPlugin(DefaultValueObjectInArrayClass::class, 'withObjects');
    $generator = new InterceptorClassGenerator();

    expect(fn () => $generator->generateConcreteSubclassCode(DefaultValueObjectInArrayClass::class, $registry))
        ->toThrow(PluginException::class, 'parameter $objects has an object default value (DefaultValueObject)');
});
