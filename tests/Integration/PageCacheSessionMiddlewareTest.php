<?php

declare(strict_types=1);

use Marko\PageCache\CacheabilityChecker;
use Marko\PageCache\Config\PageCacheConfig;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\MatchedRoute;
use Marko\Routing\RouteMatcherInterface;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\Testing\Fake\FakeConfigRepository;

function makeRepeatVisitSession(): SessionInterface
{
    return new class () implements SessionInterface
    {
        public private(set) bool $started = true;

        /** @var array<string, mixed> */
        private array $data = [];

        public function start(): void
        {
            $this->started = true;
        }

        public function get(
            string $key,
            mixed $default = null,
        ): mixed {
            return $this->data[$key] ?? $default;
        }

        public function set(
            string $key,
            mixed $value,
        ): void {
            $this->data[$key] = $value;
        }

        public function has(string $key): bool
        {
            return isset($this->data[$key]);
        }

        public function remove(string $key): void
        {
            unset($this->data[$key]);
        }

        public function clear(): void
        {
            $this->data = [];
        }

        /**
         * @return array<string, mixed>
         */
        public function all(): array
        {
            return $this->data;
        }

        public function regenerate(bool $deleteOldSession = true): void {}

        public function destroy(): void {}

        public function getId(): string
        {
            return 'existing-session-id';
        }

        public function setId(string $id): void {}

        public function flash(): FlashBag
        {
            return new FlashBag($this->data);
        }

        public function save(): void {}
    };
}

function makePageCacheabilityChecker(): CacheabilityChecker
{
    $matcher = new class () implements RouteMatcherInterface
    {
        public function match(
            string $method,
            string $path,
        ): ?MatchedRoute {
            return null;
        }
    };

    $config = new PageCacheConfig(new FakeConfigRepository([
        'page-cache.cacheable_methods' => ['GET', 'HEAD'],
        'page-cache.cacheable_status_codes' => [200],
    ]));

    return new CacheabilityChecker($matcher, $config);
}

function makeSessionConfig(): SessionConfig
{
    return new SessionConfig(new FakeConfigRepository([
        'session.driver' => 'array',
        'session.lifetime' => 120,
        'session.expire_on_close' => false,
        'session.path' => '/tmp',
        'session.cookie.name' => 'marko_session',
        'session.cookie.path' => '/',
        'session.cookie.domain' => '',
        'session.cookie.secure' => true,
        'session.cookie.httponly' => true,
        'session.cookie.samesite' => 'lax',
        'session.gc_probability' => 2,
        'session.gc_divisor' => 100,
    ]));
}

it(
    'still caches a repeat visit response that passed through session middleware without a new session',
    function (): void {
        $session = makeRepeatVisitSession();
        $sessionConfig = makeSessionConfig();
        $middleware = new SessionMiddleware($session, $sessionConfig);
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            cookies: [$sessionConfig->cookieName() => 'existing-session-id'],
        );

        $response = $middleware->handle($request, fn (Request $request): Response => new Response(
            body: 'cached page',
            statusCode: 200,
        ));

        $checker = makePageCacheabilityChecker();

        expect($response->cookies())->toBeEmpty()
            ->and($checker->isResponseCacheable($response))->toBeTrue();
    },
);
