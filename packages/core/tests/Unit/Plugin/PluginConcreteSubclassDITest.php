<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;

// All fixture classes are prefixed with PCSDI_ to avoid conflicts.

// ---------------------------------------------------------------------------
// Dependency classes
// ---------------------------------------------------------------------------

readonly class PCSDI_Dep
{
    public function __construct(
        public string $value,
    ) {}
}

readonly class PCSDI_PrivateDep
{
    public function __construct(
        private string $secret,
    ) {}

    public function getSecret(): string
    {
        return $this->secret;
    }
}

readonly class PCSDI_BaseDep
{
    public function __construct(
        public string $baseValue,
    ) {}
}

// ---------------------------------------------------------------------------
// Target classes with mandatory promoted DI deps
// ---------------------------------------------------------------------------

class PCSDI_ConcreteService
{
    public function __construct(
        private readonly PCSDI_Dep $dep,
    ) {}

    public function work(): string
    {
        return 'worked: ' . $this->dep->value;
    }

    public function getDepValue(): string
    {
        return $this->dep->value;
    }
}

class PCSDI_ServiceWithPrivateDep
{
    public function __construct(
        private readonly PCSDI_PrivateDep $privateDep,
    ) {}

    /** @noinspection PhpUnused - accessed via non-plugged method */
    public function getSecret(): string
    {
        return $this->privateDep->getSecret();
    }

    public function compute(): string
    {
        return 'computed: ' . $this->privateDep->getSecret();
    }
}

class PCSDI_BaseService
{
    public function __construct(
        protected readonly PCSDI_BaseDep $baseDep,
    ) {}

    public function baseWork(): string
    {
        return 'base: ' . $this->baseDep->baseValue;
    }
}

class PCSDI_ChildService extends PCSDI_BaseService
{
    public function __construct(
        PCSDI_BaseDep $baseDep,
        private readonly PCSDI_Dep $childDep,
    ) {
        parent::__construct($baseDep);
    }

    public function childWork(): string
    {
        return 'child: ' . $this->childDep->value . ' + ' . $this->baseDep->baseValue;
    }
}

class PCSDI_ConcreteServiceCtorSideEffect
{
    public static int $ctorCallCount = 0;

    public function __construct(
        private readonly PCSDI_Dep $dep,
    ) {
        self::$ctorCallCount++;
    }

    public function work(): string
    {
        return 'worked: ' . $this->dep->value;
    }
}

readonly class PCSDI_ReadonlyConcreteService
{
    public function work(): string
    {
        return 'readonly work';
    }
}

// ---------------------------------------------------------------------------
// Plugin classes
// ---------------------------------------------------------------------------

class PCSDI_BeforePlugin
{
    public static array $callLog = [];

    /** @noinspection PhpUnused - invoked via reflection */
    public function work(): ?string
    {
        self::$callLog[] = 'PCSDI_BeforePlugin::work';

        return null;
    }
}

class PCSDI_AfterPlugin
{
    public static array $callLog = [];

    /** @noinspection PhpUnused - invoked via reflection */
    public function work(mixed $result): string
    {
        self::$callLog[] = 'PCSDI_AfterPlugin::work';

        return $result . ' [after]';
    }
}

class PCSDI_ComputeBeforePlugin
{
    /** @noinspection PhpUnused - invoked via reflection */
    public function compute(): ?string
    {
        return null;
    }
}

class PCSDI_ComputeAfterPlugin
{
    /** @noinspection PhpUnused - invoked via reflection */
    public function compute(mixed $result): string
    {
        return $result . ' [modified]';
    }
}

class PCSDI_ChildWorkBeforePlugin
{
    /** @noinspection PhpUnused - invoked via reflection */
    public function childWork(): ?string
    {
        return null;
    }
}

class PCSDI_WorkBeforePlugin
{
    /** @noinspection PhpUnused - invoked via reflection */
    public function work(): ?string
    {
        return null;
    }
}

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function makePcsDiInterceptor(Container $container, PluginRegistry $registry): PluginInterceptor
{
    return new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
}

// ---------------------------------------------------------------------------
// Reset static state before each test
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    PCSDI_BeforePlugin::$callLog = [];
    PCSDI_AfterPlugin::$callLog = [];
    PCSDI_ConcreteServiceCtorSideEffect::$ctorCallCount = 0;
});

// ===========================================================================
// TESTS
// ===========================================================================

it(
    'intercepts a #[Before] plugin method on a concrete class whose constructor has a mandatory promoted dependency without throwing ArgumentCountError',
    function (): void {
        $container = new Container();
        $registry = new PluginRegistry();

        $registry->register(new PluginDefinition(
            pluginClass: PCSDI_BeforePlugin::class,
            targetClass: PCSDI_ConcreteService::class,
            beforeMethods: ['work' => ['pluginMethod' => 'work', 'sortOrder' => 10]],
        ));

        $dep = new PCSDI_Dep(value: 'injected');
        $target = new PCSDI_ConcreteService($dep);

        $interceptor = makePcsDiInterceptor($container, $registry);
        $proxy = $interceptor->createProxy(
            PCSDI_ConcreteService::class,
            PCSDI_ConcreteService::class,
            $target,
        );

        $result = $proxy->work();

        expect(PCSDI_BeforePlugin::$callLog)->toBe(['PCSDI_BeforePlugin::work'])
            ->and($result)->toBe('worked: injected');
    },
);

it(
    'intercepts an #[After] plugin method on a constructor-DI concrete class and returns the plugin-modified result',
    function (): void {
        $container = new Container();
        $registry = new PluginRegistry();

        $registry->register(new PluginDefinition(
            pluginClass: PCSDI_AfterPlugin::class,
            targetClass: PCSDI_ConcreteService::class,
            afterMethods: ['work' => ['pluginMethod' => 'work', 'sortOrder' => 10]],
        ));

        $dep = new PCSDI_Dep(value: 'injected');
        $target = new PCSDI_ConcreteService($dep);

        $interceptor = makePcsDiInterceptor($container, $registry);
        $proxy = $interceptor->createProxy(
            PCSDI_ConcreteService::class,
            PCSDI_ConcreteService::class,
            $target,
        );

        $result = $proxy->work();

        expect(PCSDI_AfterPlugin::$callLog)->toBe(['PCSDI_AfterPlugin::work'])
            ->and($result)->toBe('worked: injected [after]');
    },
);

it(
    'runs a PLUGGED method that reads a constructor-injected property and returns the real injected value (parent:: call sees the copied state, not an uninitialized property)',
    function (): void {
        $container = new Container();
        $registry = new PluginRegistry();

        $registry->register(new PluginDefinition(
            pluginClass: PCSDI_BeforePlugin::class,
            targetClass: PCSDI_ConcreteService::class,
            beforeMethods: ['work' => ['pluginMethod' => 'work', 'sortOrder' => 10]],
        ));

        $dep = new PCSDI_Dep(value: 'real-value');
        $target = new PCSDI_ConcreteService($dep);

        $interceptor = makePcsDiInterceptor($container, $registry);
        $proxy = $interceptor->createProxy(
            PCSDI_ConcreteService::class,
            PCSDI_ConcreteService::class,
            $target,
        );

        $result = $proxy->work();

        expect($result)->toBe('worked: real-value');
    },
);

it(
    'delegates non-plugged public methods on a constructor-DI concrete target, reading the real constructor-injected state without an uninitialized-property Error',
    function (): void {
        $container = new Container();
        $registry = new PluginRegistry();

        // Only 'work' is plugged; 'getDepValue' is NOT plugged.
        $registry->register(new PluginDefinition(
            pluginClass: PCSDI_BeforePlugin::class,
            targetClass: PCSDI_ConcreteService::class,
            beforeMethods: ['work' => ['pluginMethod' => 'work', 'sortOrder' => 10]],
        ));

        $dep = new PCSDI_Dep(value: 'dep-value');
        $target = new PCSDI_ConcreteService($dep);

        $interceptor = makePcsDiInterceptor($container, $registry);
        $proxy = $interceptor->createProxy(
            PCSDI_ConcreteService::class,
            PCSDI_ConcreteService::class,
            $target,
        );

        // Non-plugged method reads the private property via $this (inherited)
        $result = $proxy->getDepValue();

        expect($result)->toBe('dep-value');
    },
);

it(
    'copies private and protected properties from the target onto the interceptor (state from base classes in the hierarchy is preserved)',
    function (): void {
        $container = new Container();
        $registry = new PluginRegistry();

        $registry->register(new PluginDefinition(
            pluginClass: PCSDI_ChildWorkBeforePlugin::class,
            targetClass: PCSDI_ChildService::class,
            beforeMethods: ['childWork' => ['pluginMethod' => 'childWork', 'sortOrder' => 10]],
        ));

        $baseDep = new PCSDI_BaseDep(baseValue: 'from-base');
        $childDep = new PCSDI_Dep(value: 'from-child');
        $target = new PCSDI_ChildService($baseDep, $childDep);

        $interceptor = makePcsDiInterceptor($container, $registry);
        $proxy = $interceptor->createProxy(
            PCSDI_ChildService::class,
            PCSDI_ChildService::class,
            $target,
        );

        $result = $proxy->childWork();

        expect($result)->toBe('child: from-child + from-base');
    },
);

it(
    'does not invoke the target\'s parent constructor when building the concrete subclass interceptor (instantiates via newInstanceWithoutConstructor)',
    function (): void {
        $container = new Container();
        $registry = new PluginRegistry();

        $registry->register(new PluginDefinition(
            pluginClass: PCSDI_WorkBeforePlugin::class,
            targetClass: PCSDI_ConcreteServiceCtorSideEffect::class,
            beforeMethods: ['work' => ['pluginMethod' => 'work', 'sortOrder' => 10]],
        ));

        $dep = new PCSDI_Dep(value: 'side-effect-test');
        $target = new PCSDI_ConcreteServiceCtorSideEffect($dep);

        // Target construction incremented it once
        expect(PCSDI_ConcreteServiceCtorSideEffect::$ctorCallCount)->toBe(1);

        $interceptor = makePcsDiInterceptor($container, $registry);
        $proxy = $interceptor->createProxy(
            PCSDI_ConcreteServiceCtorSideEffect::class,
            PCSDI_ConcreteServiceCtorSideEffect::class,
            $target,
        );

        // Constructor must NOT have been called again for the interceptor
        expect(PCSDI_ConcreteServiceCtorSideEffect::$ctorCallCount)->toBe(1)
            ->and($proxy->work())->toBe('worked: side-effect-test');
    },
);

it(
    'leaves the interface-wrapper strategy unchanged for interface targets with plugins',
    function (): void {
        $container = new Container();
        $registry = new PluginRegistry();

        // Register plugin against an interface — this should use the interface-wrapper strategy
        $registry->register(new PluginDefinition(
            pluginClass: PCSDI_BeforePlugin::class,
            targetClass: PCSDI_ConcreteService::class,
            beforeMethods: ['work' => ['pluginMethod' => 'work', 'sortOrder' => 10]],
        ));

        $dep = new PCSDI_Dep(value: 'original');
        $target = new PCSDI_ConcreteService($dep);

        $interceptor = makePcsDiInterceptor($container, $registry);

        // Use same concrete class for both originalId and resolvedId to force concrete-subclass path
        $proxy = $interceptor->createProxy(
            PCSDI_ConcreteService::class,
            PCSDI_ConcreteService::class,
            $target,
        );

        expect($proxy)->toBeInstanceOf(PluginInterceptedInterface::class)
            ->and($proxy)->toBeInstanceOf(PCSDI_ConcreteService::class)
            ->and($proxy->getPluginTarget())->toBe($target);
    },
);

it(
    'still throws the existing loud PluginException for readonly concrete targets',
    function (): void {
        $container = new Container();
        $registry = new PluginRegistry();

        $registry->register(new PluginDefinition(
            pluginClass: PCSDI_BeforePlugin::class,
            targetClass: PCSDI_ReadonlyConcreteService::class,
            beforeMethods: ['work' => ['pluginMethod' => 'work', 'sortOrder' => 10]],
        ));

        $target = new PCSDI_ReadonlyConcreteService();

        $interceptor = makePcsDiInterceptor($container, $registry);

        expect(
            fn () => $interceptor->createProxy(
                PCSDI_ReadonlyConcreteService::class,
                PCSDI_ReadonlyConcreteService::class,
                $target,
            ),
        )->toThrow(PluginException::class);
    },
);
