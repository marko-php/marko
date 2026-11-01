<?php

declare(strict_types=1);

use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\RateLimiter\ClientIpKeyResolver;
use Marko\RateLimiter\ClientIpResolver;
use Marko\RateLimiter\Config\RateLimiterConfig;
use Marko\RateLimiter\Contracts\RateLimiterInterface;
use Marko\RateLimiter\Contracts\RateLimitKeyResolverInterface;
use Marko\RateLimiter\Exceptions\ClientIpException;
use Marko\RateLimiter\Middleware\RateLimitMiddleware;
use Marko\RateLimiter\RateLimiter;
use Marko\RateLimiter\RateLimitResult;

use function Marko\RateLimiter\Tests\createTaggedResponse;

use Marko\RateLimiter\Tests\Fixtures\PlainController;
use Marko\RateLimiter\Tests\Fixtures\RateLimitedController;
use Marko\RateLimiter\Tests\TaggedResponse;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * Records every attempt() call and answers with a fixed result.
 */
class RecordingRateLimiter implements RateLimiterInterface
{
    /** @var list<array{key: string, maxAttempts: int, decaySeconds: int}> */
    public array $attempts = [];

    public function __construct(
        private readonly RateLimitResult $result = new RateLimitResult(allowed: true, remaining: 59),
    ) {}

    public function attempt(
        string $key,
        int $maxAttempts,
        int $decaySeconds,
    ): RateLimitResult {
        $this->attempts[] = ['key' => $key, 'maxAttempts' => $maxAttempts, 'decaySeconds' => $decaySeconds];

        return $this->result;
    }

    public function tooManyAttempts(
        string $key,
        int $maxAttempts,
    ): bool {
        return !$this->result->allowed();
    }

    public function clear(
        string $key,
    ): void {}
}

function createMiddlewareKeyResolver(
    array $trustedProxies = [],
): RateLimitKeyResolverInterface {
    $config = new FakeConfigRepository([
        'ratelimiter.trusted_proxies' => $trustedProxies,
        'ratelimiter.ipv6_prefix' => 64,
    ]);

    return new ClientIpKeyResolver(new ClientIpResolver($config), new RateLimiterConfig($config));
}

function createMiddlewareConfig(
    int $maxAttempts = 60,
    int $decaySeconds = 60,
): RateLimiterConfig {
    return new RateLimiterConfig(new FakeConfigRepository([
        'ratelimiter.default_max_attempts' => $maxAttempts,
        'ratelimiter.default_decay_seconds' => $decaySeconds,
    ]));
}

function createRateLimitMiddleware(
    RateLimiterInterface $rateLimiter,
    ?RateLimiterConfig $rateLimiterConfig = null,
): RateLimitMiddleware {
    return new RateLimitMiddleware(
        $rateLimiter,
        createMiddlewareKeyResolver(),
        $rateLimiterConfig ?? createMiddlewareConfig(),
    );
}

function createRealRateLimiter(): RateLimiter
{
    $clock = new FakeClock();

    return new RateLimiter(new ArrayCacheDriver(new CacheConfig(new FakeConfigRepository([
        'cache.path' => '/tmp/cache',
        'cache.default_ttl' => 3600,
        'cache.driver' => 'array',
    ])), $clock, new CacheOperations()), $clock);
}

function createRoutedRequest(
    string $controller,
    string $action,
    string $ip = '10.0.0.1',
): Request {
    return new Request(server: ['REMOTE_ADDR' => $ip])->withRoute($controller, $action);
}

function okHandler(): Closure
{
    return fn (Request $request): Response => new Response('OK');
}

describe('RateLimitMiddleware', function (): void {
    it('implements MiddlewareInterface', function (): void {
        expect(createRateLimitMiddleware(new RecordingRateLimiter()))
            ->toBeInstanceOf(MiddlewareInterface::class);
    });

    it('passes the request to the next handler when allowed', function (): void {
        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;

            return new Response('OK');
        };

        $response = createRateLimitMiddleware(new RecordingRateLimiter())
            ->handle(new Request(server: ['REMOTE_ADDR' => '10.0.0.1']), $next);

        expect($nextCalled)->toBeTrue()
            ->and($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('OK');
    });

    it('includes rate limit headers on allowed response', function (): void {
        $middleware = createRateLimitMiddleware(
            new RecordingRateLimiter(new RateLimitResult(allowed: true, remaining: 42)),
            createMiddlewareConfig(maxAttempts: 100),
        );

        $response = $middleware->handle(new Request(server: ['REMOTE_ADDR' => '10.0.0.1']), okHandler());

        expect($response->headers()['X-RateLimit-Limit'])->toBe('100')
            ->and($response->headers()['X-RateLimit-Remaining'])->toBe('42');
    });

    it('returns a json 429 with rate limit headers', function (): void {
        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled): Response {
            $nextCalled = true;

            return new Response('OK');
        };
        $middleware = createRateLimitMiddleware(
            new RecordingRateLimiter(new RateLimitResult(allowed: false, remaining: 0, retryAfter: 45)),
            createMiddlewareConfig(maxAttempts: 100),
        );

        $response = $middleware->handle(new Request(server: ['REMOTE_ADDR' => '10.0.0.1']), $next);

        expect($nextCalled)->toBeFalse()
            ->and($response->statusCode())->toBe(429)
            ->and(json_decode($response->body(), true))->toBe(['message' => 'Too Many Requests'])
            ->and($response->headers())->toMatchArray([
                'Content-Type' => 'application/json',
                'Retry-After' => '45',
                'X-RateLimit-Limit' => '100',
                'X-RateLimit-Remaining' => '0',
            ]);
    });

    it('throws ClientIpException when REMOTE_ADDR is absent', function (): void {
        $middleware = createRateLimitMiddleware(new RecordingRateLimiter());

        expect(fn (): Response => $middleware->handle(new Request(), okHandler()))
            ->toThrow(ClientIpException::class);
    });

    it('preserves the response subclass through rate limit middleware', function (): void {
        $middleware = createRateLimitMiddleware(new RecordingRateLimiter());
        $next = fn (Request $request): TaggedResponse => createTaggedResponse(tag: 'from-controller');

        $response = $middleware->handle(new Request(server: ['REMOTE_ADDR' => '10.0.0.1']), $next);

        /** @var TaggedResponse $response */
        expect($response)->toBeInstanceOf(TaggedResponse::class)
            ->and($response->tag)->toBe('from-controller')
            ->and($response->headers())->toHaveKey('X-RateLimit-Limit');
    });

    it('applies config defaults when no attribute is present', function (): void {
        $rateLimiter = new RecordingRateLimiter();
        $middleware = createRateLimitMiddleware(
            $rateLimiter,
            createMiddlewareConfig(maxAttempts: 7, decaySeconds: 90),
        );

        $middleware->handle(createRoutedRequest(PlainController::class, 'index'), okHandler());

        expect($rateLimiter->attempts[0]['maxAttempts'])->toBe(7)
            ->and($rateLimiter->attempts[0]['decaySeconds'])->toBe(90);
    });

    it('applies config defaults when the request has no route', function (): void {
        $rateLimiter = new RecordingRateLimiter();
        $middleware = createRateLimitMiddleware(
            $rateLimiter,
            createMiddlewareConfig(maxAttempts: 7, decaySeconds: 90),
        );

        $middleware->handle(new Request(server: ['REMOTE_ADDR' => '203.0.113.50']), okHandler());

        expect($rateLimiter->attempts[0])->toBe([
            'key' => 'global|203.0.113.50',
            'maxAttempts' => 7,
            'decaySeconds' => 90,
        ]);
    });

    it('applies the class attribute limits', function (): void {
        $rateLimiter = new RecordingRateLimiter();

        createRateLimitMiddleware($rateLimiter)
            ->handle(createRoutedRequest(RateLimitedController::class, 'index'), okHandler());

        expect($rateLimiter->attempts[0]['maxAttempts'])->toBe(10)
            ->and($rateLimiter->attempts[0]['decaySeconds'])->toBe(120);
    });

    it('lets the method attribute override the class attribute', function (): void {
        $rateLimiter = new RecordingRateLimiter();

        createRateLimitMiddleware($rateLimiter)
            ->handle(createRoutedRequest(RateLimitedController::class, 'login'), okHandler());

        expect($rateLimiter->attempts[0]['maxAttempts'])->toBe(2)
            ->and($rateLimiter->attempts[0]['decaySeconds'])->toBe(30);
    });

    it('falls back to config for limits the attribute leaves unset', function (): void {
        $rateLimiter = new RecordingRateLimiter();

        createRateLimitMiddleware($rateLimiter, createMiddlewareConfig(decaySeconds: 75))
            ->handle(createRoutedRequest(RateLimitedController::class, 'register'), okHandler());

        expect($rateLimiter->attempts[0]['maxAttempts'])->toBe(2)
            ->and($rateLimiter->attempts[0]['decaySeconds'])->toBe(75);
    });

    it('keys the bucket by controller action and client ip', function (): void {
        $rateLimiter = new RecordingRateLimiter();

        createRateLimitMiddleware($rateLimiter)
            ->handle(createRoutedRequest(PlainController::class, 'show', '203.0.113.50'), okHandler());

        expect($rateLimiter->attempts[0]['key'])->toBe(PlainController::class . '::show|203.0.113.50');
    });

    it('keys the bucket by attribute name when one is given', function (): void {
        $rateLimiter = new RecordingRateLimiter();

        createRateLimitMiddleware($rateLimiter)
            ->handle(createRoutedRequest(RateLimitedController::class, 'register', '203.0.113.50'), okHandler());

        expect($rateLimiter->attempts[0]['key'])->toBe('auth|203.0.113.50');
    });

    it('keeps separate counters for two routes from the same ip', function (): void {
        $middleware = createRateLimitMiddleware(createRealRateLimiter());

        $loginStatuses = [];

        for ($i = 0; $i < 3; $i++) {
            $loginStatuses[] = $middleware
                ->handle(createRoutedRequest(RateLimitedController::class, 'login'), okHandler())
                ->statusCode();
        }

        $index = $middleware->handle(createRoutedRequest(RateLimitedController::class, 'index'), okHandler());

        expect($loginStatuses)->toBe([200, 200, 429])
            ->and($index->statusCode())->toBe(200)
            ->and($index->headers()['X-RateLimit-Remaining'])->toBe('9');
    });

    it('shares a bucket across routes with the same attribute name', function (): void {
        $middleware = createRateLimitMiddleware(createRealRateLimiter());

        $statuses = [
            $middleware->handle(
                createRoutedRequest(RateLimitedController::class, 'register'),
                okHandler(),
            )->statusCode(),
            $middleware->handle(
                createRoutedRequest(RateLimitedController::class, 'passwordReset'),
                okHandler(),
            )->statusCode(),
            $middleware->handle(
                createRoutedRequest(RateLimitedController::class, 'register'),
                okHandler(),
            )->statusCode(),
        ];

        expect($statuses)->toBe([200, 200, 429]);
    });

    it('keeps separate counters for different clients on the same route', function (): void {
        $middleware = createRateLimitMiddleware(createRealRateLimiter());

        $middleware->handle(createRoutedRequest(RateLimitedController::class, 'login', '10.0.0.1'), okHandler());
        $middleware->handle(createRoutedRequest(RateLimitedController::class, 'login', '10.0.0.1'), okHandler());

        $other = $middleware->handle(
            createRoutedRequest(RateLimitedController::class, 'login', '10.0.0.2'),
            okHandler(),
        );

        expect($other->statusCode())->toBe(200);
    });

    it('limits an IPv6 client end to end', function (): void {
        $middleware = createRateLimitMiddleware(createRealRateLimiter());

        $statuses = [];

        for ($i = 0; $i < 3; $i++) {
            $statuses[] = $middleware
                ->handle(createRoutedRequest(RateLimitedController::class, 'login', '2001:db8::1'), okHandler())
                ->statusCode();
        }

        expect($statuses)->toBe([200, 200, 429]);
    });

    it('uses the bound key resolver for the client identity', function (): void {
        $rateLimiter = new RecordingRateLimiter();
        $keyResolver = new readonly class () implements RateLimitKeyResolverInterface
        {
            public function resolve(
                Request $request,
            ): string {
                return 'user-42';
            }
        };

        new RateLimitMiddleware($rateLimiter, $keyResolver, createMiddlewareConfig())
            ->handle(createRoutedRequest(PlainController::class, 'index'), okHandler());

        expect($rateLimiter->attempts[0]['key'])->toBe(PlainController::class . '::index|user-42');
    });

    it('reflects on each controller action only once', function (): void {
        $middleware = createRateLimitMiddleware(new RecordingRateLimiter());

        $middleware->handle(createRoutedRequest(RateLimitedController::class, 'login'), okHandler());
        $middleware->handle(createRoutedRequest(RateLimitedController::class, 'login'), okHandler());
        $middleware->handle(createRoutedRequest(PlainController::class, 'index'), okHandler());

        $cache = new ReflectionProperty(RateLimitMiddleware::class, 'rateLimits')->getValue($middleware);

        expect(array_keys($cache))->toBe([
            RateLimitedController::class . '::login',
            PlainController::class . '::index',
        ]);
    });
});
