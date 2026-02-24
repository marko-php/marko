<?php

declare(strict_types=1);

use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserProvider;

// Helper function to create AuthManager with authenticated user
function createAuthManagerWithUser(
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

test('it allows authenticated users through', function (): void {
    $user = new FakeAuthenticatable(id: 1);
    $authManager = createAuthManagerWithUser($user);

    $middleware = new AuthMiddleware($authManager);

    $request = new Request();
    $expectedResponse = new Response(body: 'success', statusCode: 200);

    $response = $middleware->handle(
        $request,
        fn (Request $r) => $expectedResponse,
    );

    expect($response)->toBe($expectedResponse)
        ->and($response->statusCode())->toBe(200);
});

test('it blocks unauthenticated users', function (): void {
    $authManager = createAuthManagerWithUser(); // No user

    $middleware = new AuthMiddleware($authManager);

    $request = new Request();
    $nextCalled = false;

    $response = $middleware->handle(
        $request,
        function () use (&$nextCalled): Response {
            $nextCalled = true;

            return new Response(body: 'success', statusCode: 200);
        },
    );

    expect($nextCalled)->toBeFalse()
        ->and($response->statusCode())->not->toBe(200);
});

test('it returns 401 for API guard when unauthenticated', function (): void {
    $authManager = createAuthManagerWithUser();

    $middleware = new AuthMiddleware(
        auth: $authManager,
        guard: 'api',
    );

    $request = new Request();

    $response = $middleware->handle(
        $request,
        fn (Request $r) => new Response(body: 'success', statusCode: 200),
    );

    expect($response->statusCode())->toBe(401)
        ->and($response->headers())->toHaveKey('Content-Type')
        ->and($response->headers()['Content-Type'])->toBe('application/json');
});

test('it redirects for web guard when unauthenticated', function (): void {
    $authManager = createAuthManagerWithUser();

    $middleware = new AuthMiddleware(
        auth: $authManager,
        guard: 'web',
        redirectTo: '/login',
    );

    $request = new Request();

    $response = $middleware->handle(
        $request,
        fn (Request $r) => new Response(body: 'success', statusCode: 200),
    );

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($response->headers()['Location'])->toBe('/login');
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

    // Middleware using 'api' guard should fail (user not authenticated on api guard)
    $middleware = new AuthMiddleware(
        auth: $authManager,
        guard: 'api',
    );

    $request = new Request();

    $response = $middleware->handle(
        $request,
        fn (Request $r) => new Response(body: 'success', statusCode: 200),
    );

    // API guard returns 401 JSON because user is not authenticated on API guard
    expect($response->statusCode())->toBe(401);
});

test('it uses default guard when not specified', function (): void {
    $user = new FakeAuthenticatable(id: 1);
    $authManager = createAuthManagerWithUser($user);

    // Middleware without guard parameter uses default (web)
    $middleware = new AuthMiddleware(
        auth: $authManager,
    );

    $request = new Request();
    $expectedResponse = new Response(body: 'success', statusCode: 200);

    $response = $middleware->handle(
        $request,
        fn (Request $r) => $expectedResponse,
    );

    // User is authenticated on default guard, so request passes through
    expect($response)->toBe($expectedResponse)
        ->and($response->statusCode())->toBe(200);
});
