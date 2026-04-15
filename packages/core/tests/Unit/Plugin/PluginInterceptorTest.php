<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginArgumentCountException;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;

// All fixture classes are prefixed with PIT_ to avoid conflicts with other test files.

// ---------------------------------------------------------------------------
// Interface fixtures
// ---------------------------------------------------------------------------

interface PIT_GreeterInterface
{
    public function greet(string $name): string;
}

class PIT_ConcreteGreeter implements PIT_GreeterInterface
{
    public static array $callLog = [];

    public function greet(string $name): string
    {
        self::$callLog[] = "PIT_ConcreteGreeter::greet($name)";

        return "Hello, $name!";
    }
}

class PIT_GreeterBeforePlugin
{
    public static array $callLog = [];

    public function greet(string $name): ?string
    {
        self::$callLog[] = "PIT_GreeterBeforePlugin::greet($name)";

        return null;
    }
}

class PIT_GreeterAfterPlugin
{
    public static array $callLog = [];

    public function greet(mixed $result, string $name): mixed
    {
        self::$callLog[] = 'PIT_GreeterAfterPlugin::greet';

        return strtoupper((string) $result);
    }
}

// ---------------------------------------------------------------------------
// Simple concrete service fixtures
// ---------------------------------------------------------------------------

class PIT_SimpleService
{
    public static array $callLog = [];

    public function doAction(): string
    {
        self::$callLog[] = 'PIT_SimpleService::doAction';

        return 'original result';
    }
}

class PIT_SimpleBeforePlugin
{
    public static array $callLog = [];

    public function doAction(): ?string
    {
        self::$callLog[] = 'PIT_SimpleBeforePlugin::doAction';

        return null;
    }
}

class PIT_SimpleSecondBeforePlugin
{
    public static array $callLog = [];

    public function doAction(): ?string
    {
        self::$callLog[] = 'PIT_SimpleSecondBeforePlugin::doAction';

        return null;
    }
}

class PIT_SimpleAfterPlugin
{
    public static array $callLog = [];

    public function doAction(mixed $result): mixed
    {
        self::$callLog[] = 'PIT_SimpleAfterPlugin::doAction';

        return $result;
    }
}

class PIT_SimpleSecondAfterPlugin
{
    public static array $callLog = [];

    public function doAction(mixed $result): mixed
    {
        self::$callLog[] = 'PIT_SimpleSecondAfterPlugin::doAction';

        return $result;
    }
}

// ---------------------------------------------------------------------------
// Argument test fixtures
// ---------------------------------------------------------------------------

class PIT_ArgsService
{
    public static array $callLog = [];

    public function process(string $name, int $count): string
    {
        self::$callLog[] = "PIT_ArgsService::process($name, $count)";

        return "processed: $name, $count";
    }
}

class PIT_ArgsBeforePlugin
{
    public static array $receivedArgs = [];

    public function process(string $name, int $count): ?string
    {
        self::$receivedArgs = ['name' => $name, 'count' => $count];
        PIT_ArgsService::$callLog[] = "PIT_ArgsBeforePlugin::process($name, $count)";

        return null;
    }
}

class PIT_ArgsModifyingBeforePlugin
{
    public function process(string $name, int $count): ?array
    {
        return ['modified-name', $count + 10];
    }
}

class PIT_ArgsAfterPlugin
{
    public static array $receivedArgs = [];

    public function process(mixed $result, string $name, int $count): mixed
    {
        self::$receivedArgs = ['result' => $result, 'name' => $name, 'count' => $count];

        return $result;
    }
}

// ---------------------------------------------------------------------------
// Short-circuit fixtures
// ---------------------------------------------------------------------------

class PIT_ShortCircuitService
{
    public static bool $called = false;

    public function fetch(string $key): string
    {
        self::$called = true;

        return "fetched: $key";
    }
}

class PIT_ShortCircuitBeforePlugin
{
    public function fetch(string $key): string
    {
        return 'short-circuited';
    }
}

class PIT_PassThroughBeforePlugin
{
    public function fetch(string $key): ?string
    {
        return null;
    }
}

// ---------------------------------------------------------------------------
// After plugin fixtures
// ---------------------------------------------------------------------------

class PIT_ResultModService
{
    public function getValue(): int
    {
        return 10;
    }
}

class PIT_DoublerAfterPlugin
{
    public static ?int $received = null;

    public function getValue(mixed $result): int
    {
        self::$received = $result;

        return $result * 2;
    }
}

class PIT_AdderAfterPlugin
{
    public static ?int $received = null;

    public function getValue(mixed $result): int
    {
        self::$received = $result;

        return $result + 5;
    }
}

// ---------------------------------------------------------------------------
// Argument count exception fixtures
// ---------------------------------------------------------------------------

class PIT_RequiredParamService
{
    public function create(string $name, int $age): string
    {
        return "$name is $age";
    }
}

class PIT_WrongCountBeforePlugin
{
    public function create(string $name, int $age): ?array
    {
        return ['only-one'];
    }
}

// ---------------------------------------------------------------------------
// Chain arg modification fixtures
// ---------------------------------------------------------------------------

class PIT_ChainArgService
{
    public function transform(string $text, int $mult): string
    {
        return "result: $text x$mult";
    }
}

class PIT_ChainArgFirstPlugin
{
    public function transform(string $text, int $mult): ?array
    {
        return ["$text-first", $mult + 1];
    }
}

class PIT_ChainArgSecondPlugin
{
    public function transform(string $text, int $mult): ?array
    {
        return ["$text-second", $mult + 1];
    }
}

// ---------------------------------------------------------------------------
// Complete flow fixtures
// ---------------------------------------------------------------------------

class PIT_CompleteFlowService
{
    public static array $callLog = [];

    public function process(string $input): string
    {
        self::$callLog[] = "PIT_CompleteFlowService::process($input)";

        return "processed: $input";
    }
}

class PIT_CompleteFlowBeforePlugin
{
    public function process(string $input): ?string
    {
        PIT_CompleteFlowService::$callLog[] = "PIT_CompleteFlowBeforePlugin::process($input)";

        return null;
    }
}

class PIT_CompleteFlowAfterPlugin
{
    public function process(mixed $result, string $input): string
    {
        PIT_CompleteFlowService::$callLog[] = "PIT_CompleteFlowAfterPlugin::process($result, $input)";

        return "$result [modified]";
    }
}

// ---------------------------------------------------------------------------
// No-plugins fixtures
// ---------------------------------------------------------------------------

class PIT_NoPluginsService
{
    public static array $callLog = [];

    public function doSomething(): string
    {
        self::$callLog[] = 'PIT_NoPluginsService::doSomething';

        return 'result';
    }

    public function anotherMethod(int $value): int
    {
        self::$callLog[] = "PIT_NoPluginsService::anotherMethod($value)";

        return $value * 2;
    }
}

// ---------------------------------------------------------------------------
// Dependency injection fixtures
// ---------------------------------------------------------------------------

class PIT_LoggerDep
{
    public static array $messages = [];

    public function log(string $message): void
    {
        self::$messages[] = $message;
    }
}

class PIT_DISaveService
{
    public static array $callLog = [];

    public function save(string $data): string
    {
        self::$callLog[] = "PIT_DISaveService::save($data)";

        return "saved: $data";
    }
}

readonly class PIT_DIPlugin
{
    public function __construct(
        private PIT_LoggerDep $logger,
    ) {}

    public function save(string $data): ?string
    {
        $this->logger->log("About to save: $data");
        PIT_DISaveService::$callLog[] = 'PIT_DIPlugin::save';

        return null;
    }
}

// ---------------------------------------------------------------------------
// Method name fixtures
// ---------------------------------------------------------------------------

class PIT_MethodNameService
{
    public static array $callLog = [];

    public function save(string $data): string
    {
        self::$callLog[] = "PIT_MethodNameService::save($data)";

        return "saved: $data";
    }
}

class PIT_ExplicitMethodPlugin
{
    public static array $callLog = [];

    public function validateInput(string $data): ?string
    {
        self::$callLog[] = "PIT_ExplicitMethodPlugin::validateInput($data)";

        return null;
    }
}

// ---------------------------------------------------------------------------
// Interface-to-concrete resolution fixtures
// ---------------------------------------------------------------------------

interface PIT_HasherInterface
{
    public function hash(string $value): string;
}

class PIT_BcryptHasher implements PIT_HasherInterface
{
    public static array $callLog = [];

    public function hash(string $value): string
    {
        self::$callLog[] = "PIT_BcryptHasher::hash($value)";

        return "bcrypt:$value";
    }
}

class PIT_HasherPlugin
{
    public static array $callLog = [];

    public function hash(string $value): ?string
    {
        self::$callLog[] = "PIT_HasherPlugin::hash($value)";

        return null;
    }
}

// ---------------------------------------------------------------------------
// Readonly class fixture
// ---------------------------------------------------------------------------

readonly class PIT_ReadonlyService
{
    public function doWork(): string
    {
        return 'work done';
    }
}

class PIT_ReadonlyServicePlugin
{
    public function doWork(): ?string
    {
        return null;
    }
}

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function makePluginInterceptor(Container $container, PluginRegistry $registry): PluginInterceptor
{
    return new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
}

// ---------------------------------------------------------------------------
// Reset static state before each test
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    PIT_ConcreteGreeter::$callLog = [];
    PIT_GreeterBeforePlugin::$callLog = [];
    PIT_GreeterAfterPlugin::$callLog = [];
    PIT_SimpleService::$callLog = [];
    PIT_SimpleBeforePlugin::$callLog = [];
    PIT_SimpleSecondBeforePlugin::$callLog = [];
    PIT_SimpleAfterPlugin::$callLog = [];
    PIT_SimpleSecondAfterPlugin::$callLog = [];
    PIT_ArgsService::$callLog = [];
    PIT_ArgsBeforePlugin::$receivedArgs = [];
    PIT_ArgsAfterPlugin::$receivedArgs = [];
    PIT_ShortCircuitService::$called = false;
    PIT_DoublerAfterPlugin::$received = null;
    PIT_AdderAfterPlugin::$received = null;
    PIT_CompleteFlowService::$callLog = [];
    PIT_NoPluginsService::$callLog = [];
    PIT_LoggerDep::$messages = [];
    PIT_DISaveService::$callLog = [];
    PIT_MethodNameService::$callLog = [];
    PIT_ExplicitMethodPlugin::$callLog = [];
    PIT_BcryptHasher::$callLog = [];
    PIT_HasherPlugin::$callLog = [];
});

// ===========================================================================
// TESTS
// ===========================================================================

it('returns original instance when no plugins exist for the target', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();
    $interceptor = makePluginInterceptor($container, $registry);

    $target = new PIT_NoPluginsService();
    $result = $interceptor->createProxy(PIT_NoPluginsService::class, PIT_NoPluginsService::class, $target);

    expect($result)->toBe($target);
});

it('creates interceptor implementing interface when plugin targets an interface', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_GreeterBeforePlugin::class,
        targetClass: PIT_GreeterInterface::class,
        beforeMethods: ['greet' => ['pluginMethod' => 'greet', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $target = new PIT_ConcreteGreeter();
    $proxy = $interceptor->createProxy(PIT_GreeterInterface::class, PIT_ConcreteGreeter::class, $target);

    expect($proxy)->toBeInstanceOf(PIT_GreeterInterface::class)
        ->and($proxy)->not->toBe($target);
});

it('creates interceptor that passes instanceof check for target interface', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_GreeterBeforePlugin::class,
        targetClass: PIT_GreeterInterface::class,
        beforeMethods: ['greet' => ['pluginMethod' => 'greet', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_GreeterInterface::class, PIT_ConcreteGreeter::class, new PIT_ConcreteGreeter());

    expect($proxy)->toBeInstanceOf(PIT_GreeterInterface::class);
});

it('executes before plugins in sort order then calls target method', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_SimpleSecondBeforePlugin::class,
        targetClass: PIT_SimpleService::class,
        beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 20]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: PIT_SimpleBeforePlugin::class,
        targetClass: PIT_SimpleService::class,
        beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_SimpleService::class, PIT_SimpleService::class, new PIT_SimpleService());

    $result = $proxy->doAction();

    expect(PIT_SimpleBeforePlugin::$callLog)->toBe(['PIT_SimpleBeforePlugin::doAction'])
        ->and(PIT_SimpleSecondBeforePlugin::$callLog)->toBe(['PIT_SimpleSecondBeforePlugin::doAction'])
        ->and(PIT_SimpleService::$callLog)->toBe(['PIT_SimpleService::doAction'])
        ->and($result)->toBe('original result');
});

it('passes method arguments to before plugins', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_ArgsBeforePlugin::class,
        targetClass: PIT_ArgsService::class,
        beforeMethods: ['process' => ['pluginMethod' => 'process', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_ArgsService::class, PIT_ArgsService::class, new PIT_ArgsService());

    $result = $proxy->process('test', 42);

    expect(PIT_ArgsBeforePlugin::$receivedArgs)->toBe(['name' => 'test', 'count' => 42])
        ->and($result)->toBe('processed: test, 42');
});

it('short-circuits when before plugin returns non-null non-array value', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_ShortCircuitBeforePlugin::class,
        targetClass: PIT_ShortCircuitService::class,
        beforeMethods: ['fetch' => ['pluginMethod' => 'fetch', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_ShortCircuitService::class, PIT_ShortCircuitService::class, new PIT_ShortCircuitService());

    $result = $proxy->fetch('mykey');

    expect($result)->toBe('short-circuited')
        ->and(PIT_ShortCircuitService::$called)->toBeFalse();
});

it('passes through to target method when before plugin returns null', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_PassThroughBeforePlugin::class,
        targetClass: PIT_ShortCircuitService::class,
        beforeMethods: ['fetch' => ['pluginMethod' => 'fetch', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_ShortCircuitService::class, PIT_ShortCircuitService::class, new PIT_ShortCircuitService());

    $result = $proxy->fetch('mykey');

    expect($result)->toBe('fetched: mykey')
        ->and(PIT_ShortCircuitService::$called)->toBeTrue();
});

it('modifies arguments when before plugin returns an array', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_ArgsModifyingBeforePlugin::class,
        targetClass: PIT_ArgsService::class,
        beforeMethods: ['process' => ['pluginMethod' => 'process', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_ArgsService::class, PIT_ArgsService::class, new PIT_ArgsService());

    $result = $proxy->process('original', 5);

    expect($result)->toBe('processed: modified-name, 15');
});

it('throws PluginArgumentCountException when before plugin returns array with wrong count', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_WrongCountBeforePlugin::class,
        targetClass: PIT_RequiredParamService::class,
        beforeMethods: ['create' => ['pluginMethod' => 'create', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_RequiredParamService::class, PIT_RequiredParamService::class, new PIT_RequiredParamService());

    expect(fn () => $proxy->create('Alice', 30))->toThrow(PluginArgumentCountException::class);
});

it('chains argument modifications through multiple before plugins', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_ChainArgFirstPlugin::class,
        targetClass: PIT_ChainArgService::class,
        beforeMethods: ['transform' => ['pluginMethod' => 'transform', 'sortOrder' => 10]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: PIT_ChainArgSecondPlugin::class,
        targetClass: PIT_ChainArgService::class,
        beforeMethods: ['transform' => ['pluginMethod' => 'transform', 'sortOrder' => 20]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_ChainArgService::class, PIT_ChainArgService::class, new PIT_ChainArgService());

    $result = $proxy->transform('hello', 1);

    expect($result)->toBe('result: hello-first-second x3');
});

it('executes after plugins in sort order after target method', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_SimpleSecondAfterPlugin::class,
        targetClass: PIT_SimpleService::class,
        afterMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 20]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: PIT_SimpleAfterPlugin::class,
        targetClass: PIT_SimpleService::class,
        afterMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_SimpleService::class, PIT_SimpleService::class, new PIT_SimpleService());

    $proxy->doAction();

    expect(PIT_SimpleService::$callLog)->toBe(['PIT_SimpleService::doAction'])
        ->and(PIT_SimpleAfterPlugin::$callLog)->toBe(['PIT_SimpleAfterPlugin::doAction'])
        ->and(PIT_SimpleSecondAfterPlugin::$callLog)->toBe(['PIT_SimpleSecondAfterPlugin::doAction']);
});

it('passes result and arguments to after plugins', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_ArgsAfterPlugin::class,
        targetClass: PIT_ArgsService::class,
        afterMethods: ['process' => ['pluginMethod' => 'process', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_ArgsService::class, PIT_ArgsService::class, new PIT_ArgsService());

    $proxy->process('test', 42);

    expect(PIT_ArgsAfterPlugin::$receivedArgs)->toBe(['result' => 'processed: test, 42', 'name' => 'test', 'count' => 42]);
});

it('chains modified results through multiple after plugins', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_DoublerAfterPlugin::class,
        targetClass: PIT_ResultModService::class,
        afterMethods: ['getValue' => ['pluginMethod' => 'getValue', 'sortOrder' => 10]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: PIT_AdderAfterPlugin::class,
        targetClass: PIT_ResultModService::class,
        afterMethods: ['getValue' => ['pluginMethod' => 'getValue', 'sortOrder' => 20]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_ResultModService::class, PIT_ResultModService::class, new PIT_ResultModService());

    $result = $proxy->getValue();

    expect(PIT_DoublerAfterPlugin::$received)->toBe(10)
        ->and(PIT_AdderAfterPlugin::$received)->toBe(20)
        ->and($result)->toBe(25);
});

it('passes modified arguments from before plugins to after plugins', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_ArgsModifyingBeforePlugin::class,
        targetClass: PIT_ArgsService::class,
        beforeMethods: ['process' => ['pluginMethod' => 'process', 'sortOrder' => 10]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: PIT_ArgsAfterPlugin::class,
        targetClass: PIT_ArgsService::class,
        afterMethods: ['process' => ['pluginMethod' => 'process', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_ArgsService::class, PIT_ArgsService::class, new PIT_ArgsService());

    $proxy->process('original', 5);

    expect(PIT_ArgsAfterPlugin::$receivedArgs)->toBe([
        'result' => 'processed: modified-name, 15',
        'name' => 'modified-name',
        'count' => 15,
    ]);
});

it('executes complete before-target-after flow', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_CompleteFlowBeforePlugin::class,
        targetClass: PIT_CompleteFlowService::class,
        beforeMethods: ['process' => ['pluginMethod' => 'process', 'sortOrder' => 10]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: PIT_CompleteFlowAfterPlugin::class,
        targetClass: PIT_CompleteFlowService::class,
        afterMethods: ['process' => ['pluginMethod' => 'process', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_CompleteFlowService::class, PIT_CompleteFlowService::class, new PIT_CompleteFlowService());

    $result = $proxy->process('test');

    expect($result)->toBe('processed: test [modified]')
        ->and(PIT_CompleteFlowService::$callLog)->toBe([
            'PIT_CompleteFlowBeforePlugin::process(test)',
            'PIT_CompleteFlowService::process(test)',
            'PIT_CompleteFlowAfterPlugin::process(processed: test, test)',
        ]);
});

it('handles methods with no plugins on an intercepted class without overhead', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $interceptor = makePluginInterceptor($container, $registry);
    $target = new PIT_NoPluginsService();
    $proxy = $interceptor->createProxy(PIT_NoPluginsService::class, PIT_NoPluginsService::class, $target);

    expect($proxy)->toBe($target);

    $result1 = $proxy->doSomething();
    $result2 = $proxy->anotherMethod(5);

    expect($result1)->toBe('result')
        ->and($result2)->toBe(10)
        ->and(PIT_NoPluginsService::$callLog)->toBe([
            'PIT_NoPluginsService::doSomething',
            'PIT_NoPluginsService::anotherMethod(5)',
        ]);
});

it('injects plugin dependencies via container', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_DIPlugin::class,
        targetClass: PIT_DISaveService::class,
        beforeMethods: ['save' => ['pluginMethod' => 'save', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_DISaveService::class, PIT_DISaveService::class, new PIT_DISaveService());

    $result = $proxy->save('test data');

    expect(PIT_LoggerDep::$messages)->toBe(['About to save: test data'])
        ->and(PIT_DISaveService::$callLog)->toBe([
            'PIT_DIPlugin::save',
            'PIT_DISaveService::save(test data)',
        ])
        ->and($result)->toBe('saved: test data');
});

it('calls plugin method by name returned from registry', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_ExplicitMethodPlugin::class,
        targetClass: PIT_MethodNameService::class,
        beforeMethods: ['save' => ['pluginMethod' => 'validateInput', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_MethodNameService::class, PIT_MethodNameService::class, new PIT_MethodNameService());

    $result = $proxy->save('hello');

    expect(PIT_ExplicitMethodPlugin::$callLog)->toBe(['PIT_ExplicitMethodPlugin::validateInput(hello)'])
        ->and(PIT_MethodNameService::$callLog)->toBe(['PIT_MethodNameService::save(hello)'])
        ->and($result)->toBe('saved: hello');
});

it('executes plugins using explicit method param with different method names', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_ExplicitMethodPlugin::class,
        targetClass: PIT_MethodNameService::class,
        beforeMethods: ['save' => ['pluginMethod' => 'validateInput', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_MethodNameService::class, PIT_MethodNameService::class, new PIT_MethodNameService());

    $result = $proxy->save('test data');

    expect(PIT_ExplicitMethodPlugin::$callLog)->toBe(['PIT_ExplicitMethodPlugin::validateInput(test data)'])
        ->and($result)->toBe('saved: test data');
});

it('finds plugins when interface is resolved to concrete class (originalId differs from resolvedId)', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_HasherPlugin::class,
        targetClass: PIT_HasherInterface::class,
        beforeMethods: ['hash' => ['pluginMethod' => 'hash', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $proxy = $interceptor->createProxy(PIT_HasherInterface::class, PIT_BcryptHasher::class, new PIT_BcryptHasher());

    $result = $proxy->hash('secret');

    expect(PIT_HasherPlugin::$callLog)->toBe(['PIT_HasherPlugin::hash(secret)'])
        ->and(PIT_BcryptHasher::$callLog)->toBe(['PIT_BcryptHasher::hash(secret)'])
        ->and($result)->toBe('bcrypt:secret');
});

it('exposes original target via getPluginTarget', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_GreeterBeforePlugin::class,
        targetClass: PIT_GreeterInterface::class,
        beforeMethods: ['greet' => ['pluginMethod' => 'greet', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $target = new PIT_ConcreteGreeter();
    $proxy = $interceptor->createProxy(PIT_GreeterInterface::class, PIT_ConcreteGreeter::class, $target);

    expect($proxy)->toBeInstanceOf(PluginInterceptedInterface::class)
        ->and($proxy->getPluginTarget())->toBe($target);
});

it('throws PluginException for readonly concrete class with direct plugins', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_ReadonlyServicePlugin::class,
        targetClass: PIT_ReadonlyService::class,
        beforeMethods: ['doWork' => ['pluginMethod' => 'doWork', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);

    expect(fn () => $interceptor->createProxy(PIT_ReadonlyService::class, PIT_ReadonlyService::class, new PIT_ReadonlyService()))
        ->toThrow(PluginException::class);
});

it('creates interceptor implementing PluginInterceptedInterface', function (): void {
    $container = new Container();
    $registry = new PluginRegistry();

    $registry->register(new PluginDefinition(
        pluginClass: PIT_SimpleBeforePlugin::class,
        targetClass: PIT_SimpleService::class,
        beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 10]],
    ));

    $interceptor = makePluginInterceptor($container, $registry);
    $target = new PIT_SimpleService();
    $proxy = $interceptor->createProxy(PIT_SimpleService::class, PIT_SimpleService::class, $target);

    expect($proxy)->toBeInstanceOf(PluginInterceptedInterface::class)
        ->and($proxy->getPluginTarget())->toBe($target);
});
