<?php

declare(strict_types=1);

use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\PasswordConfirmationRequiredException;
use Marko\Authentication\Hashing\BcryptPasswordHasher;
use Marko\Authentication\Hashing\PasswordVerifier;
use Marko\Authentication\Http\IntendedUrl;
use Marko\Authentication\Middleware\RequirePasswordConfirmationMiddleware;
use Marko\Authentication\PasswordConfirmation\PasswordConfirmation;
use Marko\Authentication\Tests\Fixtures\StatelessFakeGuard;
use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\RateLimiter\RateLimiter;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeGuard;
use Marko\Testing\Fake\FakeSession;

/**
 * The middleware and its real collaborators over a fake session, clock and guard.
 */
class PasswordConfirmationMiddlewareHarness
{
    public FakeClock $clock;

    public FakeSession $session;

    public PasswordConfirmation $confirmation;

    public IntendedUrl $intendedUrl;

    public FakeAuthenticatable $user;

    private AuthConfig $authConfig;

    public function __construct()
    {
        $this->clock = new FakeClock('2026-06-01 12:00:00');
        $this->session = new FakeSession();
        $this->session->start();
        $hasher = new BcryptPasswordHasher(cost: 4);
        $this->user = new FakeAuthenticatable(id: 1, password: $hasher->hash('secret'));
        $this->authConfig = new AuthConfig(new FakeConfigRepository([
            'authentication.password_confirmation.timeout_seconds' => 10800,
            'authentication.password_confirmation.route' => '/confirm-password',
            'authentication.password_confirmation.throttle.max_attempts' => 5,
            'authentication.password_confirmation.throttle.decay_seconds' => 60,
        ]));
        $cache = new ArrayCacheDriver(new CacheConfig(new FakeConfigRepository([
            'cache.path' => sys_get_temp_dir(),
            'cache.default_ttl' => 3600,
            'cache.driver' => 'array',
        ])), $this->clock, new CacheOperations());
        $this->confirmation = new PasswordConfirmation(
            passwordVerifier: new PasswordVerifier($hasher),
            session: $this->session,
            clock: $this->clock,
            rateLimiter: new RateLimiter($cache, $this->clock),
            authConfig: $this->authConfig,
        );
        $this->intendedUrl = new IntendedUrl($this->session);
    }

    public function middleware(
        GuardInterface $guard = new FakeGuard(name: 'session'),
    ): RequirePasswordConfirmationMiddleware {
        return new RequirePasswordConfirmationMiddleware(
            guard: $guard,
            passwordConfirmation: $this->confirmation,
            intendedUrl: $this->intendedUrl,
            authConfig: $this->authConfig,
        );
    }

    /**
     * @param array<string, string> $headers
     */
    public static function request(
        string $uri = '/settings/two-factor',
        array $headers = [],
    ): Request {
        $server = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri];

        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return new Request(server: $server);
    }
}

function passwordConfirmationNext(): Closure
{
    return fn (Request $request): Response => new Response(body: 'sensitive page');
}

it('lets a recently confirmed user through', function (): void {
    $harness = new PasswordConfirmationMiddlewareHarness();
    $harness->confirmation->confirm($harness->user, 'secret');

    $response = $harness->middleware()->handle(
        PasswordConfirmationMiddlewareHarness::request(),
        passwordConfirmationNext(),
    );

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('sensitive page');
});

it('redirects to the confirmation route once the confirmation is stale', function (): void {
    $harness = new PasswordConfirmationMiddlewareHarness();
    $harness->confirmation->confirm($harness->user, 'secret');
    $harness->clock->travel('+3 hours');

    $response = $harness->middleware()->handle(
        PasswordConfirmationMiddlewareHarness::request(),
        passwordConfirmationNext(),
    );

    expect($response->statusCode())->toBe(302)
        ->and($response->headers()['Location'])->toBe('/confirm-password');
});

it('redirects an unconfirmed user and sends them to the intended URL after confirming', function (): void {
    $harness = new PasswordConfirmationMiddlewareHarness();
    $request = PasswordConfirmationMiddlewareHarness::request('/settings/two-factor?step=2');

    $response = $harness->middleware()->handle($request, passwordConfirmationNext());
    $confirmed = $harness->confirmation->confirm($harness->user, 'secret');

    expect($response->headers()['Location'])->toBe('/confirm-password')
        ->and($confirmed)->toBeTrue()
        ->and($harness->intendedUrl->pull('/home'))->toBe('/settings/two-factor?step=2')
        ->and($harness->middleware()->handle($request, passwordConfirmationNext())->body())->toBe('sensitive page');
});

it('answers a JSON request with a 423 instead of a redirect', function (): void {
    $harness = new PasswordConfirmationMiddlewareHarness();
    $request = PasswordConfirmationMiddlewareHarness::request(headers: ['Accept' => 'application/json']);

    try {
        $harness->middleware()->handle($request, passwordConfirmationNext());
        $this->fail('Expected PasswordConfirmationRequiredException');
    } catch (PasswordConfirmationRequiredException $exception) {
        expect($exception->getStatusCode())->toBe(423)
            ->and($exception->getResponseData())->toBe(['message' => 'Password confirmation required.'])
            ->and($harness->intendedUrl->pull('/home'))->toBe('/home');
    }
});

it('throws a configuration error on a stateless guard', function (): void {
    $harness = new PasswordConfirmationMiddlewareHarness();
    $harness->confirmation->markConfirmed();

    expect(fn () => $harness->middleware(new StatelessFakeGuard(name: 'token'))
        ->handle(PasswordConfirmationMiddlewareHarness::request(), passwordConfirmationNext()))
        ->toThrow(AuthException::class, "RequirePasswordConfirmationMiddleware cannot run on stateless guard 'token'");
});
