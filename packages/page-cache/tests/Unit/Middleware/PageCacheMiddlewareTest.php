<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Container\ContainerInterface;
use Marko\PageCache\Attributes\Cacheable;
use Marko\PageCache\CacheabilityChecker;
use Marko\PageCache\CachePolicy;
use Marko\PageCache\Config\PageCacheConfig;
use Marko\PageCache\Contracts\CacheTagProviderInterface;
use Marko\PageCache\Contracts\PageCacheInterface;
use Marko\PageCache\Exceptions\PageCacheException;
use Marko\PageCache\Middleware\PageCacheMiddleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\MatchedRoute;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcherInterface;

// ─── Fake PageCacheInterface ──────────────────────────────────────────────────

class FakePageCache implements PageCacheInterface
{
    public ?Response $lookupResult = null;

    public ?Response $storeResult = null;

    public ?CachePolicy $storedPolicy = null;

    public function lookup(Request $request): ?Response
    {
        return $this->lookupResult;
    }

    public function store(
        Request $request,
        Response $response,
        CachePolicy $policy,
    ): Response {
        $this->storedPolicy = $policy;

        return $this->storeResult ?? $response;
    }

    public function purgeUrl(string $url): bool
    {
        return true;
    }

    public function purgeTag(string $tag): bool
    {
        return true;
    }

    public function clear(): bool
    {
        return true;
    }
}

// ─── Controller fixture for attribute testing ─────────────────────────────────

class MiddlewareCacheableController
{
    #[Cacheable(ttl: 3600, tags: ['products'])]
    public function index(): void {}

    public function show(): void {}

    #[Cacheable(ttl: 3600, tags: ['static-a', 'static-b'], provider: FakeCacheTagProvider::class)]
    public function indexWithProvider(): void {}

    #[Cacheable(ttl: 3600, tags: ['static-a'], provider: FakeCacheTagProvider::class)]
    public function indexWithProviderOverlap(): void {}

    #[Cacheable(ttl: 3600, tags: ['static-a'], provider: NotACacheTagProvider::class)]
    public function indexWithInvalidProvider(): void {}
}

// ─── Fake CacheTagProvider ────────────────────────────────────────────────────

class FakeCacheTagProvider implements CacheTagProviderInterface
{
    /** @var array<string> */
    public array $tagsToReturn = ['dynamic-1', 'dynamic-2'];

    /** @return array<string> */
    public function tags(
        Request $request,
        Cacheable $attribute,
    ): array {
        return $this->tagsToReturn;
    }
}

class NotACacheTagProvider
{
    // intentionally does not implement CacheTagProviderInterface
}

// ─── Fake ContainerInterface ──────────────────────────────────────────────────

class FakeContainer implements ContainerInterface
{
    /** @var array<string, object> */
    public array $instances = [];

    public bool $getCalled = false;

    public ?string $lastGetId = null;

    public function get(string $id): mixed
    {
        $this->getCalled = true;
        $this->lastGetId = $id;

        return $this->instances[$id] ?? new $id();
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || class_exists($id);
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
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeMiddlewareChecker(
    RouteMatcherInterface $matcher,
    array $methods = ['GET', 'HEAD'],
    array $statusCodes = [200],
): CacheabilityChecker {
    $config = new readonly class ($methods, $statusCodes) implements ConfigRepositoryInterface
    {
        public function __construct(
            private array $methods,
            private array $statusCodes,
        ) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            return match ($key) {
                'page-cache.cacheable_methods' => $this->methods,
                'page-cache.cacheable_status_codes' => $this->statusCodes,
                default => throw new ConfigNotFoundException($key),
            };
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return false;
        }

        public function getString(
            string $key,
            ?string $scope = null,
        ): string {
            throw new ConfigNotFoundException($key);
        }

        public function getInt(
            string $key,
            ?string $scope = null,
        ): int {
            throw new ConfigNotFoundException($key);
        }

        public function getBool(
            string $key,
            ?string $scope = null,
        ): bool {
            throw new ConfigNotFoundException($key);
        }

        public function getFloat(
            string $key,
            ?string $scope = null,
        ): float {
            throw new ConfigNotFoundException($key);
        }

        public function getArray(
            string $key,
            ?string $scope = null,
        ): array {
            return match ($key) {
                'page-cache.cacheable_methods' => $this->methods,
                'page-cache.cacheable_status_codes' => $this->statusCodes,
                default => throw new ConfigNotFoundException($key),
            };
        }

        public function all(?string $scope = null): array
        {
            return [];
        }

        public function withScope(string $scope): ConfigRepositoryInterface
        {
            return $this;
        }
    };

    $pageConfig = new PageCacheConfig($config);

    return new CacheabilityChecker($matcher, $pageConfig);
}

function makeNullRouteMatcher(): RouteMatcherInterface
{
    return new class () implements RouteMatcherInterface
    {
        public function match(
            string $method,
            string $path,
        ): ?MatchedRoute {
            return null;
        }
    };
}

function makeMatcherForController(string $action): RouteMatcherInterface
{
    return new readonly class ($action) implements RouteMatcherInterface
    {
        public function __construct(private string $action) {}

        public function match(
            string $method,
            string $path,
        ): ?MatchedRoute {
            $route = new RouteDefinition(
                method: $method,
                path: $path,
                controller: MiddlewareCacheableController::class,
                action: $this->action,
            );

            return new MatchedRoute($route);
        }
    };
}

function makeMiddlewareRequest(string $method = 'GET', string $uri = '/'): Request
{
    return new Request(server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri]);
}

function makeMiddlewareResponse(int $statusCode = 200, array $headers = [], string $body = 'body'): Response
{
    return new Response(body: $body, statusCode: $statusCode, headers: $headers);
}

function makeMiddlewareContainer(): FakeContainer
{
    return new FakeContainer();
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('passes through when the request is not cacheable by HTTP method', function (): void {
    $checker = makeMiddlewareChecker(makeNullRouteMatcher(), methods: ['GET', 'HEAD']);
    $cache = new FakePageCache();
    $container = makeMiddlewareContainer();
    $middleware = new PageCacheMiddleware($cache, $checker, $container);

    $postRequest = makeMiddlewareRequest('POST');
    $expectedResponse = makeMiddlewareResponse();

    $next = fn (Request $r): Response => $expectedResponse;

    $result = $middleware->handle($postRequest, $next);

    expect($result)->toBe($expectedResponse);
});

it('passes through when the matched route has no Cacheable attribute', function (): void {
    $checker = makeMiddlewareChecker(makeMatcherForController('show'));
    $cache = new FakePageCache();
    $container = makeMiddlewareContainer();
    $middleware = new PageCacheMiddleware($cache, $checker, $container);

    $request = makeMiddlewareRequest('GET', '/products/1');
    $expectedResponse = makeMiddlewareResponse();

    $next = fn (Request $r): Response => $expectedResponse;

    $result = $middleware->handle($request, $next);

    expect($result)->toBe($expectedResponse);
});

it('returns the cached response when lookup hits', function (): void {
    $checker = makeMiddlewareChecker(makeMatcherForController('index'));
    $cache = new FakePageCache();
    $cachedResponse = makeMiddlewareResponse(body: 'cached');
    $cache->lookupResult = $cachedResponse;
    $container = makeMiddlewareContainer();
    $middleware = new PageCacheMiddleware($cache, $checker, $container);

    $request = makeMiddlewareRequest('GET', '/products');
    $nextCalled = false;
    $next = function (Request $r) use (&$nextCalled): Response {
        $nextCalled = true;

        return makeMiddlewareResponse(body: 'fresh');
    };

    $result = $middleware->handle($request, $next);

    expect($result)->toBe($cachedResponse)
        ->and($nextCalled)->toBeFalse();
});

it('calls the next handler when lookup misses', function (): void {
    $checker = makeMiddlewareChecker(makeMatcherForController('index'));
    $cache = new FakePageCache();
    $cache->lookupResult = null;
    $container = makeMiddlewareContainer();
    $middleware = new PageCacheMiddleware($cache, $checker, $container);

    $request = makeMiddlewareRequest('GET', '/products');
    $freshResponse = makeMiddlewareResponse(body: 'fresh');
    $nextCalled = false;
    $next = function (Request $r) use (&$nextCalled, $freshResponse): Response {
        $nextCalled = true;

        return $freshResponse;
    };

    $result = $middleware->handle($request, $next);

    expect($nextCalled)->toBeTrue()
        ->and($result)->toBe($freshResponse);
});

it('stores the response when the response is cacheable', function (): void {
    $checker = makeMiddlewareChecker(makeMatcherForController('index'));
    $cache = new FakePageCache();
    $cache->lookupResult = null;
    $container = makeMiddlewareContainer();
    $middleware = new PageCacheMiddleware($cache, $checker, $container);

    $request = makeMiddlewareRequest('GET', '/products');
    $freshResponse = makeMiddlewareResponse(statusCode: 200);
    $next = fn (Request $r): Response => $freshResponse;

    $middleware->handle($request, $next);

    expect($cache->storedPolicy)->toBeInstanceOf(CachePolicy::class);
});

it('does not store responses that are not cacheable', function (): void {
    $checker = makeMiddlewareChecker(makeMatcherForController('index'), statusCodes: [200]);
    $cache = new FakePageCache();
    $cache->lookupResult = null;
    $container = makeMiddlewareContainer();
    $middleware = new PageCacheMiddleware($cache, $checker, $container);

    $request = makeMiddlewareRequest('GET', '/products');
    $errorResponse = makeMiddlewareResponse(statusCode: 500);
    $next = fn (Request $r): Response => $errorResponse;

    $result = $middleware->handle($request, $next);

    expect($cache->storedPolicy)->toBeNull()
        ->and($result)->toBe($errorResponse);
});

it('builds a CachePolicy from the Cacheable attribute ttl and tags before storing', function (): void {
    $checker = makeMiddlewareChecker(makeMatcherForController('index'));
    $cache = new FakePageCache();
    $cache->lookupResult = null;
    $container = makeMiddlewareContainer();
    $middleware = new PageCacheMiddleware($cache, $checker, $container);

    $request = makeMiddlewareRequest('GET', '/products');
    $freshResponse = makeMiddlewareResponse(statusCode: 200);
    $next = fn (Request $r): Response => $freshResponse;

    $middleware->handle($request, $next);

    expect($cache->storedPolicy)->toBeInstanceOf(CachePolicy::class)
        ->and($cache->storedPolicy->ttl)->toBe(3600)
        ->and($cache->storedPolicy->tags)->toBe(['products']);
});

it(
    'returns the Response value returned by store (not the original) so proxy drivers can decorate headers',
    function (): void {
        $checker = makeMiddlewareChecker(makeMatcherForController('index'));
        $cache = new FakePageCache();
        $cache->lookupResult = null;
        $decoratedResponse = makeMiddlewareResponse(body: 'decorated');
        $cache->storeResult = $decoratedResponse;
        $container = makeMiddlewareContainer();
        $middleware = new PageCacheMiddleware($cache, $checker, $container);

        $request = makeMiddlewareRequest('GET', '/products');
        $freshResponse = makeMiddlewareResponse(statusCode: 200, body: 'fresh');
        $next = fn (Request $r): Response => $freshResponse;

        $result = $middleware->handle($request, $next);

        expect($result)->toBe($decoratedResponse);
    },
);

it('stores cached response with only static tags when no provider is configured', function (): void {
    $checker = makeMiddlewareChecker(makeMatcherForController('index'));
    $cache = new FakePageCache();
    $cache->lookupResult = null;
    $container = makeMiddlewareContainer();
    $middleware = new PageCacheMiddleware($cache, $checker, $container);

    $request = makeMiddlewareRequest('GET', '/products');
    $freshResponse = makeMiddlewareResponse(statusCode: 200);
    $next = fn (Request $r): Response => $freshResponse;

    $middleware->handle($request, $next);

    expect($cache->storedPolicy)->toBeInstanceOf(CachePolicy::class)
        ->and($cache->storedPolicy->tags)->toBe(['products'])
        ->and($container->getCalled)->toBeFalse();
});

it('stores cached response with merged static and provider tags when provider is configured', function (): void {
    $checker = makeMiddlewareChecker(makeMatcherForController('indexWithProvider'));
    $cache = new FakePageCache();
    $cache->lookupResult = null;
    $container = makeMiddlewareContainer();
    $provider = new FakeCacheTagProvider();
    $provider->tagsToReturn = ['dynamic-1', 'dynamic-2'];
    $container->instance(FakeCacheTagProvider::class, $provider);
    $middleware = new PageCacheMiddleware($cache, $checker, $container);

    $request = makeMiddlewareRequest('GET', '/products');
    $freshResponse = makeMiddlewareResponse(statusCode: 200);
    $next = fn (Request $r): Response => $freshResponse;

    $middleware->handle($request, $next);

    expect($cache->storedPolicy)->toBeInstanceOf(CachePolicy::class)
        ->and($cache->storedPolicy->tags)->toBe(['static-a', 'static-b', 'dynamic-1', 'dynamic-2']);
});

it('resolves the provider class from the container', function (): void {
    $checker = makeMiddlewareChecker(makeMatcherForController('indexWithProvider'));
    $cache = new FakePageCache();
    $cache->lookupResult = null;
    $container = makeMiddlewareContainer();
    $provider = new FakeCacheTagProvider();
    $container->instance(FakeCacheTagProvider::class, $provider);
    $middleware = new PageCacheMiddleware($cache, $checker, $container);

    $request = makeMiddlewareRequest('GET', '/products');
    $freshResponse = makeMiddlewareResponse(statusCode: 200);
    $next = fn (Request $r): Response => $freshResponse;

    $middleware->handle($request, $next);

    expect($container->getCalled)->toBeTrue()
        ->and($container->lastGetId)->toBe(FakeCacheTagProvider::class);
});

it('deduplicates tags when provider returns tags already present in the static list', function (): void {
    $checker = makeMiddlewareChecker(makeMatcherForController('indexWithProviderOverlap'));
    $cache = new FakePageCache();
    $cache->lookupResult = null;
    $container = makeMiddlewareContainer();
    $provider = new FakeCacheTagProvider();
    $provider->tagsToReturn = ['static-a', 'dynamic-1'];
    $container->instance(FakeCacheTagProvider::class, $provider);
    $middleware = new PageCacheMiddleware($cache, $checker, $container);

    $request = makeMiddlewareRequest('GET', '/products');
    $freshResponse = makeMiddlewareResponse(statusCode: 200);
    $next = fn (Request $r): Response => $freshResponse;

    $middleware->handle($request, $next);

    expect($cache->storedPolicy)->toBeInstanceOf(CachePolicy::class)
        ->and($cache->storedPolicy->tags)->toBe(['static-a', 'dynamic-1']);
});

it('preserves the order of static tags before provider tags after deduplication', function (): void {
    $checker = makeMiddlewareChecker(makeMatcherForController('indexWithProvider'));
    $cache = new FakePageCache();
    $cache->lookupResult = null;
    $container = makeMiddlewareContainer();
    $provider = new FakeCacheTagProvider();
    $provider->tagsToReturn = ['dynamic-1', 'static-a'];
    $container->instance(FakeCacheTagProvider::class, $provider);
    $middleware = new PageCacheMiddleware($cache, $checker, $container);

    $request = makeMiddlewareRequest('GET', '/products');
    $freshResponse = makeMiddlewareResponse(statusCode: 200);
    $next = fn (Request $r): Response => $freshResponse;

    $middleware->handle($request, $next);

    expect($cache->storedPolicy)->toBeInstanceOf(CachePolicy::class)
        ->and($cache->storedPolicy->tags)->toBe(['static-a', 'static-b', 'dynamic-1']);
});

it(
    'throws PageCacheException when the resolved provider does not implement CacheTagProviderInterface',
    function (): void {
        $checker = makeMiddlewareChecker(makeMatcherForController('indexWithInvalidProvider'));
        $cache = new FakePageCache();
        $cache->lookupResult = null;
        $container = makeMiddlewareContainer();
        $container->instance(NotACacheTagProvider::class, new NotACacheTagProvider());
        $middleware = new PageCacheMiddleware($cache, $checker, $container);

        $request = makeMiddlewareRequest('GET', '/products');
        $freshResponse = makeMiddlewareResponse(statusCode: 200);
        $next = fn (Request $r): Response => $freshResponse;

        expect(fn () => $middleware->handle($request, $next))->toThrow(PageCacheException::class);
    },
);

it('does not invoke the provider on cache hits', function (): void {
    $checker = makeMiddlewareChecker(makeMatcherForController('indexWithProvider'));
    $cache = new FakePageCache();
    $cachedResponse = makeMiddlewareResponse(body: 'cached');
    $cache->lookupResult = $cachedResponse;
    $container = makeMiddlewareContainer();
    $middleware = new PageCacheMiddleware($cache, $checker, $container);

    $request = makeMiddlewareRequest('GET', '/products');
    $next = fn (Request $r): Response => makeMiddlewareResponse(body: 'fresh');

    $middleware->handle($request, $next);

    expect($container->getCalled)->toBeFalse();
});

it('does not invoke the provider when the response is not cacheable', function (): void {
    $checker = makeMiddlewareChecker(makeMatcherForController('indexWithProvider'), statusCodes: [200]);
    $cache = new FakePageCache();
    $cache->lookupResult = null;
    $container = makeMiddlewareContainer();
    $middleware = new PageCacheMiddleware($cache, $checker, $container);

    $request = makeMiddlewareRequest('GET', '/products');
    $errorResponse = makeMiddlewareResponse(statusCode: 500);
    $next = fn (Request $r): Response => $errorResponse;

    $middleware->handle($request, $next);

    expect($container->getCalled)->toBeFalse();
});
