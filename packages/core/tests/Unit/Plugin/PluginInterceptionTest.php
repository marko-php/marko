<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Plugin\PluginArgumentCountException;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Core\Plugin\PluginInterception;
use Marko\Core\Plugin\PluginRegistry;

// ---------------------------------------------------------------------------
// Test double: concrete class that uses the trait and exposes interceptCall
// ---------------------------------------------------------------------------

class InterceptionTestTarget
{
    public function hash(string $value): string
    {
        return md5($value);
    }

    public function add(
        int $a,
        int $b,
    ): int {
        return $a + $b;
    }

    public function greet(string $name): string
    {
        return "Hello, $name!";
    }
}

class ConcreteInterceptor implements PluginInterceptedInterface
{
    use PluginInterception;

    public function callIntercept(
        string $method,
        array $arguments,
    ): mixed {
        return $this->interceptCall($method, $arguments);
    }

    public function callInterceptParent(
        string $method,
        array $arguments,
        Closure $parentCall,
    ): mixed {
        return $this->interceptParentCall($method, $arguments, $parentCall);
    }
}

// ---------------------------------------------------------------------------
// Plugin fixtures
// ---------------------------------------------------------------------------

class PI_TrackingPlugin
{
    public static array $callLog = [];

    public static function reset(): void
    {
        self::$callLog = [];
    }

    public function beforeHash(string $value): ?string
    {
        self::$callLog[] = 'PI_TrackingPlugin::beforeHash';

        return null;
    }

    public function afterHash(string $result): string
    {
        self::$callLog[] = 'PI_TrackingPlugin::afterHash';

        return $result;
    }
}

class PI_SecondTrackingPlugin
{
    public function beforeHash(string $value): ?string
    {
        PI_TrackingPlugin::$callLog[] = 'PI_SecondTrackingPlugin::beforeHash';

        return null;
    }
}

class PI_ShortCircuitPlugin
{
    public function beforeHash(string $value): string
    {
        return 'short-circuited';
    }
}

class PI_SecondShortCircuitPlugin
{
    public function beforeHash(string $value): ?string
    {
        PI_TrackingPlugin::$callLog[] = 'PI_SecondShortCircuitPlugin::beforeHash';

        return null;
    }
}

class PI_ArgModifyPlugin
{
    public function beforeHash(string $value): array
    {
        return [strtoupper($value)];
    }
}

class PI_SecondArgModifyPlugin
{
    public function beforeHash(string $value): array
    {
        return [$value . '_modified'];
    }
}

class PI_WrongArgCountPlugin
{
    public function beforeHash(string $value): array
    {
        return ['too', 'many', 'args'];
    }
}

class PI_ResultDoublePlugin
{
    public function afterHash(string $result): string
    {
        return $result . $result;
    }
}

class PI_SecondResultPlugin
{
    public function afterHash(string $result): string
    {
        return '[' . $result . ']';
    }
}

class PI_AfterWithArgsPlugin
{
    public static array $receivedArgs = [];

    public function afterHash(
        string $result,
        string $value,
    ): string {
        self::$receivedArgs = ['result' => $result, 'value' => $value];

        return $result;
    }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeRegistry(array $beforeMethods = [], array $afterMethods = [], string $targetClass = InterceptionTestTarget::class): PluginRegistry
{
    $registry = new PluginRegistry();

    if ($beforeMethods !== [] || $afterMethods !== []) {
        $registry->register(new PluginDefinition(
            pluginClass: DummyPlugin::class,
            targetClass: $targetClass,
            beforeMethods: $beforeMethods,
            afterMethods: $afterMethods,
        ));
    }

    return $registry;
}

function makeRegistryWith(array $definitions): PluginRegistry
{
    $registry = new PluginRegistry();

    foreach ($definitions as $definition) {
        $registry->register(new PluginDefinition(
            pluginClass: $definition['pluginClass'],
            targetClass: $definition['targetClass'] ?? InterceptionTestTarget::class,
            beforeMethods: $definition['beforeMethods'] ?? [],
            afterMethods: $definition['afterMethods'] ?? [],
        ));
    }

    return $registry;
}

function makeContainer(array $instances = []): ContainerInterface
{
    return new class ($instances) implements ContainerInterface
    {
        public function __construct(private array $instances) {}

        public function get(string $id): mixed
        {
            return $this->instances[$id] ?? new $id();
        }

        public function has(string $id): bool
        {
            return isset($this->instances[$id]);
        }

        public function singleton(string $id): void {}

        public function instance(
            string $id,
            object $instance,
        ): void {
            $this->instances[$id] = $instance;
        }

        public function call(Closure $callable): mixed
        {
            return $callable();
        }
    };
}

function makeInterceptor(
    ?InterceptionTestTarget $target = null,
    ?PluginRegistry $registry = null,
    ?ContainerInterface $container = null,
): ConcreteInterceptor {
    $interceptor = new ConcreteInterceptor();
    $interceptor->initInterception(
        pluginTarget: $target ?? new InterceptionTestTarget(),
        pluginTargetClass: InterceptionTestTarget::class,
        pluginContainer: $container ?? makeContainer(),
        pluginRegistry: $registry ?? new PluginRegistry(),
    );

    return $interceptor;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    PI_TrackingPlugin::reset();
    PI_AfterWithArgsPlugin::$receivedArgs = [];
});

it('initializes interception state via initInterception', function (): void {
    $target = new InterceptionTestTarget();
    $registry = new PluginRegistry();
    $container = makeContainer();

    $interceptor = new ConcreteInterceptor();
    $interceptor->initInterception(
        pluginTarget: $target,
        pluginTargetClass: InterceptionTestTarget::class,
        pluginContainer: $container,
        pluginRegistry: $registry,
    );

    expect($interceptor->getPluginTarget())->toBe($target);
});

it('executes before plugins in sort order then calls target method via interceptCall', function (): void {
    $registry = makeRegistryWith([
        [
            'pluginClass' => PI_SecondTrackingPlugin::class,
            'beforeMethods' => ['hash' => ['pluginMethod' => 'beforeHash', 'sortOrder' => 20]],
        ],
        [
            'pluginClass' => PI_TrackingPlugin::class,
            'beforeMethods' => ['hash' => ['pluginMethod' => 'beforeHash', 'sortOrder' => 10]],
        ],
    ]);

    $interceptor = makeInterceptor(registry: $registry);
    $interceptor->callIntercept('hash', ['test']);

    expect(PI_TrackingPlugin::$callLog)->toBe([
        'PI_TrackingPlugin::beforeHash',
        'PI_SecondTrackingPlugin::beforeHash',
    ]);
});

it('passes method arguments to before plugins', function (): void {
    $received = [];
    $plugin = new class ($received)
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$received,
        ) {}

        public function beforeHash(string $value): ?string
        {
            $this->received[] = $value;

            return null;
        }
    };

    $registry = makeRegistryWith([
        [
            'pluginClass' => $plugin::class,
            'beforeMethods' => ['hash' => ['pluginMethod' => 'beforeHash', 'sortOrder' => 10]],
        ],
    ]);

    makeInterceptor(
        registry: $registry,
        container: makeContainer([$plugin::class => $plugin]),
    )->callIntercept('hash', ['hello']);

    expect($received)->toBe(['hello']);
});

it('short-circuits when before plugin returns non-null non-array value', function (): void {
    $registry = makeRegistryWith([
        [
            'pluginClass' => PI_ShortCircuitPlugin::class,
            'beforeMethods' => ['hash' => ['pluginMethod' => 'beforeHash', 'sortOrder' => 10]],
        ],
    ]);

    $result = makeInterceptor(registry: $registry)->callIntercept('hash', ['test']);

    expect($result)->toBe('short-circuited');
});

it('skips remaining before plugins and target method on short-circuit', function (): void {
    $registry = makeRegistryWith([
        [
            'pluginClass' => PI_ShortCircuitPlugin::class,
            'beforeMethods' => ['hash' => ['pluginMethod' => 'beforeHash', 'sortOrder' => 10]],
        ],
        [
            'pluginClass' => PI_SecondShortCircuitPlugin::class,
            'beforeMethods' => ['hash' => ['pluginMethod' => 'beforeHash', 'sortOrder' => 20]],
        ],
    ]);

    makeInterceptor(registry: $registry)->callIntercept('hash', ['test']);

    expect(PI_TrackingPlugin::$callLog)->toBeEmpty();
});

it('passes through to target method when before plugin returns null', function (): void {
    $registry = makeRegistryWith([
        [
            'pluginClass' => PI_TrackingPlugin::class,
            'beforeMethods' => ['hash' => ['pluginMethod' => 'beforeHash', 'sortOrder' => 10]],
        ],
    ]);

    $result = makeInterceptor(registry: $registry)->callIntercept('hash', ['test']);

    expect($result)->toBe(md5('test'));
});

it('modifies arguments when before plugin returns an array', function (): void {
    $registry = makeRegistryWith([
        [
            'pluginClass' => PI_ArgModifyPlugin::class,
            'beforeMethods' => ['hash' => ['pluginMethod' => 'beforeHash', 'sortOrder' => 10]],
        ],
    ]);

    $result = makeInterceptor(registry: $registry)->callIntercept('hash', ['hello']);

    expect($result)->toBe(md5('HELLO'));
});

it('throws PluginArgumentCountException when before plugin returns array with wrong count', function (): void {
    $registry = makeRegistryWith([
        [
            'pluginClass' => PI_WrongArgCountPlugin::class,
            'beforeMethods' => ['hash' => ['pluginMethod' => 'beforeHash', 'sortOrder' => 10]],
        ],
    ]);

    expect(fn () => makeInterceptor(registry: $registry)->callIntercept('hash', ['test']))
        ->toThrow(PluginArgumentCountException::class);
});

it('chains argument modifications through multiple before plugins', function (): void {
    $registry = makeRegistryWith([
        [
            'pluginClass' => PI_ArgModifyPlugin::class,
            'beforeMethods' => ['hash' => ['pluginMethod' => 'beforeHash', 'sortOrder' => 10]],
        ],
        [
            'pluginClass' => PI_SecondArgModifyPlugin::class,
            'beforeMethods' => ['hash' => ['pluginMethod' => 'beforeHash', 'sortOrder' => 20]],
        ],
    ]);

    $result = makeInterceptor(registry: $registry)->callIntercept('hash', ['hello']);

    // ArgModifyPlugin uppercases → 'HELLO', SecondArgModifyPlugin appends → 'HELLO_modified'
    expect($result)->toBe(md5('HELLO_modified'));
});

it('executes after plugins in sort order after target method', function (): void {
    $registry = makeRegistryWith([
        [
            'pluginClass' => PI_TrackingPlugin::class,
            'afterMethods' => ['hash' => ['pluginMethod' => 'afterHash', 'sortOrder' => 10]],
        ],
    ]);

    makeInterceptor(registry: $registry)->callIntercept('hash', ['test']);

    expect(PI_TrackingPlugin::$callLog)->toBe(['PI_TrackingPlugin::afterHash']);
});

it('passes result and original arguments to after plugins', function (): void {
    $registry = makeRegistryWith([
        [
            'pluginClass' => PI_AfterWithArgsPlugin::class,
            'afterMethods' => ['hash' => ['pluginMethod' => 'afterHash', 'sortOrder' => 10]],
        ],
    ]);

    makeInterceptor(registry: $registry)->callIntercept('hash', ['hello']);

    expect(PI_AfterWithArgsPlugin::$receivedArgs['result'])->toBe(md5('hello'))
        ->and(PI_AfterWithArgsPlugin::$receivedArgs['value'])->toBe('hello');
});

it('chains modified results through multiple after plugins', function (): void {
    $registry = makeRegistryWith([
        [
            'pluginClass' => PI_ResultDoublePlugin::class,
            'afterMethods' => ['hash' => ['pluginMethod' => 'afterHash', 'sortOrder' => 10]],
        ],
        [
            'pluginClass' => PI_SecondResultPlugin::class,
            'afterMethods' => ['hash' => ['pluginMethod' => 'afterHash', 'sortOrder' => 20]],
        ],
    ]);

    $hash = md5('test');
    $result = makeInterceptor(registry: $registry)->callIntercept('hash', ['test']);

    expect($result)->toBe('[' . $hash . $hash . ']');
});

it('passes modified arguments from before plugins to after plugins', function (): void {
    $registry = makeRegistryWith([
        [
            'pluginClass' => PI_ArgModifyPlugin::class,
            'beforeMethods' => ['hash' => ['pluginMethod' => 'beforeHash', 'sortOrder' => 10]],
        ],
        [
            'pluginClass' => PI_AfterWithArgsPlugin::class,
            'afterMethods' => ['hash' => ['pluginMethod' => 'afterHash', 'sortOrder' => 10]],
        ],
    ]);

    makeInterceptor(registry: $registry)->callIntercept('hash', ['hello']);

    // ArgModifyPlugin uppercases to 'HELLO', after plugin should receive 'HELLO'
    expect(PI_AfterWithArgsPlugin::$receivedArgs['value'])->toBe('HELLO');
});

it('executes complete flow of before plugins then target then after plugins', function (): void {
    $callLog = [];
    $beforePlugin = new class ($callLog)
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$log,
        ) {}

        public function beforeHash(string $value): ?string
        {
            $this->log[] = 'before';

            return null;
        }
    };
    $afterPlugin = new class ($callLog)
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$log,
        ) {}

        public function afterHash(string $result): string
        {
            $this->log[] = 'after';

            return $result;
        }
    };

    $registry = makeRegistryWith([
        [
            'pluginClass' => $beforePlugin::class,
            'beforeMethods' => ['hash' => ['pluginMethod' => 'beforeHash', 'sortOrder' => 10]],
        ],
        [
            'pluginClass' => $afterPlugin::class,
            'afterMethods' => ['hash' => ['pluginMethod' => 'afterHash', 'sortOrder' => 10]],
        ],
    ]);

    $result = makeInterceptor(
        registry: $registry,
        container: makeContainer([
            $beforePlugin::class => $beforePlugin,
            $afterPlugin::class => $afterPlugin,
        ]),
    )->callIntercept('hash', ['test']);

    expect($callLog)->toBe(['before', 'after'])
        ->and($result)->toBe(md5('test'));
});

it('returns target instance via getPluginTarget', function (): void {
    $target = new InterceptionTestTarget();
    $interceptor = makeInterceptor(target: $target);

    expect($interceptor->getPluginTarget())->toBe($target);
});

it('executes parent call via interceptParentCall for subclass strategy', function (): void {
    $parentCalled = false;
    $parentCall = function (string $value) use (&$parentCalled): string {
        $parentCalled = true;

        return 'parent:' . $value;
    };

    $registry = makeRegistryWith([
        [
            'pluginClass' => PI_ArgModifyPlugin::class,
            'beforeMethods' => ['hash' => ['pluginMethod' => 'beforeHash', 'sortOrder' => 10]],
        ],
        [
            'pluginClass' => PI_ResultDoublePlugin::class,
            'afterMethods' => ['hash' => ['pluginMethod' => 'afterHash', 'sortOrder' => 10]],
        ],
    ]);

    $interceptor = makeInterceptor(registry: $registry);
    $result = $interceptor->callInterceptParent('hash', ['hello'], Closure::fromCallable($parentCall));

    // ArgModifyPlugin uppercases to 'HELLO', parent returns 'parent:HELLO', ResultDoublePlugin doubles it
    expect($parentCalled)->toBeTrue()
        ->and($result)->toBe('parent:HELLOparent:HELLO');
});
