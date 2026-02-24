<?php

declare(strict_types=1);

use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Middleware\GuestMiddleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserProvider;

// Helper function to create AuthManager with authenticated user
function createGuestAuthManagerWithUser(
    ?FakeAuthenticatable $user = null,
): AuthManager {
    $configRepo = new FakeConfigRepository([
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'api' => ['driver' => 'token', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new FakeSession();
    $session->start();
    $provider = $user !== null
        ? new FakeUserProvider([$user->getAuthIdentifier() => $user])
        : new FakeUserProvider();

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        provider: $provider,
    );

    // If user provided, authenticate them
    if ($user !== null) {
        $manager->attempt(['email' => 'test@example.com', 'password' => 'password']);
    }

    return $manager;
}

test('GuestMiddleware allows unauthenticated users', function (): void {
    $authManager = createGuestAuthManagerWithUser(); // No user

    $middleware = new GuestMiddleware(
        auth: $authManager,
        redirectTo: '/dashboard',
    );

    $request = new Request();
    $expectedResponse = new Response(body: 'login page', statusCode: 200);

    $response = $middleware->handle(
        $request,
        fn (Request $r) => $expectedResponse,
    );

    expect($response)->toBe($expectedResponse)
        ->and($response->statusCode())->toBe(200);
});

test('GuestMiddleware redirects authenticated users', function (): void {
    $user = new FakeAuthenticatable(id: 1);
    $authManager = createGuestAuthManagerWithUser($user);

    $middleware = new GuestMiddleware(
        auth: $authManager,
        redirectTo: '/dashboard',
    );

    $request = new Request();
    $nextCalled = false;

    $response = $middleware->handle(
        $request,
        function () use (&$nextCalled): Response {
            $nextCalled = true;

            return new Response(body: 'login page', statusCode: 200);
        },
    );

    expect($nextCalled)->toBeFalse()
        ->and($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($response->headers()['Location'])->toBe('/dashboard');
});

test('it supports configurable redirect URL', function (): void {
    $user = new FakeAuthenticatable(id: 1);
    $authManager = createGuestAuthManagerWithUser($user);

    // Custom redirect URL
    $middleware = new GuestMiddleware(
        auth: $authManager,
        redirectTo: '/custom/home',
    );

    $request = new Request();

    $response = $middleware->handle(
        $request,
        fn (Request $r) => new Response(body: 'login page', statusCode: 200),
    );

    expect($response->statusCode())->toBe(302)
        ->and($response->headers()['Location'])->toBe('/custom/home');
});

test('it supports specifying guard via parameter', function (): void {
    $user = new FakeAuthenticatable(id: 1);
    $configRepo = new FakeConfigRepository([
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'api' => ['driver' => 'token', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider([1 => $user]);

    $authManager = new AuthManager(
        config: $authConfig,
        session: $session,
        provider: $provider,
    );

    // Authenticate on web guard
    $authManager->attempt(['email' => 'test@example.com', 'password' => 'password']);

    // GuestMiddleware using 'api' guard should allow through (user not authenticated on api guard)
    $middleware = new GuestMiddleware(
        auth: $authManager,
        redirectTo: '/dashboard',
        guard: 'api',
    );

    $request = new Request();
    $expectedResponse = new Response(body: 'login page', statusCode: 200);

    $response = $middleware->handle(
        $request,
        fn (Request $r) => $expectedResponse,
    );

    // API guard user is not authenticated, so request passes through
    expect($response)->toBe($expectedResponse)
        ->and($response->statusCode())->toBe(200);
});
