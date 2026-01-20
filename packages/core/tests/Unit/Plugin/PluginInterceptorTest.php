<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;

// Test fixtures for interceptor tests
class InterceptorTargetService
{
    public static array $callLog = [];

    public function doAction(): string
    {
        self::$callLog[] = 'InterceptorTargetService::doAction';

        return 'original result';
    }
}

class FirstBeforePlugin
{
    public function beforeDoAction(): ?string
    {
        InterceptorTargetService::$callLog[] = 'FirstBeforePlugin::beforeDoAction';

        return null;
    }
}

class SecondBeforePlugin
{
    public function beforeDoAction(): ?string
    {
        InterceptorTargetService::$callLog[] = 'SecondBeforePlugin::beforeDoAction';

        return null;
    }
}

beforeEach(function (): void {
    InterceptorTargetService::$callLog = [];
});

it('executes before plugins in sort order before target method', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    // Register plugins with different sort orders (lower runs first)
    $registry->register(new PluginDefinition(
        pluginClass: SecondBeforePlugin::class,
        targetClass: InterceptorTargetService::class,
        beforeMethods: ['beforeDoAction' => 20],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: FirstBeforePlugin::class,
        targetClass: InterceptorTargetService::class,
        beforeMethods: ['beforeDoAction' => 10],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(InterceptorTargetService::class, new InterceptorTargetService());

    $result = $proxy->doAction();

    // Verify execution order: before plugins (in sort order), then target
    expect(InterceptorTargetService::$callLog)->toBe([
        'FirstBeforePlugin::beforeDoAction',
        'SecondBeforePlugin::beforeDoAction',
        'InterceptorTargetService::doAction',
    ])
        ->and($result)->toBe('original result');
});

// Test fixtures for argument passing
class ServiceWithArgs
{
    public static array $callLog = [];

    public function process(
        string $name,
        int $count,
    ): string {
        self::$callLog[] = "ServiceWithArgs::process($name, $count)";

        return "processed: $name, $count";
    }
}

class ArgLoggingPlugin
{
    public static array $receivedArgs = [];

    public function beforeProcess(
        string $name,
        int $count,
    ): ?string {
        self::$receivedArgs = ['name' => $name, 'count' => $count];
        ServiceWithArgs::$callLog[] = "ArgLoggingPlugin::beforeProcess($name, $count)";

        return null;
    }
}

it('passes method arguments to before plugins', function (): void {
    ServiceWithArgs::$callLog = [];
    ArgLoggingPlugin::$receivedArgs = [];

    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: ArgLoggingPlugin::class,
        targetClass: ServiceWithArgs::class,
        beforeMethods: ['beforeProcess' => 10],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(ServiceWithArgs::class, new ServiceWithArgs());

    $result = $proxy->process('test', 42);

    expect(ArgLoggingPlugin::$receivedArgs)->toBe(['name' => 'test', 'count' => 42])
        ->and(ServiceWithArgs::$callLog)->toBe([
            'ArgLoggingPlugin::beforeProcess(test, 42)',
            'ServiceWithArgs::process(test, 42)',
        ])
        ->and($result)->toBe('processed: test, 42');
});

// Test fixtures for short-circuit
class ShortCircuitService
{
    public static array $callLog = [];

    public function doAction(): string
    {
        self::$callLog[] = 'ShortCircuitService::doAction';

        return 'original';
    }
}

class ShortCircuitPlugin
{
    public function beforeDoAction(): ?string
    {
        ShortCircuitService::$callLog[] = 'ShortCircuitPlugin::beforeDoAction';

        return 'short-circuited';
    }
}

class SkippedPlugin
{
    public function beforeDoAction(): ?string
    {
        ShortCircuitService::$callLog[] = 'SkippedPlugin::beforeDoAction';

        return null;
    }
}

it('short-circuits when before plugin returns non-null value', function (): void {
    ShortCircuitService::$callLog = [];

    $container = new Container();
    $registry = new PluginRegistry();

    // First plugin passes through
    $registry->register(new PluginDefinition(
        pluginClass: SkippedPlugin::class,
        targetClass: ShortCircuitService::class,
        beforeMethods: ['beforeDoAction' => 30],  // Will be skipped due to short-circuit
    ));

    // Second plugin short-circuits
    $registry->register(new PluginDefinition(
        pluginClass: ShortCircuitPlugin::class,
        targetClass: ShortCircuitService::class,
        beforeMethods: ['beforeDoAction' => 10],  // Runs first, returns non-null
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(ShortCircuitService::class, new ShortCircuitService());

    $result = $proxy->doAction();

    // Should only run the short-circuiting plugin, skip the rest and target
    expect(ShortCircuitService::$callLog)->toBe([
        'ShortCircuitPlugin::beforeDoAction',
    ])
        ->and($result)->toBe('short-circuited');
});

// Test fixtures for pass-through plugins
class PassThroughService
{
    public static array $callLog = [];

    public function doAction(): string
    {
        self::$callLog[] = 'PassThroughService::doAction';

        return 'target result';
    }
}

class PassThroughPluginA
{
    public function beforeDoAction(): ?string
    {
        PassThroughService::$callLog[] = 'PassThroughPluginA::beforeDoAction';

        return null;
    }
}

class PassThroughPluginB
{
    public function beforeDoAction(): ?string
    {
        PassThroughService::$callLog[] = 'PassThroughPluginB::beforeDoAction';

        return null;
    }
}

it('executes target method when all before plugins return null', function (): void {
    PassThroughService::$callLog = [];

    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PassThroughPluginA::class,
        targetClass: PassThroughService::class,
        beforeMethods: ['beforeDoAction' => 10],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: PassThroughPluginB::class,
        targetClass: PassThroughService::class,
        beforeMethods: ['beforeDoAction' => 20],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PassThroughService::class, new PassThroughService());

    $result = $proxy->doAction();

    // All plugins pass through, target method executes
    expect(PassThroughService::$callLog)->toBe([
        'PassThroughPluginA::beforeDoAction',
        'PassThroughPluginB::beforeDoAction',
        'PassThroughService::doAction',
    ])
        ->and($result)->toBe('target result');
});

// Test fixtures for after plugins
class AfterTargetService
{
    public static array $callLog = [];

    public function doAction(): string
    {
        self::$callLog[] = 'AfterTargetService::doAction';

        return 'original';
    }
}

class FirstAfterPlugin
{
    public function afterDoAction(mixed $result): mixed
    {
        AfterTargetService::$callLog[] = 'FirstAfterPlugin::afterDoAction';

        return $result;
    }
}

class SecondAfterPlugin
{
    public function afterDoAction(mixed $result): mixed
    {
        AfterTargetService::$callLog[] = 'SecondAfterPlugin::afterDoAction';

        return $result;
    }
}

it('executes after plugins in sort order after target method', function (): void {
    AfterTargetService::$callLog = [];

    $container = new Container();
    $registry = new PluginRegistry();

    // Register in reverse order to verify sorting
    $registry->register(new PluginDefinition(
        pluginClass: SecondAfterPlugin::class,
        targetClass: AfterTargetService::class,
        afterMethods: ['afterDoAction' => 20],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: FirstAfterPlugin::class,
        targetClass: AfterTargetService::class,
        afterMethods: ['afterDoAction' => 10],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(AfterTargetService::class, new AfterTargetService());

    $result = $proxy->doAction();

    // Verify execution order: target, then after plugins in sort order
    expect(AfterTargetService::$callLog)->toBe([
        'AfterTargetService::doAction',
        'FirstAfterPlugin::afterDoAction',
        'SecondAfterPlugin::afterDoAction',
    ])
        ->and($result)->toBe('original');
});

// Test fixtures for after plugin argument inspection
class AfterServiceWithArgs
{
    public static array $callLog = [];

    public function calculate(
        int $a,
        int $b,
    ): int {
        self::$callLog[] = "AfterServiceWithArgs::calculate($a, $b)";

        return $a + $b;
    }
}

class AfterArgInspectorPlugin
{
    public static array $receivedArgs = [];

    public function afterCalculate(
        mixed $result,
        int $a,
        int $b,
    ): mixed {
        self::$receivedArgs = ['result' => $result, 'a' => $a, 'b' => $b];
        AfterServiceWithArgs::$callLog[] = "AfterArgInspectorPlugin::afterCalculate(result=$result, a=$a, b=$b)";

        return $result;
    }
}

it('passes result and original arguments to after plugins', function (): void {
    AfterServiceWithArgs::$callLog = [];
    AfterArgInspectorPlugin::$receivedArgs = [];

    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: AfterArgInspectorPlugin::class,
        targetClass: AfterServiceWithArgs::class,
        afterMethods: ['afterCalculate' => 10],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(AfterServiceWithArgs::class, new AfterServiceWithArgs());

    $result = $proxy->calculate(5, 3);

    expect(AfterArgInspectorPlugin::$receivedArgs)->toBe([
        'result' => 8,
        'a' => 5,
        'b' => 3,
    ])
        ->and(AfterServiceWithArgs::$callLog)->toBe([
            'AfterServiceWithArgs::calculate(5, 3)',
            'AfterArgInspectorPlugin::afterCalculate(result=8, a=5, b=3)',
        ])
        ->and($result)->toBe(8);
});

// Test fixtures for result modification chain
class ModifyResultService
{
    public static array $callLog = [];

    public function getValue(): int
    {
        self::$callLog[] = 'ModifyResultService::getValue';

        return 10;
    }
}

class DoublerPlugin
{
    public static ?int $receivedResult = null;

    public function afterGetValue(mixed $result): int
    {
        self::$receivedResult = $result;
        ModifyResultService::$callLog[] = "DoublerPlugin::afterGetValue(received=$result)";

        return $result * 2;
    }
}

class AdderPlugin
{
    public static ?int $receivedResult = null;

    public function afterGetValue(mixed $result): int
    {
        self::$receivedResult = $result;
        ModifyResultService::$callLog[] = "AdderPlugin::afterGetValue(received=$result)";

        return $result + 5;
    }
}

it('uses modified result from after plugin for next plugin', function (): void {
    ModifyResultService::$callLog = [];
    DoublerPlugin::$receivedResult = null;
    AdderPlugin::$receivedResult = null;

    $container = new Container();
    $registry = new PluginRegistry();

    // Doubler runs first (sort order 10), then Adder (sort order 20)
    // Original: 10 -> Doubler: 20 -> Adder: 25
    $registry->register(new PluginDefinition(
        pluginClass: DoublerPlugin::class,
        targetClass: ModifyResultService::class,
        afterMethods: ['afterGetValue' => 10],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: AdderPlugin::class,
        targetClass: ModifyResultService::class,
        afterMethods: ['afterGetValue' => 20],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(ModifyResultService::class, new ModifyResultService());

    $result = $proxy->getValue();

    // Verify the chain: 10 -> 20 -> 25
    expect(DoublerPlugin::$receivedResult)->toBe(10)  // Received original
        ->and(AdderPlugin::$receivedResult)->toBe(20)  // Received doubled value
        ->and($result)->toBe(25)                       // Final result
        ->and(ModifyResultService::$callLog)->toBe([
            'ModifyResultService::getValue',
            'DoublerPlugin::afterGetValue(received=10)',
            'AdderPlugin::afterGetValue(received=20)',
        ]);
});

// Test fixture for complete flow
class CompleteFlowService
{
    public static array $callLog = [];

    public function process(string $input): string
    {
        self::$callLog[] = "CompleteFlowService::process($input)";

        return "processed: $input";
    }
}

class CompleteFlowBeforePlugin
{
    public function beforeProcess(string $input): ?string
    {
        CompleteFlowService::$callLog[] = "CompleteFlowBeforePlugin::beforeProcess($input)";

        return null;
    }
}

class CompleteFlowAfterPlugin
{
    public function afterProcess(
        mixed $result,
        string $input,
    ): string {
        CompleteFlowService::$callLog[] = "CompleteFlowAfterPlugin::afterProcess($result, $input)";

        return "$result [modified]";
    }
}

it('returns final result after all after plugins complete', function (): void {
    CompleteFlowService::$callLog = [];

    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: CompleteFlowBeforePlugin::class,
        targetClass: CompleteFlowService::class,
        beforeMethods: ['beforeProcess' => 10],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: CompleteFlowAfterPlugin::class,
        targetClass: CompleteFlowService::class,
        afterMethods: ['afterProcess' => 10],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(CompleteFlowService::class, new CompleteFlowService());

    $result = $proxy->process('test');

    expect($result)->toBe('processed: test [modified]')
        ->and(CompleteFlowService::$callLog)->toBe([
            'CompleteFlowBeforePlugin::beforeProcess(test)',
            'CompleteFlowService::process(test)',
            'CompleteFlowAfterPlugin::afterProcess(processed: test, test)',
        ]);
});

// Test fixture for no plugins service
class NoPluginsService
{
    public static array $callLog = [];

    public function doSomething(): string
    {
        self::$callLog[] = 'NoPluginsService::doSomething';

        return 'result';
    }

    public function anotherMethod(int $value): int
    {
        self::$callLog[] = "NoPluginsService::anotherMethod($value)";

        return $value * 2;
    }
}

it('handles methods with no plugins without overhead', function (): void {
    NoPluginsService::$callLog = [];

    $container = new Container();
    $registry = new PluginRegistry();

    // No plugins registered for NoPluginsService
    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(NoPluginsService::class, new NoPluginsService());

    // Call methods that have no plugins
    $result1 = $proxy->doSomething();
    $result2 = $proxy->anotherMethod(5);

    expect($result1)->toBe('result')
        ->and($result2)->toBe(10)
        ->and(NoPluginsService::$callLog)->toBe([
            'NoPluginsService::doSomething',
            'NoPluginsService::anotherMethod(5)',
        ]);
});

// Test fixtures for dependency injection
class PluginLoggerDependency
{
    public static array $messages = [];

    public function log(string $message): void
    {
        self::$messages[] = $message;
    }
}

class DependencyInjectionService
{
    public static array $callLog = [];

    public function save(string $data): string
    {
        self::$callLog[] = "DependencyInjectionService::save($data)";

        return "saved: $data";
    }
}

class PluginWithDependency
{
    public function __construct(
        private PluginLoggerDependency $logger,
    ) {}

    public function beforeSave(string $data): ?string
    {
        $this->logger->log("About to save: $data");
        DependencyInjectionService::$callLog[] = 'PluginWithDependency::beforeSave';

        return null;
    }

    public function afterSave(
        mixed $result,
        string $data,
    ): mixed {
        $this->logger->log("Saved successfully: $data");
        DependencyInjectionService::$callLog[] = 'PluginWithDependency::afterSave';

        return $result;
    }
}

it('injects plugin dependencies via container', function (): void {
    DependencyInjectionService::$callLog = [];
    PluginLoggerDependency::$messages = [];

    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PluginWithDependency::class,
        targetClass: DependencyInjectionService::class,
        beforeMethods: ['beforeSave' => 10],
        afterMethods: ['afterSave' => 10],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(DependencyInjectionService::class, new DependencyInjectionService());

    $result = $proxy->save('test data');

    // Verify the plugin received its dependency and used it
    expect(PluginLoggerDependency::$messages)->toBe([
        'About to save: test data',
        'Saved successfully: test data',
    ])
        ->and(DependencyInjectionService::$callLog)->toBe([
            'PluginWithDependency::beforeSave',
            'DependencyInjectionService::save(test data)',
            'PluginWithDependency::afterSave',
        ])
        ->and($result)->toBe('saved: test data');
});

use Marko\Core\Plugin\PluginProxy;

// Test fixture for conditional proxy creation
class ProxyCheckService
{
    public function doSomething(): string
    {
        return 'done';
    }
}

class ProxyCheckServicePlugin
{
    public function beforeDoSomething(): ?string
    {
        return null;
    }
}

it('creates proxy only for classes with registered plugins', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    // Register a plugin for ProxyCheckService
    $registry->register(new PluginDefinition(
        pluginClass: ProxyCheckServicePlugin::class,
        targetClass: ProxyCheckService::class,
        beforeMethods: ['beforeDoSomething' => 10],
    ));

    $interceptor = new PluginInterceptor($container, $registry);

    // Class WITH plugins should get a proxy
    $targetWithPlugins = new ProxyCheckService();
    $proxyWithPlugins = $interceptor->createProxy(ProxyCheckService::class, $targetWithPlugins);
    expect($proxyWithPlugins)->toBeInstanceOf(PluginProxy::class)
        ->and($proxyWithPlugins)->not->toBe($targetWithPlugins);

    // Class WITHOUT plugins should return the original instance (no proxy)
    $targetWithoutPlugins = new NoPluginsService();
    $result = $interceptor->createProxy(NoPluginsService::class, $targetWithoutPlugins);
    expect($result)->toBe($targetWithoutPlugins)
        ->and($result)->not->toBeInstanceOf(PluginProxy::class);
});
