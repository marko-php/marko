<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginInterceptedInterface;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;

// ---------------------------------------------------------------------------
// Test fixtures — prefixed with PIIT_ to avoid conflicts across test files
// ---------------------------------------------------------------------------

interface PIIT_HasherInterface
{
    public function hash(string $value): string;
}

class PIIT_BcryptHasher implements PIIT_HasherInterface
{
    public function hash(string $value): string
    {
        return "bcrypt:$value";
    }
}

class PIIT_HasherPlugin
{
    public static array $log = [];

    /** @noinspection PhpUnused - Invoked via plugin interception */
    public function hash(string $value): null
    {
        self::$log[] = "before:$value";

        return null;
    }
}

class PIIT_HasherAfterPlugin
{
    public static array $log = [];

    /** @noinspection PhpUnused - Invoked via plugin interception */
    public function hash(mixed $result, string $value): string
    {
        self::$log[] = "after:$result";

        return strtoupper((string) $result);
    }
}

class PIIT_LoggingPlugin
{
    public static array $log = [];

    /** @noinspection PhpUnused - Invoked via plugin interception */
    public function hash(string $value): null
    {
        self::$log[] = "before:$value";

        return null;
    }

    /** @noinspection PhpUnused - Invoked via plugin interception */
    public function hashAfter(mixed $result, string $value): string
    {
        self::$log[] = "after:$result";

        return (string) $result;
    }
}

class PIIT_ShortCircuitPlugin
{
    /** @noinspection PhpUnused - Invoked via plugin interception */
    public function hash(string $value): string
    {
        return "short-circuit:$value";
    }
}

class PIIT_ArgModifyingPlugin
{
    /** @noinspection PhpUnused - Invoked via plugin interception */
    public function hash(string $value): array
    {
        return ["modified:$value"];
    }
}

class PIIT_FirstAfterPlugin
{
    public static array $log = [];

    /** @noinspection PhpUnused - Invoked via plugin interception */
    public function hash(mixed $result, string $value): string
    {
        self::$log[] = "first-after:$result";

        return "[$result]";
    }
}

class PIIT_SecondAfterPlugin
{
    public static array $log = [];

    /** @noinspection PhpUnused - Invoked via plugin interception */
    public function hash(mixed $result, string $value): string
    {
        self::$log[] = "second-after:$result";

        return "$result!";
    }
}

class PIIT_ConcreteService
{
    public static array $log = [];

    public function compute(string $input): string
    {
        self::$log[] = "compute:$input";

        return "result:$input";
    }
}

class PIIT_ConcretePlugin
{
    public static array $log = [];

    /** @noinspection PhpUnused - Invoked via plugin interception */
    public function compute(string $input): null
    {
        self::$log[] = "before-compute:$input";

        return null;
    }
}

readonly class PIIT_ReadonlyService
{
    public function work(): string
    {
        return 'done';
    }
}

class PIIT_ReadonlyPlugin
{
    /** @noinspection PhpUnused - Invoked via plugin interception */
    public function work(): null
    {
        return null;
    }
}

readonly class PIIT_Controller
{
    public function __construct(
        private PIIT_HasherInterface $hasher,
    ) {}

    public function run(): string
    {
        return $this->hasher->hash('test');
    }
}

class PIIT_SingletonService
{
    public static int $instanceCount = 0;

    public function __construct()
    {
        self::$instanceCount++;
    }

    public function getValue(): string
    {
        return 'singleton-value';
    }
}

class PIIT_SingletonPlugin
{
    public static array $log = [];

    /** @noinspection PhpUnused - Invoked via plugin interception */
    public function getValue(): null
    {
        self::$log[] = 'before-getValue';

        return null;
    }
}

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function makeIntegrationContainer(
    ?PreferenceRegistry $preferenceRegistry = null,
): array {
    $registry = new PluginRegistry();
    $container = new Container($preferenceRegistry ?? new PreferenceRegistry());
    $interceptor = new PluginInterceptor($container, $registry, new InterceptorClassGenerator());
    $container->setPluginInterceptor($interceptor);

    return ['container' => $container, 'registry' => $registry];
}

// ---------------------------------------------------------------------------
// Reset static state before each test
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    PIIT_HasherPlugin::$log = [];
    PIIT_HasherAfterPlugin::$log = [];
    PIIT_LoggingPlugin::$log = [];
    PIIT_FirstAfterPlugin::$log = [];
    PIIT_SecondAfterPlugin::$log = [];
    PIIT_ConcreteService::$log = [];
    PIIT_ConcretePlugin::$log = [];
    PIIT_SingletonPlugin::$log = [];
    PIIT_SingletonService::$instanceCount = 0;
});

// ===========================================================================
// TESTS
// ===========================================================================

it('intercepts interface method calls when plugin targets the interface via closure binding', function (): void {
    ['container' => $container, 'registry' => $registry] = makeIntegrationContainer();

    $registry->register(new PluginDefinition(
        pluginClass: PIIT_HasherPlugin::class,
        targetClass: PIIT_HasherInterface::class,
        beforeMethods: ['hash' => ['pluginMethod' => 'hash', 'sortOrder' => 10]],
    ));

    $container->bind(PIIT_HasherInterface::class, fn () => new PIIT_BcryptHasher());

    $hasher = $container->get(PIIT_HasherInterface::class);
    $result = $hasher->hash('secret');

    expect(PIIT_HasherPlugin::$log)->toBe(['before:secret'])
        ->and($result)->toBe('bcrypt:secret');
});

it('intercepts interface method calls when plugin targets the interface via preference resolution', function (): void {
    $preferenceRegistry = new PreferenceRegistry();
    $preferenceRegistry->register(PIIT_HasherInterface::class, PIIT_BcryptHasher::class);

    ['container' => $container, 'registry' => $registry] = makeIntegrationContainer($preferenceRegistry);

    $registry->register(new PluginDefinition(
        pluginClass: PIIT_HasherPlugin::class,
        targetClass: PIIT_HasherInterface::class,
        beforeMethods: ['hash' => ['pluginMethod' => 'hash', 'sortOrder' => 10]],
    ));

    $hasher = $container->get(PIIT_HasherInterface::class);
    $result = $hasher->hash('secret');

    expect(PIIT_HasherPlugin::$log)->toBe(['before:secret'])
        ->and($result)->toBe('bcrypt:secret');
});

it('returns object that passes instanceof check for target interface', function (): void {
    ['container' => $container, 'registry' => $registry] = makeIntegrationContainer();

    $registry->register(new PluginDefinition(
        pluginClass: PIIT_HasherPlugin::class,
        targetClass: PIIT_HasherInterface::class,
        beforeMethods: ['hash' => ['pluginMethod' => 'hash', 'sortOrder' => 10]],
    ));

    $container->bind(PIIT_HasherInterface::class, fn () => new PIIT_BcryptHasher());

    $hasher = $container->get(PIIT_HasherInterface::class);

    expect($hasher)->toBeInstanceOf(PIIT_HasherInterface::class);
});

it('executes before plugin then target method then after plugin in correct order', function (): void {
    ['container' => $container, 'registry' => $registry] = makeIntegrationContainer();

    $registry->register(new PluginDefinition(
        pluginClass: PIIT_LoggingPlugin::class,
        targetClass: PIIT_HasherInterface::class,
        beforeMethods: ['hash' => ['pluginMethod' => 'hash', 'sortOrder' => 10]],
        afterMethods: ['hash' => ['pluginMethod' => 'hashAfter', 'sortOrder' => 10]],
    ));

    $container->bind(PIIT_HasherInterface::class, fn () => new PIIT_BcryptHasher());

    $hasher = $container->get(PIIT_HasherInterface::class);
    $result = $hasher->hash('test');

    expect(PIIT_LoggingPlugin::$log)->toBe(['before:test', 'after:bcrypt:test'])
        ->and($result)->toBe('bcrypt:test');
});

it('short-circuits target method when before plugin returns non-null', function (): void {
    ['container' => $container, 'registry' => $registry] = makeIntegrationContainer();

    $registry->register(new PluginDefinition(
        pluginClass: PIIT_ShortCircuitPlugin::class,
        targetClass: PIIT_HasherInterface::class,
        beforeMethods: ['hash' => ['pluginMethod' => 'hash', 'sortOrder' => 10]],
    ));

    $container->bind(PIIT_HasherInterface::class, fn () => new PIIT_BcryptHasher());

    $hasher = $container->get(PIIT_HasherInterface::class);
    $result = $hasher->hash('test');

    expect($result)->toBe('short-circuit:test');
});

it('modifies arguments via before plugin array return', function (): void {
    ['container' => $container, 'registry' => $registry] = makeIntegrationContainer();

    $registry->register(new PluginDefinition(
        pluginClass: PIIT_ArgModifyingPlugin::class,
        targetClass: PIIT_HasherInterface::class,
        beforeMethods: ['hash' => ['pluginMethod' => 'hash', 'sortOrder' => 10]],
    ));

    $container->bind(PIIT_HasherInterface::class, fn () => new PIIT_BcryptHasher());

    $hasher = $container->get(PIIT_HasherInterface::class);
    $result = $hasher->hash('original');

    expect($result)->toBe('bcrypt:modified:original');
});

it('chains after plugin results', function (): void {
    ['container' => $container, 'registry' => $registry] = makeIntegrationContainer();

    $registry->register(new PluginDefinition(
        pluginClass: PIIT_FirstAfterPlugin::class,
        targetClass: PIIT_HasherInterface::class,
        afterMethods: ['hash' => ['pluginMethod' => 'hash', 'sortOrder' => 10]],
    ));

    $registry->register(new PluginDefinition(
        pluginClass: PIIT_SecondAfterPlugin::class,
        targetClass: PIIT_HasherInterface::class,
        afterMethods: ['hash' => ['pluginMethod' => 'hash', 'sortOrder' => 20]],
    ));

    $container->bind(PIIT_HasherInterface::class, fn () => new PIIT_BcryptHasher());

    $hasher = $container->get(PIIT_HasherInterface::class);
    $result = $hasher->hash('test');

    expect(PIIT_FirstAfterPlugin::$log)->toBe(['first-after:bcrypt:test'])
        ->and(PIIT_SecondAfterPlugin::$log)->toBe(['second-after:[bcrypt:test]'])
        ->and($result)->toBe('[bcrypt:test]!');
});

it('works when plugin targets concrete class that is not readonly', function (): void {
    ['container' => $container, 'registry' => $registry] = makeIntegrationContainer();

    $registry->register(new PluginDefinition(
        pluginClass: PIIT_ConcretePlugin::class,
        targetClass: PIIT_ConcreteService::class,
        beforeMethods: ['compute' => ['pluginMethod' => 'compute', 'sortOrder' => 10]],
    ));

    $service = $container->get(PIIT_ConcreteService::class);
    $result = $service->compute('hello');

    expect(PIIT_ConcretePlugin::$log)->toBe(['before-compute:hello'])
        ->and($result)->toBe('result:hello');
});

it('throws PluginException when plugin targets readonly concrete class directly', function (): void {
    ['container' => $container, 'registry' => $registry] = makeIntegrationContainer();

    $registry->register(new PluginDefinition(
        pluginClass: PIIT_ReadonlyPlugin::class,
        targetClass: PIIT_ReadonlyService::class,
        beforeMethods: ['work' => ['pluginMethod' => 'work', 'sortOrder' => 10]],
    ));

    expect(fn () => $container->get(PIIT_ReadonlyService::class))
        ->toThrow(PluginException::class);
});

it('injects intercepted service into another service constructor without TypeError', function (): void {
    ['container' => $container, 'registry' => $registry] = makeIntegrationContainer();

    $registry->register(new PluginDefinition(
        pluginClass: PIIT_HasherPlugin::class,
        targetClass: PIIT_HasherInterface::class,
        beforeMethods: ['hash' => ['pluginMethod' => 'hash', 'sortOrder' => 10]],
    ));

    $container->bind(PIIT_HasherInterface::class, fn () => new PIIT_BcryptHasher());

    // PIIT_Controller type-hints PIIT_HasherInterface — this used to throw TypeError
    $controller = $container->get(PIIT_Controller::class);
    $result = $controller->run();

    expect($result)->toBe('bcrypt:test')
        ->and(PIIT_HasherPlugin::$log)->toBe(['before:test']);
});

it('works with singleton services that have plugins', function (): void {
    ['container' => $container, 'registry' => $registry] = makeIntegrationContainer();

    $registry->register(new PluginDefinition(
        pluginClass: PIIT_SingletonPlugin::class,
        targetClass: PIIT_SingletonService::class,
        beforeMethods: ['getValue' => ['pluginMethod' => 'getValue', 'sortOrder' => 10]],
    ));

    $container->singleton(PIIT_SingletonService::class);

    $first = $container->get(PIIT_SingletonService::class);
    $countAfterFirst = PIIT_SingletonService::$instanceCount;

    $second = $container->get(PIIT_SingletonService::class);
    $countAfterSecond = PIIT_SingletonService::$instanceCount;

    $first->getValue();
    $second->getValue();

    // The same proxy instance is returned on both calls (singleton behaviour)
    // Construction count does not grow after the first resolution
    expect($first)->toBe($second)
        ->and($countAfterSecond)->toBe($countAfterFirst)
        ->and(PIIT_SingletonPlugin::$log)->toBe(['before-getValue', 'before-getValue']);
});

it('exposes original target via getPluginTarget on intercepted instance', function (): void {
    ['container' => $container, 'registry' => $registry] = makeIntegrationContainer();

    $registry->register(new PluginDefinition(
        pluginClass: PIIT_HasherPlugin::class,
        targetClass: PIIT_HasherInterface::class,
        beforeMethods: ['hash' => ['pluginMethod' => 'hash', 'sortOrder' => 10]],
    ));

    $container->bind(PIIT_HasherInterface::class, fn () => new PIIT_BcryptHasher());

    $hasher = $container->get(PIIT_HasherInterface::class);

    expect($hasher)->toBeInstanceOf(PluginInterceptedInterface::class)
        ->and($hasher->getPluginTarget())->toBeInstanceOf(PIIT_BcryptHasher::class);
});
