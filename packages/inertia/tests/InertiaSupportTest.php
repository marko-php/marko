<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Inertia\Exceptions\InertiaException;
use Marko\Inertia\InertiaConfig;
use Marko\Inertia\InertiaFlashStore;
use Marko\Inertia\Interfaces\ProvidesScrollMetadata;
use Marko\Inertia\Props\AlwaysProp;
use Marko\Inertia\Props\DeferProp;
use Marko\Inertia\Props\MergeProp;
use Marko\Inertia\Props\OnceProp;
use Marko\Inertia\Props\OptionalProp;
use Marko\Inertia\Props\PropArray;
use Marko\Inertia\Props\PropertyContext;
use Marko\Inertia\Props\ResolvedProps;
use Marko\Inertia\Props\ScrollProp;
use Marko\Inertia\Rendering\RenderContext;
use Marko\Inertia\Ssr\SsrGateway;
use Marko\Inertia\Ssr\SsrPage;
use Marko\Routing\Http\Request;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;

beforeEach(function (): void {
    $this->request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard'],
    );
});

function inertiaPropertyContext(
    Request $request,
    string $key = 'stats',
    bool $isPartial = false,
    array $only = [],
    array $except = [],
    array $loadedOnce = [],
): PropertyContext {
    return new PropertyContext(
        request: $request,
        component: 'Dashboard/Index',
        key: $key,
        props: [],
        isPartial: $isPartial,
        only: $only,
        except: $except,
        loadedOnce: $loadedOnce,
    );
}

function inertiaSessionStub(bool $started = true): SessionInterface
{
    return new class ($started) implements SessionInterface
    {
        public function __construct(
            public bool $started,
        ) {}

        /** @var array<string, mixed> */
        private array $data = [];

        public function start(): void {}

        public function get(
            string $key,
            mixed $default = null,
        ): mixed
        {
            return $this->data[$key] ?? $default;
        }

        public function set(
            string $key,
            mixed $value,
        ): void
        {
            $this->data[$key] = $value;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->data);
        }

        public function remove(string $key): void
        {
            unset($this->data[$key]);
        }

        public function clear(): void
        {
            $this->data = [];
        }

        public function all(): array
        {
            return $this->data;
        }

        public function regenerate(bool $deleteOldSession = true): void {}

        public function destroy(): void
        {
            $this->data = [];
            $this->started = false;
        }

        public function getId(): string
        {
            return 'support-test-session';
        }

        public function setId(string $id): void {}

        public function flash(): FlashBag
        {
            return new FlashBag($this->data);
        }

        public function save(): void {}
    };
}

final class InertiaSupportTestStreamWrapper
{
    public static string $body = '';

    public mixed $context;

    public int $position = 0;

    public function stream_open(
        string $path,
        string $mode,
        int $options,
        ?string &$opened_path,
    ): bool
    {
        $this->position = 0;

        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr(self::$body, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen(self::$body);
    }

    public function stream_stat(): array
    {
        return [];
    }
}

beforeAll(function (): void {
    if (! in_array('inertia-test-stream', stream_get_wrappers(), true)) {
        stream_wrapper_register('inertia-test-stream', InertiaSupportTestStreamWrapper::class);
    }
});

test('inertia flash store safely handles missing sessions and can flash peek and pull values', function (): void {
    $session = inertiaSessionStub();
    $store = new InertiaFlashStore(static fn (): SessionInterface => $session);

    $store->flash([
        'notice' => 'Saved',
        123 => 'ignored',
    ]);
    $store->flash('count', 2);

    expect($store->available())->toBeTrue()
        ->and($store->peek())->toBe([
            'notice' => 'Saved',
            'count' => 2,
        ])
        ->and($store->pull())->toBe([
            'notice' => 'Saved',
            'count' => 2,
        ])
        ->and($store->peek())->toBe([]);

    $unavailable = new InertiaFlashStore(static fn (): SessionInterface => inertiaSessionStub(started: false));
    $throwing = new InertiaFlashStore(static function (): never {
        throw new RuntimeException('session unavailable');
    });

    expect($unavailable->available())->toBeFalse()
        ->and($unavailable->peek())->toBe([])
        ->and($throwing->available())->toBeFalse()
        ->and($throwing->pull())->toBe([]);
})->group('inertia');

test('inertia prop helpers include and resolve values consistently', function (): void {
    $request = $this->request;
    $fullContext = inertiaPropertyContext($request);
    $partialContext = inertiaPropertyContext($request, only: ['stats'], isPartial: true);
    $exceptedContext = inertiaPropertyContext($request, isPartial: true, only: ['stats'], except: ['stats']);
    $mergeExceptedContext = inertiaPropertyContext($request, isPartial: true, except: ['stats']);
    $onceLoadedContext = inertiaPropertyContext($request, loadedOnce: ['stats']);

    $always = new AlwaysProp(['count' => 1]);
    $deferred = new DeferProp('later', 'analytics');
    $merge = new MergeProp(['items' => [1]]);
    $deepMerged = $merge->deepMerge();
    $prepended = $merge->prepend();
    $once = new OnceProp('welcome');
    $refreshed = $once->refresh();
    $optional = new OptionalProp('only-on-partial');

    expect($always->shouldInclude($fullContext))->toBeTrue()
        ->and($always->shouldInclude($exceptedContext))->toBeFalse()
        ->and($always->resolve($fullContext))->toBe(['count' => 1])
        ->and($deferred->group())->toBe('analytics')
        ->and($deferred->shouldInclude($fullContext))->toBeFalse()
        ->and($deferred->shouldInclude($partialContext))->toBeTrue()
        ->and($deferred->shouldInclude($exceptedContext))->toBeFalse()
        ->and($deferred->resolve($partialContext))->toBe('later')
        ->and($merge->shouldMerge())->toBeTrue()
        ->and($merge->shouldDeepMerge())->toBeFalse()
        ->and($merge->shouldPrepend())->toBeFalse()
        ->and($merge->shouldInclude($fullContext))->toBeTrue()
        ->and($merge->shouldInclude($mergeExceptedContext))->toBeFalse()
        ->and($merge->shouldInclude($exceptedContext))->toBeTrue()
        ->and($deepMerged->shouldDeepMerge())->toBeTrue()
        ->and(
            $deepMerged->shouldInclude(
                inertiaPropertyContext($request, key: 'other', isPartial: true, only: ['stats'])
            )
        )->toBeTrue()
        ->and($prepended->shouldPrepend())->toBeTrue()
        ->and(
            $prepended->shouldInclude(inertiaPropertyContext($request, key: 'other', isPartial: true, only: ['stats']))
        )->toBeTrue()
        ->and($once->key())->toBeNull()
        ->and($once->shouldRefresh())->toBeFalse()
        ->and($once->shouldInclude($fullContext))->toBeTrue()
        ->and($once->shouldInclude($onceLoadedContext))->toBeFalse()
        ->and($refreshed->shouldRefresh())->toBeTrue()
        ->and($refreshed->shouldInclude($onceLoadedContext))->toBeTrue()
        ->and($optional->shouldInclude($fullContext))->toBeFalse()
        ->and($optional->shouldInclude($partialContext))->toBeTrue()
        ->and($optional->resolve($partialContext))->toBe('only-on-partial');
})->group('inertia');

test('prop array property context and resolved props support nested state', function (): void {
    $props = [];
    PropArray::set($props, 'filters.status', 'open');
    PropArray::set($props, 'filters.sort', 'latest');
    PropArray::set($props, '', 'blank');
    PropArray::set($props, '..ignored..', 'value');

    $context = inertiaPropertyContext($this->request, loadedOnce: ['reports']);
    $resolved = new ResolvedProps($props, ['deferred' => ['reports' => ['group' => 'dashboard']]]);

    expect($props)->toBe([
        'filters' => [
            'status' => 'open',
            'sort' => 'latest',
        ],
        '' => 'blank',
        'ignored' => 'value',
    ])
        ->and($context->isOnceLoaded('reports'))->toBeTrue()
        ->and($context->isOnceLoaded('stats'))->toBeFalse()
        ->and($resolved->props)->toBe($props)
        ->and($resolved->metadata)->toBe([
            'deferred' => ['reports' => ['group' => 'dashboard']],
        ]);
})->group('inertia');

test('scroll prop wraps values and resolves metadata from arrays objects and callbacks', function (): void {
    $context = inertiaPropertyContext($this->request, key: 'feed', isPartial: true, only: ['feed']);
    $renderContext = new RenderContext('Dashboard/Feed', $this->request);

    $metadataObject = new class () implements ProvidesScrollMetadata
    {
        public function toScrollMetadata(RenderContext $context): array
        {
            return ['component' => $context->component];
        }
    };

    $wrapped = new ScrollProp(static fn (): array => ['items' => [1, 2]], 'payload');
    $alreadyWrapped = new ScrollProp(['payload' => ['items' => [1, 2]]], 'payload', ['mode' => 'array']);
    $passthrough = new ScrollProp(
        'plain',
        '',
        static fn (RenderContext $ctx): array => ['path' => $ctx->request->path()]
    );
    $objectMetadata = new ScrollProp('plain', 'data', $metadataObject);

    expect($wrapped->shouldMerge())->toBeTrue()
        ->and($wrapped->shouldDeepMerge())->toBeFalse()
        ->and($wrapped->shouldPrepend())->toBeFalse()
        ->and($wrapped->shouldInclude($context))->toBeTrue()
        ->and($wrapped->resolve($context))->toBe([
            'payload' => ['items' => [1, 2]],
        ])
        ->and($alreadyWrapped->resolve($context))->toBe([
            'payload' => ['items' => [1, 2]],
        ])
        ->and($alreadyWrapped->metadata($renderContext))->toBe(['mode' => 'array'])
        ->and($passthrough->resolve($context))->toBe('plain')
        ->and($passthrough->metadata($renderContext))->toBe(['path' => '/dashboard'])
        ->and($objectMetadata->metadata($renderContext))->toBe(['component' => 'Dashboard/Feed']);
})->group('inertia');

test('ssr gateway handles disabled missing invalid and successful responses', function (): void {
    $disabled = new SsrGateway(new InertiaConfig(new ConfigRepository([
        'inertia' => ['ssr' => [
            'enabled' => false,
            'url' => 'inertia-test-stream://disabled',
            'bundle' => '',
            'ensure_bundle_exists' => false,
            'throw_on_error' => false,
        ]],
    ])));

    expect($disabled->render(['component' => 'Dashboard/Index']))->toBeNull();

    $missingBundle = new SsrGateway(new InertiaConfig(new ConfigRepository([
        'inertia' => [
            'ssr' => [
                'enabled' => true,
                'url' => 'inertia-test-stream://missing-bundle',
                'ensure_bundle_exists' => true,
                'bundle' => '/definitely/missing.js',
                'throw_on_error' => false,
            ],
        ],
    ])));

    expect($missingBundle->render(['component' => 'Dashboard/Index']))->toBeNull();

    $throwingBundle = new SsrGateway(new InertiaConfig(new ConfigRepository([
        'inertia' => [
            'ssr' => [
                'enabled' => true,
                'url' => 'inertia-test-stream://throwing-bundle',
                'ensure_bundle_exists' => true,
                'bundle' => '/definitely/missing.js',
                'throw_on_error' => true,
            ],
        ],
    ])));

    expect(fn () => $throwingBundle->render(['component' => 'Dashboard/Index']))
        ->toThrow(RuntimeException::class, 'Inertia SSR bundle is missing.');

    InertiaSupportTestStreamWrapper::$body = '';
    $empty = new SsrGateway(new InertiaConfig(new ConfigRepository([
        'inertia' => [
            'ssr' => [
                'enabled' => true,
                'url' => 'inertia-test-stream://empty',
                'bundle' => '',
                'ensure_bundle_exists' => false,
                'throw_on_error' => false,
            ],
        ],
    ])));

    expect($empty->render(['component' => 'Dashboard/Index']))->toBeNull();

    InertiaSupportTestStreamWrapper::$body = '{"missing":"body"}';
    $invalidBody = new SsrGateway(new InertiaConfig(new ConfigRepository([
        'inertia' => [
            'ssr' => [
                'enabled' => true,
                'url' => 'inertia-test-stream://invalid-body',
                'bundle' => '',
                'ensure_bundle_exists' => false,
                'throw_on_error' => false,
            ],
        ],
    ])));

    expect($invalidBody->render(['component' => 'Dashboard/Index']))->toBeNull();

    InertiaSupportTestStreamWrapper::$body = '{"body":"<div>SSR</div>","head":"<title>Marko</title>"}';
    $success = new SsrGateway(new InertiaConfig(new ConfigRepository([
        'inertia' => [
            'ssr' => [
                'enabled' => true,
                'url' => 'inertia-test-stream://success',
                'bundle' => '',
                'ensure_bundle_exists' => false,
                'throw_on_error' => true,
            ],
        ],
    ])));

    $page = $success->render(['component' => 'Dashboard/Index']);

    expect($page)
        ->toBeInstanceOf(SsrPage::class)
        ->and($page?->body)->toBe('<div>SSR</div>')
        ->and($page?->head)->toBe(['<title>Marko</title>']);

    InertiaSupportTestStreamWrapper::$body = '{invalid';
    $invalidJson = new SsrGateway(new InertiaConfig(new ConfigRepository([
        'inertia' => [
            'ssr' => [
                'enabled' => true,
                'url' => 'inertia-test-stream://invalid-json',
                'bundle' => '',
                'ensure_bundle_exists' => false,
                'throw_on_error' => true,
            ],
        ],
    ])));

    expect(fn () => $invalidJson->render(['component' => 'Dashboard/Index']))
        ->toThrow(RuntimeException::class, 'Inertia SSR server returned invalid JSON.');
})->group('inertia');

test('inertia exception extends the shared marko exception base', function (): void {
    $exception = new InertiaException('boom');

    expect($exception)->toBeInstanceOf(Marko\Core\Exceptions\MarkoException::class)
        ->and($exception->getMessage())->toBe('boom');
})->group('inertia');
