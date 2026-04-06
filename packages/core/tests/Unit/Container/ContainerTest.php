<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Exceptions\BindingException;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;
use Marko\TestFixture\Exceptions\NoDriverException;
use Marko\TestFixture\SomeInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;

require_once __DIR__ . '/Fixtures/TestFixtureInterface.php';
require_once __DIR__ . '/Fixtures/TestFixtureNoDriverException.php';
require_once __DIR__ . '/Fixtures/TestFixtureNoDriverInterface.php';

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

it('throws NoDriverException when interface package has one and no binding exists', function (): void {
    $container = new Container();

    expect(fn () => $container->get(SomeInterface::class))
        ->toThrow(NoDriverException::class);
});

it('throws generic BindingException when no NoDriverException class exists for the package', function (): void {
    $container = new Container();

    expect(fn () => $container->get(Marko\TestFixtureNoDriver\SomeInterface::class))
        ->toThrow(BindingException::class);
});

it('falls back to BindingException for non-Marko interfaces', function (): void {
    $container = new Container();

    expect(fn () => $container->get(UnboundInterface::class))
        ->toThrow(BindingException::class);
});

it('does not check for NoDriverException on non-interface classes', function (): void {
    $container = new Container();

    expect(fn () => $container->get('NonExistentClass'))
        ->not->toThrow(NoDriverException::class);
});

it('accepts PluginInterceptor via setter method', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();
    $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());

    $container->setPluginInterceptor($interceptor);

    expect($container)->toBeInstanceOf(Container::class);
});

it('resolves classes without PluginInterceptor when none is set', function (): void {
    $container = new Container();

    $instance = $container->get(SimpleClass::class);

    expect($instance)->toBeInstanceOf(SimpleClass::class);
});

it('continues to work with PreferenceRegistry when PluginInterceptor is also set', function (): void {
    $preferenceRegistry = new PreferenceRegistry();
    $container = new Container($preferenceRegistry);
    $pluginRegistry = new PluginRegistry();
    $interceptor = new PluginInterceptor($container, $pluginRegistry, new InterceptorClassGenerator());

    $container->setPluginInterceptor($interceptor);

    $instance = $container->get(SimpleClass::class);

    expect($instance)->toBeInstanceOf(SimpleClass::class);
});

// Plugin interception test fixtures
class PluggableService
{
    public function getValue(): string
    {
        return 'original';
    }
}

class PluggableServicePlugin
{
    public function beforeGetValue(): ?array
    {
        return null;
    }
}

class PluginPreferredService extends PluggableService {}

describe('plugin interception', function (): void {
    it('wraps resolved instance with plugin proxy when plugins are registered', function (): void {
        $container = new Container();
        $registry = new PluginRegistry();
        $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
        $container->setPluginInterceptor($interceptor);

        $registry->register(new PluginDefinition(
            pluginClass: PluggableServicePlugin::class,
            targetClass: PluggableService::class,
            beforeMethods: ['getValue' => ['pluginMethod' => 'beforeGetValue', 'sortOrder' => 10]],
        ));

        $instance = $container->get(PluggableService::class);

        expect($instance)->toBeInstanceOf(PluginInterceptedInterface::class);
    });

    it('returns raw instance when no plugins are registered for the class', function (): void {
        $container = new Container();
        $registry = new PluginRegistry();
        $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
        $container->setPluginInterceptor($interceptor);

        $instance = $container->get(PluggableService::class);

        expect($instance)->toBeInstanceOf(PluggableService::class)
            ->and($instance)->not->toBeInstanceOf(PluginInterceptedInterface::class);
    });

    it('caches the proxy as the singleton instance on subsequent resolves', function (): void {
        $container = new Container();
        $registry = new PluginRegistry();
        $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
        $container->setPluginInterceptor($interceptor);
        $container->singleton(PluggableService::class);

        $registry->register(new PluginDefinition(
            pluginClass: PluggableServicePlugin::class,
            targetClass: PluggableService::class,
            beforeMethods: ['getValue' => ['pluginMethod' => 'beforeGetValue', 'sortOrder' => 10]],
        ));

        $instance1 = $container->get(PluggableService::class);
        $instance2 = $container->get(PluggableService::class);

        expect($instance1)->toBeInstanceOf(PluginInterceptedInterface::class)
            ->and($instance1)->toBe($instance2);
    });

    it('wraps closure binding results with plugin proxy', function (): void {
        $container = new Container();
        $registry = new PluginRegistry();
        $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
        $container->setPluginInterceptor($interceptor);

        $container->bind(PluggableService::class, fn () => new PluggableService());

        $registry->register(new PluginDefinition(
            pluginClass: PluggableServicePlugin::class,
            targetClass: PluggableService::class,
            beforeMethods: ['getValue' => ['pluginMethod' => 'beforeGetValue', 'sortOrder' => 10]],
        ));

        $instance = $container->get(PluggableService::class);

        expect($instance)->toBeInstanceOf(PluginInterceptedInterface::class);
    });

    it('does not wrap pre-registered instances from instance() method', function (): void {
        $container = new Container();
        $registry = new PluginRegistry();
        $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
        $container->setPluginInterceptor($interceptor);

        $registry->register(new PluginDefinition(
            pluginClass: PluggableServicePlugin::class,
            targetClass: PluggableService::class,
            beforeMethods: ['getValue' => ['pluginMethod' => 'beforeGetValue', 'sortOrder' => 10]],
        ));

        $rawInstance = new PluggableService();
        $container->instance(PluggableService::class, $rawInstance);

        $resolved = $container->get(PluggableService::class);

        expect($resolved)->toBe($rawInstance)
            ->and($resolved)->not->toBeInstanceOf(PluginInterceptedInterface::class);
    });

    it('applies plugin proxy after preference resolution', function (): void {
        $preferenceRegistry = new PreferenceRegistry();
        $preferenceRegistry->register(
            original: PluggableService::class,
            replacement: PluginPreferredService::class,
        );

        $container = new Container($preferenceRegistry);
        $registry = new PluginRegistry();
        $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
        $container->setPluginInterceptor($interceptor);

        $registry->register(new PluginDefinition(
            pluginClass: PluggableServicePlugin::class,
            targetClass: PluginPreferredService::class,
            beforeMethods: ['getValue' => ['pluginMethod' => 'beforeGetValue', 'sortOrder' => 10]],
        ));

        $instance = $container->get(PluggableService::class);

        expect($instance)->toBeInstanceOf(PluginInterceptedInterface::class);
    });
});

// Fixtures for end-to-end plugin interception tests
class E2eService
{
    public function greet(string $name): string
    {
        return "Hello, $name";
    }

    public function getValue(): string
    {
        return 'original';
    }
}

class E2eBeforePlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function beforeGreet(string $name): ?array
    {
        return ['MODIFIED'];
    }
}

class E2eTrackingPlugin
{
    public bool $called = false;

    /** @noinspection PhpUnused - Invoked via reflection */
    public function beforeGetValue(): ?array
    {
        $this->called = true;

        return null;
    }
}

class E2eAfterPlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function afterGetValue(string $result): string
    {
        return strtoupper($result);
    }
}

interface E2eServiceInterface {}

class E2eConcreteService implements E2eServiceInterface
{
    public function compute(): string
    {
        return 'computed';
    }
}

class E2eConcretePlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function afterCompute(string $result): string
    {
        return $result . '-plugged';
    }
}

describe('plugin interception - end to end', function (): void {
    it('fires before plugin when calling method on container-resolved object', function (): void {
        $container = new Container();
        $registry = new PluginRegistry();
        $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
        $container->setPluginInterceptor($interceptor);

        $tracking = new E2eTrackingPlugin();
        $container->instance(E2eTrackingPlugin::class, $tracking);

        $registry->register(new PluginDefinition(
            pluginClass: E2eTrackingPlugin::class,
            targetClass: E2eService::class,
            beforeMethods: ['getValue' => ['pluginMethod' => 'beforeGetValue', 'sortOrder' => 10]],
        ));

        $instance = $container->get(E2eService::class);
        $instance->getValue();

        expect($tracking->called)->toBeTrue();
    });

    it('fires after plugin when calling method on container-resolved object', function (): void {
        $container = new Container();
        $registry = new PluginRegistry();
        $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
        $container->setPluginInterceptor($interceptor);

        $registry->register(new PluginDefinition(
            pluginClass: E2eAfterPlugin::class,
            targetClass: E2eService::class,
            afterMethods: ['getValue' => ['pluginMethod' => 'afterGetValue', 'sortOrder' => 10]],
        ));

        $instance = $container->get(E2eService::class);
        $result = $instance->getValue();

        expect($result)->toBe('ORIGINAL');
    });

    it('passes modified arguments from before plugin to target method', function (): void {
        $container = new Container();
        $registry = new PluginRegistry();
        $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
        $container->setPluginInterceptor($interceptor);

        $registry->register(new PluginDefinition(
            pluginClass: E2eBeforePlugin::class,
            targetClass: E2eService::class,
            beforeMethods: ['greet' => ['pluginMethod' => 'beforeGreet', 'sortOrder' => 10]],
        ));

        $instance = $container->get(E2eService::class);
        $result = $instance->greet('World');

        expect($result)->toBe('Hello, MODIFIED');
    });

    it('passes modified result from after plugin back to caller', function (): void {
        $container = new Container();
        $registry = new PluginRegistry();
        $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
        $container->setPluginInterceptor($interceptor);

        $registry->register(new PluginDefinition(
            pluginClass: E2eAfterPlugin::class,
            targetClass: E2eService::class,
            afterMethods: ['getValue' => ['pluginMethod' => 'afterGetValue', 'sortOrder' => 10]],
        ));

        $instance = $container->get(E2eService::class);
        $result = $instance->getValue();

        expect($result)->toBe('ORIGINAL');
    });

    it('fires plugins on preference-resolved objects', function (): void {
        $preferenceRegistry = new PreferenceRegistry();
        $preferenceRegistry->register(
            original: E2eServiceInterface::class,
            replacement: E2eConcreteService::class,
        );

        $container = new Container($preferenceRegistry);
        $registry = new PluginRegistry();
        $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
        $container->setPluginInterceptor($interceptor);

        $registry->register(new PluginDefinition(
            pluginClass: E2eConcretePlugin::class,
            targetClass: E2eConcreteService::class,
            afterMethods: ['compute' => ['pluginMethod' => 'afterCompute', 'sortOrder' => 10]],
        ));

        $instance = $container->get(E2eServiceInterface::class);
        $result = $instance->compute();

        expect($result)->toBe('computed-plugged');
    });

    it('returns same proxied singleton on repeated resolves', function (): void {
        $container = new Container();
        $registry = new PluginRegistry();
        $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
        $container->setPluginInterceptor($interceptor);
        $container->singleton(E2eService::class);

        $registry->register(new PluginDefinition(
            pluginClass: E2eAfterPlugin::class,
            targetClass: E2eService::class,
            afterMethods: ['getValue' => ['pluginMethod' => 'afterGetValue', 'sortOrder' => 10]],
        ));

        $proxy1 = $container->get(E2eService::class);
        $proxy2 = $container->get(E2eService::class);

        expect($proxy1)->toBeInstanceOf(PluginInterceptedInterface::class)
            ->and($proxy1)->toBe($proxy2);
    });
});
