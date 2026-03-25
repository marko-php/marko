<?php

declare(strict_types=1);

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Container\Container;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginProxy;
use Marko\Core\Plugin\PluginArgumentCountException;
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
    public function doAction(): ?string
    {
        InterceptorTargetService::$callLog[] = 'FirstBeforePlugin::doAction';

        return null;
    }
}

class SecondBeforePlugin
{
    public function doAction(): ?string
    {
        InterceptorTargetService::$callLog[] = 'SecondBeforePlugin::doAction';

        return null;
    }
}

beforeEach(function (): void {
    InterceptorTargetService::$callLog = [];
});

it('executes before plugins with method names matching target method', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    // Register plugins with different sort orders (lower runs first)
    $registry->register(new PluginDefinition(
        pluginClass: SecondBeforePlugin::class,
        targetClass: InterceptorTargetService::class,
        beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 20]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: FirstBeforePlugin::class,
        targetClass: InterceptorTargetService::class,
        beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 10]],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(InterceptorTargetService::class, new InterceptorTargetService());

    $result = $proxy->doAction();

    // Verify execution order: before plugins (in sort order), then target
    expect(InterceptorTargetService::$callLog)->toBe([
        'FirstBeforePlugin::doAction',
        'SecondBeforePlugin::doAction',
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

    /** @noinspection PhpUnused - Invoked via reflection */
    public function process(
        string $name,
        int $count,
    ): ?string {
        self::$receivedArgs = ['name' => $name, 'count' => $count];
        ServiceWithArgs::$callLog[] = "ArgLoggingPlugin::process($name, $count)";

        return null;
    }
}

it('passes method arguments to before plugins with new naming', function (): void {
    ServiceWithArgs::$callLog = [];
    ArgLoggingPlugin::$receivedArgs = [];

    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: ArgLoggingPlugin::class,
        targetClass: ServiceWithArgs::class,
        beforeMethods: ['process' => ['pluginMethod' => 'process', 'sortOrder' => 10]],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(ServiceWithArgs::class, new ServiceWithArgs());

    $result = $proxy->process('test', 42);

    expect(ArgLoggingPlugin::$receivedArgs)->toBe(['name' => 'test', 'count' => 42])
        ->and(ServiceWithArgs::$callLog)->toBe([
            'ArgLoggingPlugin::process(test, 42)',
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
    public function doAction(): ?string
    {
        ShortCircuitService::$callLog[] = 'ShortCircuitPlugin::doAction';

        return 'short-circuited';
    }
}

class SkippedPlugin
{
    public function doAction(): ?string
    {
        ShortCircuitService::$callLog[] = 'SkippedPlugin::doAction';

        return null;
    }
}

it('short-circuits when before plugin returns non-null with new naming', function (): void {
    ShortCircuitService::$callLog = [];

    $container = new Container();
    $registry = new PluginRegistry();

    // First plugin passes through
    $registry->register(new PluginDefinition(
        pluginClass: SkippedPlugin::class,
        targetClass: ShortCircuitService::class,
        beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 30]],
    ));

    // Second plugin short-circuits
    $registry->register(new PluginDefinition(
        pluginClass: ShortCircuitPlugin::class,
        targetClass: ShortCircuitService::class,
        beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 10]],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(ShortCircuitService::class, new ShortCircuitService());

    $result = $proxy->doAction();

    // Should only run the short-circuiting plugin, skip the rest and target
    expect(ShortCircuitService::$callLog)->toBe([
        'ShortCircuitPlugin::doAction',
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
    public function doAction(): ?string
    {
        PassThroughService::$callLog[] = 'PassThroughPluginA::doAction';

        return null;
    }
}

class PassThroughPluginB
{
    public function doAction(): ?string
    {
        PassThroughService::$callLog[] = 'PassThroughPluginB::doAction';

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
        beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 10]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: PassThroughPluginB::class,
        targetClass: PassThroughService::class,
        beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 20]],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PassThroughService::class, new PassThroughService());

    $result = $proxy->doAction();

    // All plugins pass through, target method executes
    expect(PassThroughService::$callLog)->toBe([
        'PassThroughPluginA::doAction',
        'PassThroughPluginB::doAction',
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
    public function doAction(
        mixed $result,
    ): mixed {
        AfterTargetService::$callLog[] = 'FirstAfterPlugin::doAction';

        return $result;
    }
}

class SecondAfterPlugin
{
    public function doAction(
        mixed $result,
    ): mixed {
        AfterTargetService::$callLog[] = 'SecondAfterPlugin::doAction';

        return $result;
    }
}

it('executes after plugins with method names matching target method', function (): void {
    AfterTargetService::$callLog = [];

    $container = new Container();
    $registry = new PluginRegistry();

    // Register in reverse order to verify sorting
    $registry->register(new PluginDefinition(
        pluginClass: SecondAfterPlugin::class,
        targetClass: AfterTargetService::class,
        afterMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 20]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: FirstAfterPlugin::class,
        targetClass: AfterTargetService::class,
        afterMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 10]],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(AfterTargetService::class, new AfterTargetService());

    $result = $proxy->doAction();

    // Verify execution order: target, then after plugins in sort order
    expect(AfterTargetService::$callLog)->toBe([
        'AfterTargetService::doAction',
        'FirstAfterPlugin::doAction',
        'SecondAfterPlugin::doAction',
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

    /** @noinspection PhpUnused - Invoked via reflection */
    public function calculate(
        mixed $result,
        int $a,
        int $b,
    ): mixed {
        self::$receivedArgs = ['result' => $result, 'a' => $a, 'b' => $b];
        AfterServiceWithArgs::$callLog[] = "AfterArgInspectorPlugin::calculate(result=$result, a=$a, b=$b)";

        return $result;
    }
}

it('passes result and arguments to after plugins with new naming', function (): void {
    AfterServiceWithArgs::$callLog = [];
    AfterArgInspectorPlugin::$receivedArgs = [];

    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: AfterArgInspectorPlugin::class,
        targetClass: AfterServiceWithArgs::class,
        afterMethods: ['calculate' => ['pluginMethod' => 'calculate', 'sortOrder' => 10]],
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
            'AfterArgInspectorPlugin::calculate(result=8, a=5, b=3)',
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

    /** @noinspection PhpUnused - Invoked via reflection */
    public function getValue(
        mixed $result,
    ): int {
        self::$receivedResult = $result;
        ModifyResultService::$callLog[] = "DoublerPlugin::getValue(received=$result)";

        return $result * 2;
    }
}

class AdderPlugin
{
    public static ?int $receivedResult = null;

    /** @noinspection PhpUnused - Invoked via reflection */
    public function getValue(
        mixed $result,
    ): int {
        self::$receivedResult = $result;
        ModifyResultService::$callLog[] = "AdderPlugin::getValue(received=$result)";

        return $result + 5;
    }
}

it('chains modified results through after plugins with new naming', function (): void {
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
        afterMethods: ['getValue' => ['pluginMethod' => 'getValue', 'sortOrder' => 10]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: AdderPlugin::class,
        targetClass: ModifyResultService::class,
        afterMethods: ['getValue' => ['pluginMethod' => 'getValue', 'sortOrder' => 20]],
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
            'DoublerPlugin::getValue(received=10)',
            'AdderPlugin::getValue(received=20)',
        ]);
});

// Test fixture for complete flow
class CompleteFlowService
{
    public static array $callLog = [];

    public function process(
        string $input,
    ): string {
        self::$callLog[] = "CompleteFlowService::process($input)";

        return "processed: $input";
    }
}

class CompleteFlowBeforePlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function process(
        string $input,
    ): ?string {
        CompleteFlowService::$callLog[] = "CompleteFlowBeforePlugin::process($input)";

        return null;
    }
}

class CompleteFlowAfterPlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function process(
        mixed $result,
        string $input,
    ): string {
        CompleteFlowService::$callLog[] = "CompleteFlowAfterPlugin::process($result, $input)";

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
        beforeMethods: ['process' => ['pluginMethod' => 'process', 'sortOrder' => 10]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: CompleteFlowAfterPlugin::class,
        targetClass: CompleteFlowService::class,
        afterMethods: ['process' => ['pluginMethod' => 'process', 'sortOrder' => 10]],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(CompleteFlowService::class, new CompleteFlowService());

    $result = $proxy->process('test');

    expect($result)->toBe('processed: test [modified]')
        ->and(CompleteFlowService::$callLog)->toBe([
            'CompleteFlowBeforePlugin::process(test)',
            'CompleteFlowService::process(test)',
            'CompleteFlowAfterPlugin::process(processed: test, test)',
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

    public function anotherMethod(
        int $value,
    ): int {
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

    public function log(
        string $message,
    ): void {
        self::$messages[] = $message;
    }
}

class DependencyInjectionService
{
    public static array $callLog = [];

    public function save(
        string $data,
    ): string {
        self::$callLog[] = "DependencyInjectionService::save($data)";

        return "saved: $data";
    }
}

readonly class PluginWithDependency
{
    public function __construct(
        private PluginLoggerDependency $logger,
    ) {}

    /** @noinspection PhpUnused - Invoked via reflection */
    #[Before(method: 'save')]
    public function logBeforeSave(
        string $data,
    ): ?string {
        $this->logger->log("About to save: $data");
        DependencyInjectionService::$callLog[] = 'PluginWithDependency::logBeforeSave';

        return null;
    }

    /** @noinspection PhpUnused - Invoked via reflection */
    #[After(method: 'save')]
    public function logAfterSave(
        mixed $result,
        string $data,
    ): mixed {
        $this->logger->log("Saved successfully: $data");
        DependencyInjectionService::$callLog[] = 'PluginWithDependency::logAfterSave';

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
        beforeMethods: ['save' => ['pluginMethod' => 'logBeforeSave', 'sortOrder' => 10]],
        afterMethods: ['save' => ['pluginMethod' => 'logAfterSave', 'sortOrder' => 10]],
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
            'PluginWithDependency::logBeforeSave',
            'DependencyInjectionService::save(test data)',
            'PluginWithDependency::logAfterSave',
        ])
        ->and($result)->toBe('saved: test data');
});

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
    public function doSomething(): ?string
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
        beforeMethods: ['doSomething' => ['pluginMethod' => 'doSomething', 'sortOrder' => 10]],
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

// Test fixtures for plugin method name correctness
class RegistryMethodNameTargetService
{
    public static array $callLog = [];

    public function save(string $data): string
    {
        self::$callLog[] = "RegistryMethodNameTargetService::save($data)";

        return "saved: $data";
    }
}

class RegistryMethodNamePlugin
{
    public static array $callLog = [];

    public function save(string $data): ?string
    {
        self::$callLog[] = "RegistryMethodNamePlugin::save($data)";

        return null;
    }
}

it('calls plugin method by name returned from registry', function (): void {
    RegistryMethodNameTargetService::$callLog = [];
    RegistryMethodNamePlugin::$callLog = [];

    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: RegistryMethodNamePlugin::class,
        targetClass: RegistryMethodNameTargetService::class,
        beforeMethods: ['save' => ['pluginMethod' => 'save', 'sortOrder' => 10]],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(RegistryMethodNameTargetService::class, new RegistryMethodNameTargetService());

    $result = $proxy->save('hello');

    expect(RegistryMethodNamePlugin::$callLog)->toBe(['RegistryMethodNamePlugin::save(hello)'])
        ->and(RegistryMethodNameTargetService::$callLog)->toBe(['RegistryMethodNameTargetService::save(hello)'])
        ->and($result)->toBe('saved: hello');
});

// Test fixtures for explicit method param (plugin method differs from target method)
class ExplicitMethodParamTargetService
{
    public static array $callLog = [];

    public function save(string $data): string
    {
        self::$callLog[] = "ExplicitMethodParamTargetService::save($data)";

        return "saved: $data";
    }
}

class ExplicitMethodParamPlugin
{
    public static array $callLog = [];

    public function validateInput(string $data): ?string
    {
        self::$callLog[] = "ExplicitMethodParamPlugin::validateInput($data)";

        return null;
    }
}

it('executes plugins using explicit method param with different method names', function (): void {
    ExplicitMethodParamTargetService::$callLog = [];
    ExplicitMethodParamPlugin::$callLog = [];

    $container = new Container();
    $registry = new PluginRegistry();

    // Plugin method 'validateInput' intercepts target method 'save'
    $registry->register(new PluginDefinition(
        pluginClass: ExplicitMethodParamPlugin::class,
        targetClass: ExplicitMethodParamTargetService::class,
        beforeMethods: ['save' => ['pluginMethod' => 'validateInput', 'sortOrder' => 10]],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(
        ExplicitMethodParamTargetService::class,
        new ExplicitMethodParamTargetService()
    );

    $result = $proxy->save('test data');

    // validateInput (not save) should be called on the plugin
    expect(ExplicitMethodParamPlugin::$callLog)->toBe(['ExplicitMethodParamPlugin::validateInput(test data)'])
        ->and(ExplicitMethodParamTargetService::$callLog)->toBe(['ExplicitMethodParamTargetService::save(test data)'])
        ->and($result)->toBe('saved: test data');
});

// ---- Argument modification tests ----

class ArgModService
{
    public function process(
        string $name,
        int $count,
    ): string {
        return "processed: $name, $count";
    }
}

class ArgModifyingBeforePlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function process(
        string $name,
        int $count,
    ): ?array {
        return ['modified-name', $count + 10];
    }
}

it('modifies arguments when before plugin returns an array', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: ArgModifyingBeforePlugin::class,
        targetClass: ArgModService::class,
        beforeMethods: ['process' => ['pluginMethod' => 'process', 'sortOrder' => 10]],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(ArgModService::class, new ArgModService());

    $result = $proxy->process('original', 5);

    expect($result)->toBe('processed: modified-name, 15');
});

class ArgPassTargetService
{
    public static array $receivedArgs = [];

    public function compute(
        string $label,
        int $value,
    ): string {
        self::$receivedArgs = ['label' => $label, 'value' => $value];

        return "computed: $label=$value";
    }
}

class ArgPassBeforePlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function compute(
        string $label,
        int $value,
    ): ?array {
        return ['new-label', $value * 2];
    }
}

it('passes modified arguments to the target method', function (): void {
    ArgPassTargetService::$receivedArgs = [];

    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: ArgPassBeforePlugin::class,
        targetClass: ArgPassTargetService::class,
        beforeMethods: ['compute' => ['pluginMethod' => 'compute', 'sortOrder' => 10]],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(ArgPassTargetService::class, new ArgPassTargetService());

    $proxy->compute('original', 7);

    expect(ArgPassTargetService::$receivedArgs)->toBe(['label' => 'new-label', 'value' => 14]);
});

class ChainArgService
{
    public function transform(
        string $text,
        int $multiplier,
    ): string {
        return "result: $text x$multiplier";
    }
}

class ChainArgPluginFirst
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function transform(
        string $text,
        int $multiplier,
    ): ?array {
        return ["$text-first", $multiplier + 1];
    }
}

class ChainArgPluginSecond
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function transform(
        string $text,
        int $multiplier,
    ): ?array {
        return ["$text-second", $multiplier + 1];
    }
}

it('chains argument modifications through multiple before plugins', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: ChainArgPluginFirst::class,
        targetClass: ChainArgService::class,
        beforeMethods: ['transform' => ['pluginMethod' => 'transform', 'sortOrder' => 10]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: ChainArgPluginSecond::class,
        targetClass: ChainArgService::class,
        beforeMethods: ['transform' => ['pluginMethod' => 'transform', 'sortOrder' => 20]],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(ChainArgService::class, new ChainArgService());

    $result = $proxy->transform('hello', 1);

    // First plugin: ['hello-first', 2], Second plugin: ['hello-first-second', 3]
    expect($result)->toBe('result: hello-first-second x3');
});

class AfterModArgsService
{
    public function run(
        string $input,
        int $times,
    ): string {
        return "ran: $input x$times";
    }
}

class AfterModArgsBeforePlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function run(
        string $input,
        int $times,
    ): ?array {
        return ['modified-input', $times + 5];
    }
}

class AfterModArgsAfterPlugin
{
    public static array $receivedArgs = [];

    /** @noinspection PhpUnused - Invoked via reflection */
    public function run(
        mixed $result,
        string $input,
        int $times,
    ): mixed {
        self::$receivedArgs = ['input' => $input, 'times' => $times];

        return $result;
    }
}

it('passes modified arguments to after plugins', function (): void {
    AfterModArgsAfterPlugin::$receivedArgs = [];

    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: AfterModArgsBeforePlugin::class,
        targetClass: AfterModArgsService::class,
        beforeMethods: ['run' => ['pluginMethod' => 'run', 'sortOrder' => 10]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: AfterModArgsAfterPlugin::class,
        targetClass: AfterModArgsService::class,
        afterMethods: ['run' => ['pluginMethod' => 'run', 'sortOrder' => 10]],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(AfterModArgsService::class, new AfterModArgsService());

    $proxy->run('original', 1);

    // After plugin should receive the modified arguments, not the originals
    expect(AfterModArgsAfterPlugin::$receivedArgs)->toBe(['input' => 'modified-input', 'times' => 6]);
});

class StillShortCircuitService
{
    public static bool $called = false;

    public function fetch(string $key): string
    {
        self::$called = true;

        return "fetched: $key";
    }
}

class StillShortCircuitPlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function fetch(string $key): string
    {
        return 'short-circuited';
    }
}

it('still short-circuits when before plugin returns non-null non-array', function (): void {
    StillShortCircuitService::$called = false;

    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: StillShortCircuitPlugin::class,
        targetClass: StillShortCircuitService::class,
        beforeMethods: ['fetch' => ['pluginMethod' => 'fetch', 'sortOrder' => 10]],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(StillShortCircuitService::class, new StillShortCircuitService());

    $result = $proxy->fetch('mykey');

    expect($result)->toBe('short-circuited')
        ->and(StillShortCircuitService::$called)->toBeFalse();
});

class PassThroughNullService
{
    public static bool $called = false;

    public function execute(string $input): string
    {
        self::$called = true;

        return "executed: $input";
    }
}

class PassThroughNullPlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function execute(string $input): ?string
    {
        return null;
    }
}

it('still passes through when before plugin returns null', function (): void {
    PassThroughNullService::$called = false;

    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PassThroughNullPlugin::class,
        targetClass: PassThroughNullService::class,
        beforeMethods: ['execute' => ['pluginMethod' => 'execute', 'sortOrder' => 10]],
    ));

    $interceptor = new PluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PassThroughNullService::class, new PassThroughNullService());

    $result = $proxy->execute('hello');

    expect($result)->toBe('executed: hello')
        ->and(PassThroughNullService::$called)->toBeTrue();
});

class RequiredParamsService
{
    public function create(
        string $name,
        int $age,
    ): string
    {
        return "$name is $age";
    }
}

class EmptyArrayBeforePlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function create(
        string $name,
        int $age,
    ): ?array
    {
        return [];
    }
}

it(
    'throws PluginArgumentCountException when before plugin returns empty array and target has required params',
    function (): void {
        $container = new Container();
        $registry = new PluginRegistry();
    
        $registry->register(new PluginDefinition(
            pluginClass: EmptyArrayBeforePlugin::class,
            targetClass: RequiredParamsService::class,
            beforeMethods: ['create' => ['pluginMethod' => 'create', 'sortOrder' => 10]],
        ));
    
        $interceptor = new PluginInterceptor($container, $registry);
        $proxy = $interceptor->createProxy(RequiredParamsService::class, new RequiredParamsService());
    
        expect(fn() => $proxy->create('Alice', 30))
            ->toThrow(PluginArgumentCountException::class);
    }
);

class WrongCountService
{
    public function charge(
        string $card,
        int $amount,
    ): string
    {
        return "charged $card: $amount";
    }
}

class WrongCountBeforePlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function charge(
        string $card,
        int $amount,
    ): ?array
    {
        return ['card1', 100, 'extra-arg'];
    }
}

it(
    'throws PluginArgumentCountException when before plugin returns array with wrong argument count',
    function (): void {
        $container = new Container();
        $registry = new PluginRegistry();
    
        $registry->register(new PluginDefinition(
            pluginClass: WrongCountBeforePlugin::class,
            targetClass: WrongCountService::class,
            beforeMethods: ['charge' => ['pluginMethod' => 'charge', 'sortOrder' => 10]],
        ));
    
        $interceptor = new PluginInterceptor($container, $registry);
        $proxy = $interceptor->createProxy(WrongCountService::class, new WrongCountService());
    
        expect(fn() => $proxy->charge('visa', 50))
            ->toThrow(PluginArgumentCountException::class);
    }
);

class ExceptionMessageService
{
    public function process(
        string $input,
        int $count,
    ): string
    {
        return "$input x$count";
    }
}

class ExceptionMessagePlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function process(
        string $input,
        int $count,
    ): ?array
    {
        return ['only-one'];
    }
}

it(
    'includes plugin class name, target method, expected and actual counts in the exception message',
    function (): void {
        $container = new Container();
        $registry = new PluginRegistry();
    
        $registry->register(new PluginDefinition(
            pluginClass: ExceptionMessagePlugin::class,
            targetClass: ExceptionMessageService::class,
            beforeMethods: ['process' => ['pluginMethod' => 'process', 'sortOrder' => 10]],
        ));
    
        $interceptor = new PluginInterceptor($container, $registry);
        $proxy = $interceptor->createProxy(ExceptionMessageService::class, new ExceptionMessageService());
    
        try {
            $proxy->process('hello', 5);
            expect(false)->toBeTrue('Exception was not thrown');
        } catch (PluginArgumentCountException $e) {
            expect($e->getMessage())
                ->toContain('ExceptionMessagePlugin')
                ->toContain('ExceptionMessageService')
                ->toContain('process')
                ->toContain('2')  // expected
                ->toContain('1'); // actual
        }
    }
);
