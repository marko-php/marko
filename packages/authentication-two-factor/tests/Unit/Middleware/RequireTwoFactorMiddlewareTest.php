<?php

declare(strict_types=1);

use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Authentication\UserProviderResolver;
use Marko\AuthenticationTwoFactor\Exceptions\TwoFactorException;
use Marko\AuthenticationTwoFactor\Exceptions\TwoFactorRequiredException;
use Marko\AuthenticationTwoFactor\Middleware\RequireTwoFactorMiddleware;
use Marko\AuthenticationTwoFactor\Tests\Fixtures\TwoFactorHarness;
use Marko\Core\Container\Container;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeCookieJar;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserProvider;

function requireTwoFactorMiddleware(
    TwoFactorHarness $harness,
): RequireTwoFactorMiddleware {
    return new RequireTwoFactorMiddleware(
        authManager: $harness->authManager(),
        twoFactorManager: $harness->manager(),
        twoFactorConfig: $harness->twoFactorConfig(),
    );
}

function okResponse(): Closure
{
    return fn (Request $request): Response => new Response(body: 'ok');
}

it('is middleware', function (): void {
    expect(requireTwoFactorMiddleware(new TwoFactorHarness()))->toBeInstanceOf(MiddlewareInterface::class);
});

it('lets a user with confirmed two-factor through', function (): void {
    $harness = new TwoFactorHarness();
    $harness->authManager()->guard()->login($harness->user);
    $harness->enableTwoFactor();

    $response = requireTwoFactorMiddleware($harness)->handle(new Request(), okResponse());

    expect($response->body())->toBe('ok');
});

it('redirects a browser request from an unconfirmed user to the setup route', function (): void {
    $harness = new TwoFactorHarness(['authentication-two-factor.setup_route' => '/account/two-factor']);
    $harness->authManager()->guard()->login($harness->user);
    $harness->manager()->enable($harness->user, 'ada@example.com');

    $response = requireTwoFactorMiddleware($harness)->handle(new Request(), okResponse());

    expect($response->statusCode())->toBe(302)
        ->and($response->headers()['Location'])->toBe('/account/two-factor');
});

it('rejects a JSON request from an unconfirmed user with a 403', function (): void {
    $harness = new TwoFactorHarness();
    $harness->authManager()->guard()->login($harness->user);

    try {
        requireTwoFactorMiddleware($harness)->handle(
            new Request(server: ['HTTP_ACCEPT' => 'application/json']),
            okResponse(),
        );
    } catch (TwoFactorRequiredException $exception) {
        expect($exception->getStatusCode())->toBe(403);

        return;
    }

    throw new RuntimeException('Expected TwoFactorRequiredException');
});

it('throws a 401 when no user is authenticated', function (): void {
    requireTwoFactorMiddleware(new TwoFactorHarness())->handle(new Request(), okResponse());
})->throws(UnauthenticatedException::class);

it('fails loudly for a user entity without two-factor support', function (): void {
    $harness = new TwoFactorHarness();
    $session = new FakeSession();
    $session->start();
    $user = new FakeAuthenticatable(id: 1);
    $container = new Container();
    $container->instance(UserProviderInterface::class, new FakeUserProvider([1 => $user]));
    $config = new AuthConfig(new FakeConfigRepository([
        'authentication.default.guard' => 'web',
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.guards' => ['web' => ['driver' => 'session']],
    ]));
    $authManager = new AuthManager(
        config: $config,
        session: $session,
        providerResolver: new UserProviderResolver($config, $container),
        eventDispatcher: new FakeEventDispatcher(),
        cookieJar: new FakeCookieJar(),
        rememberTokenManager: new RememberTokenManager(new FakeClock()),
    );
    $authManager->guard()->login($user);

    new RequireTwoFactorMiddleware($authManager, $harness->manager(), $harness->twoFactorConfig())
        ->handle(new Request(), okResponse());
})->throws(TwoFactorException::class, 'does not implement TwoFactorAuthenticatableInterface');
