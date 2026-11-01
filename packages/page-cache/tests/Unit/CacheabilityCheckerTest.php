<?php

declare(strict_types=1);

use Marko\PageCache\Attributes\Cacheable;
use Marko\PageCache\CacheabilityChecker;
use Marko\PageCache\Config\PageCacheConfig;
use Marko\Routing\Http\Cookie;
use Marko\Routing\Http\OutputStreamer;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Http\StreamedResponse;
use Marko\Routing\MatchedRoute;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcherInterface;
use Marko\Testing\Fake\FakeConfigRepository;

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
    array $bypassCookies = ['marko_session', 'remember_*'],
    array $trustedHosts = [],
    ?string $sessionCookieName = null,
): CacheabilityChecker {
    $values = [
        'page-cache.cacheable_methods' => $methods,
        'page-cache.cacheable_status_codes' => $statusCodes,
        'page-cache.bypass_cookies' => $bypassCookies,
        'page-cache.trusted_hosts' => $trustedHosts,
    ];

    if ($sessionCookieName !== null) {
        $values['session.cookie.name'] = $sessionCookieName;
    }

    return new CacheabilityChecker($matcher, new PageCacheConfig(new FakeConfigRepository($values)));
}

function makeNullMatcher(): RouteMatcherInterface
{
    return new class () implements RouteMatcherInterface
    {
        public function allowedMethods(
            string $path,
        ): array {
            return [];
        }

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

it('rejects requests carrying the default session cookie', function (): void {
    $checker = makeChecker(makeNullMatcher());
    $request = new Request(server: ['REQUEST_METHOD' => 'GET'], cookies: ['marko_session' => 'abc']);

    expect($checker->isRequestCacheable($request))->toBeFalse();
});

it('rejects requests carrying the session cookie name configured in session.cookie.name', function (): void {
    $checker = makeChecker(makeNullMatcher(), bypassCookies: [], sessionCookieName: 'shop_sid');
    $request = new Request(server: ['REQUEST_METHOD' => 'GET'], cookies: ['shop_sid' => 'abc']);

    expect($checker->isRequestCacheable($request))->toBeFalse();
});

it('rejects requests carrying a cookie matching a configured bypass cookie pattern', function (
    string $cookie,
): void {
    $checker = makeChecker(makeNullMatcher(), bypassCookies: ['remember_*', 'auth_token']);
    $request = new Request(server: ['REQUEST_METHOD' => 'GET'], cookies: [$cookie => 'value']);

    expect($checker->isRequestCacheable($request))->toBeFalse();
})->with(['remember_web', 'remember_admin', 'auth_token']);

it('accepts requests carrying only cookies that match no bypass pattern', function (): void {
    $checker = makeChecker(makeNullMatcher(), sessionCookieName: 'marko_session');
    $request = new Request(server: ['REQUEST_METHOD' => 'GET'], cookies: ['_ga' => 'x', 'theme' => 'dark']);

    expect($checker->isRequestCacheable($request))->toBeTrue();
});

it('rejects requests carrying any Authorization credentials', function (array $server): void {
    $checker = makeChecker(makeNullMatcher());
    $request = new Request(server: ['REQUEST_METHOD' => 'GET', ...$server]);

    expect($checker->isRequestCacheable($request))->toBeFalse();
})->with([
    'bearer header' => [['HTTP_AUTHORIZATION' => 'Bearer abc']],
    'basic header' => [['HTTP_AUTHORIZATION' => 'Basic YTpi']],
    'redirected header' => [['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer abc']],
    'php basic auth' => [['PHP_AUTH_USER' => 'alice']],
]);

it('accepts any host when no trusted hosts are configured', function (): void {
    $checker = makeChecker(makeNullMatcher());
    $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'anything.test']);

    expect($checker->isRequestCacheable($request))->toBeTrue();
});

it('rejects requests for a host outside the configured trusted hosts', function (): void {
    $checker = makeChecker(makeNullMatcher(), trustedHosts: ['example.com', '*.example.com']);

    $trusted = new Request(server: ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'Example.com:8080']);
    $subdomain = new Request(server: ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'shop.example.com']);
    $untrusted = new Request(server: ['REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'evil.test']);
    $missing = new Request(server: ['REQUEST_METHOD' => 'GET']);

    expect($checker->isRequestCacheable($trusted))->toBeTrue()
        ->and($checker->isRequestCacheable($subdomain))->toBeTrue()
        ->and($checker->isRequestCacheable($untrusted))->toBeFalse()
        ->and($checker->isRequestCacheable($missing))->toBeFalse();
});

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

it('rejects a streamed response as not cacheable even with a cacheable status', function (): void {
    $checker = makeChecker(makeNullMatcher());
    $response = new StreamedResponse(function (OutputStreamer $outputStreamer): void {
        $outputStreamer->write('generated');
    });

    expect($response->statusCode())->toBe(200)
        ->and($checker->isResponseCacheable($response))->toBeFalse();
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

it('does not cache a response that carries cookies', function (): void {
    $checker = makeChecker(makeNullMatcher());
    $response = makeCacheCheckerResponse(200)->withCookie(new Cookie(name: 'session', value: 'abc123'));

    expect($checker->isResponseCacheable($response))->toBeFalse();
});

it('still caches a response that carries no cookies', function (): void {
    $checker = makeChecker(makeNullMatcher());
    $response = makeCacheCheckerResponse(200);

    expect($checker->isResponseCacheable($response))->toBeTrue();
});

it('still refuses to cache a response carrying a literal set-cookie header', function (): void {
    $checker = makeChecker(makeNullMatcher());
    $response = makeCacheCheckerResponse(200, ['Set-Cookie' => 'session=abc123']);

    expect($checker->isResponseCacheable($response))->toBeFalse();
});

// ─── getRouteAttribute ────────────────────────────────────────────────────────

it('returns the Cacheable attribute when the matched route declares it', function (): void {
    $matcher = new class () implements RouteMatcherInterface
    {
        public function allowedMethods(
            string $path,
        ): array {
            return [];
        }

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
        public function allowedMethods(
            string $path,
        ): array {
            return [];
        }

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
        public function allowedMethods(
            string $path,
        ): array {
            return [];
        }

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
            public function allowedMethods(
                string $path,
            ): array {
                return [];
            }

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
