<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\PageCache\Attributes\Cacheable;
use Marko\PageCache\CacheabilityChecker;
use Marko\PageCache\Config\PageCacheConfig;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\MatchedRoute;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcherInterface;

// Fixture controllers for attribute testing
class CacheableActionController
{
    #[Cacheable(ttl: 3600, tags: ['products'])]
    public function index(): void {}

    public function show(): void {}
}

function makeChecker(
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

function makeNullMatcher(): RouteMatcherInterface
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

function makeCacheCheckerRequest(string $method = 'GET', string $uri = '/'): Request
{
    return new Request(server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri]);
}

function makeCacheCheckerResponse(int $statusCode = 200, array $headers = []): Response
{
    return new Response(statusCode: $statusCode, headers: $headers);
}

// ─── isRequestCacheable ───────────────────────────────────────────────────────

it('accepts GET requests as cacheable', function (): void {
    $checker = makeChecker(makeNullMatcher());
    $request = makeCacheCheckerRequest('GET');

    expect($checker->isRequestCacheable($request))->toBeTrue();
});

it('accepts HEAD requests as cacheable', function (): void {
    $checker = makeChecker(makeNullMatcher());
    $request = makeCacheCheckerRequest('HEAD');

    expect($checker->isRequestCacheable($request))->toBeTrue();
});

it('rejects POST requests as not cacheable', function (): void {
    $checker = makeChecker(makeNullMatcher());
    $request = makeCacheCheckerRequest('POST');

    expect($checker->isRequestCacheable($request))->toBeFalse();
});

it('rejects requests with methods not in the configured cacheable methods list', function (): void {
    $checker = makeChecker(makeNullMatcher(), methods: ['GET']);
    $request = makeCacheCheckerRequest('HEAD');

    expect($checker->isRequestCacheable($request))->toBeFalse();
});

// ─── isResponseCacheable ─────────────────────────────────────────────────────

it('accepts responses with status code 200 as cacheable', function (): void {
    $checker = makeChecker(makeNullMatcher());
    $response = makeCacheCheckerResponse(200);

    expect($checker->isResponseCacheable($response))->toBeTrue();
});

it('rejects responses with status code 500 as not cacheable', function (): void {
    $checker = makeChecker(makeNullMatcher());
    $response = makeCacheCheckerResponse(500);

    expect($checker->isResponseCacheable($response))->toBeFalse();
});

it('rejects responses with status codes not in the configured cacheable status codes list', function (): void {
    $checker = makeChecker(makeNullMatcher(), statusCodes: [200]);
    $response = makeCacheCheckerResponse(301);

    expect($checker->isResponseCacheable($response))->toBeFalse();
});

it('rejects responses with a Set-Cookie header as not cacheable', function (): void {
    $checker = makeChecker(makeNullMatcher());
    $response = makeCacheCheckerResponse(200, ['Set-Cookie' => 'session=abc123']);

    expect($checker->isResponseCacheable($response))->toBeFalse();
});

it(
    'rejects responses whose Cache-Control header contains the no-store directive among others (e.g. "private, no-store, max-age=0")',
    function (): void {
        $checker = makeChecker(makeNullMatcher());
        $response = makeCacheCheckerResponse(200, ['Cache-Control' => 'private, no-store, max-age=0']);

        expect($checker->isResponseCacheable($response))->toBeFalse();
    },
);

it(
    'rejects responses whose Cache-Control header contains the private directive among others (e.g. "private, max-age=0")',
    function (): void {
        $checker = makeChecker(makeNullMatcher());
        $response = makeCacheCheckerResponse(200, ['Cache-Control' => 'private, max-age=0']);

        expect($checker->isResponseCacheable($response))->toBeFalse();
    },
);

it(
    'parses Cache-Control directives case-insensitively (e.g. "NO-STORE" rejected the same as "no-store")',
    function (): void {
        $checker = makeChecker(makeNullMatcher());
        $response = makeCacheCheckerResponse(200, ['Cache-Control' => 'NO-STORE']);

        expect($checker->isResponseCacheable($response))->toBeFalse();
    },
);

it(
    'accepts responses with a Cache-Control header that contains only public directives (e.g. "public, max-age=600")',
    function (): void {
        $checker = makeChecker(makeNullMatcher());
        $response = makeCacheCheckerResponse(200, ['Cache-Control' => 'public, max-age=600']);

        expect($checker->isResponseCacheable($response))->toBeTrue();
    },
);

// ─── getRouteAttribute ────────────────────────────────────────────────────────

it('returns the Cacheable attribute when the matched route declares it', function (): void {
    $matcher = new class () implements RouteMatcherInterface
    {
        public function match(
            string $method,
            string $path,
        ): ?MatchedRoute {
            $route = new RouteDefinition(
                method: 'GET',
                path: '/products',
                controller: CacheableActionController::class,
                action: 'index',
            );

            return new MatchedRoute($route);
        }
    };

    $checker = makeChecker($matcher);
    $request = makeCacheCheckerRequest('GET', '/products');

    $attribute = $checker->getRouteAttribute($request);

    expect($attribute)->toBeInstanceOf(Cacheable::class)
        ->and($attribute->ttl)->toBe(3600)
        ->and($attribute->tags)->toBe(['products']);
});

it('returns null when the matched route has no Cacheable attribute', function (): void {
    $matcher = new class () implements RouteMatcherInterface
    {
        public function match(
            string $method,
            string $path,
        ): ?MatchedRoute {
            $route = new RouteDefinition(
                method: 'GET',
                path: '/products/{id}',
                controller: CacheableActionController::class,
                action: 'show',
            );

            return new MatchedRoute($route);
        }
    };

    $checker = makeChecker($matcher);
    $request = makeCacheCheckerRequest('GET', '/products/1');

    expect($checker->getRouteAttribute($request))->toBeNull();
});

it('returns null when no route matches the request', function (): void {
    $checker = makeChecker(makeNullMatcher());
    $request = makeCacheCheckerRequest('GET', '/unknown');

    expect($checker->getRouteAttribute($request))->toBeNull();
});

it('returns null when the matched route\'s controller class does not exist (defensive)', function (): void {
    $matcher = new class () implements RouteMatcherInterface
    {
        public function match(
            string $method,
            string $path,
        ): ?MatchedRoute {
            $route = new RouteDefinition(
                method: 'GET',
                path: '/test',
                controller: 'NonExistentController',
                action: 'index',
            );

            return new MatchedRoute($route);
        }
    };

    $checker = makeChecker($matcher);
    $request = makeCacheCheckerRequest('GET', '/test');

    expect($checker->getRouteAttribute($request))->toBeNull();
});

it(
    'returns null when the matched route\'s action method does not exist on the controller (defensive)',
    function (): void {
        $matcher = new class () implements RouteMatcherInterface
        {
            public function match(
                string $method,
                string $path,
            ): ?MatchedRoute {
                $route = new RouteDefinition(
                    method: 'GET',
                    path: '/test',
                    controller: CacheableActionController::class,
                    action: 'nonExistentMethod',
                );

                return new MatchedRoute($route);
            }
        };

        $checker = makeChecker($matcher);
        $request = makeCacheCheckerRequest('GET', '/test');

        expect($checker->getRouteAttribute($request))->toBeNull();
    },
);
