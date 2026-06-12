<?php

declare(strict_types=1);

namespace Marko\AdminPanel\Tests\Unit\Controller\Login;

use Marko\Admin\Config\AdminConfigInterface;
use Marko\AdminPanel\Controller\LoginController;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Exceptions\CsrfTokenMismatchException;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeGuard;
use Marko\View\ViewInterface;
use ReflectionMethod;

// Stub for ViewInterface
class LoginStubView implements ViewInterface
{
    public string $lastTemplate = '';

    /** @var array<string, mixed> */
    public array $lastData = [];

    public function render(
        string $template,
        array $data = [],
    ): Response {
        $this->lastTemplate = $template;
        $this->lastData = $data;

        return Response::html('<html>login form</html>');
    }

    public function renderToString(
        string $template,
        array $data = [],
    ): string {
        $this->lastTemplate = $template;
        $this->lastData = $data;

        return '<html>login form</html>';
    }
}

// Stub for AdminConfigInterface
readonly class LoginStubAdminConfig implements AdminConfigInterface
{
    public function __construct(
        private string $routePrefix = '/admin',
        private string $name = 'Admin',
    ) {}

    public function getRoutePrefix(): string
    {
        return $this->routePrefix;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

// Stub for CsrfTokenManagerInterface
readonly class LoginStubCsrfTokenManager implements CsrfTokenManagerInterface
{
    public function __construct(
        private string $storedToken = 'test-csrf-token',
    ) {}

    public function get(): string
    {
        return $this->storedToken;
    }

    public function validate(string $token): bool
    {
        return hash_equals($this->storedToken, $token);
    }

    public function regenerate(): string
    {
        return $this->storedToken;
    }
}

it('rejects a POST to the admin login route when no CSRF token is supplied', function (): void {
    $csrfTokenManager = new LoginStubCsrfTokenManager('test-csrf-token');
    $middleware = new CsrfMiddleware(tokenManager: $csrfTokenManager);

    $middlewareAttributes = (new ReflectionMethod(LoginController::class, 'authenticate'))->getAttributes(
        Middleware::class,
    );

    expect($middlewareAttributes)->toHaveCount(1)
        ->and($middlewareAttributes[0]->newInstance()->middleware)->toContain(CsrfMiddleware::class);

    $request = new Request(server: ['REQUEST_METHOD' => 'POST']);

    $middleware->handle($request, fn (Request $r) => new Response('OK', 200));
})->throws(CsrfTokenMismatchException::class);

it('rejects a POST to the admin login route when the CSRF token is invalid', function (): void {
    $csrfTokenManager = new LoginStubCsrfTokenManager('test-csrf-token');
    $middleware = new CsrfMiddleware(tokenManager: $csrfTokenManager);

    $middlewareAttributes = (new ReflectionMethod(LoginController::class, 'authenticate'))->getAttributes(
        Middleware::class,
    );

    expect($middlewareAttributes)->toHaveCount(1)
        ->and($middlewareAttributes[0]->newInstance()->middleware)->toContain(CsrfMiddleware::class);

    $request = new Request(
        server: ['REQUEST_METHOD' => 'POST'],
        post: ['_token' => 'wrong-token'],
    );

    $middleware->handle($request, fn (Request $r) => new Response('OK', 200));
})->throws(CsrfTokenMismatchException::class);

it('allows a POST to the admin login route when a valid CSRF token is supplied', function (): void {
    $csrfTokenManager = new LoginStubCsrfTokenManager('test-csrf-token');
    $middleware = new CsrfMiddleware(tokenManager: $csrfTokenManager);

    $middlewareAttributes = (new ReflectionMethod(LoginController::class, 'authenticate'))->getAttributes(
        Middleware::class,
    );

    expect($middlewareAttributes)->toHaveCount(1)
        ->and($middlewareAttributes[0]->newInstance()->middleware)->toContain(CsrfMiddleware::class);

    $request = new Request(
        server: ['REQUEST_METHOD' => 'POST'],
        post: ['_token' => 'test-csrf-token'],
    );

    $response = $middleware->handle($request, fn (Request $r) => new Response('OK', 200));

    expect($response->statusCode())->toBe(200);
});

it('rejects a POST to the admin logout route when no valid CSRF token is supplied', function (): void {
    $csrfTokenManager = new LoginStubCsrfTokenManager('test-csrf-token');
    $middleware = new CsrfMiddleware(tokenManager: $csrfTokenManager);

    $middlewareAttributes = (new ReflectionMethod(LoginController::class, 'logout'))->getAttributes(
        Middleware::class,
    );

    expect($middlewareAttributes)->toHaveCount(1)
        ->and($middlewareAttributes[0]->newInstance()->middleware)->toContain(CsrfMiddleware::class);

    $request = new Request(server: ['REQUEST_METHOD' => 'POST']);

    $middleware->handle($request, fn (Request $r) => new Response('OK', 200));
})->throws(CsrfTokenMismatchException::class);

it('exposes a CSRF token value to the login view so the form can submit it', function (): void {
    $view = new LoginStubView();
    $guard = new FakeGuard(name: 'admin');
    $adminConfig = new LoginStubAdminConfig();
    $csrfTokenManager = new LoginStubCsrfTokenManager('test-csrf-token');

    $controller = new LoginController(
        view: $view,
        guard: $guard,
        adminConfig: $adminConfig,
        csrfTokenManager: $csrfTokenManager,
    );

    $request = new Request();
    $controller->showLoginForm($request);

    expect($view->lastData)->toHaveKey('csrfToken')
        ->and($view->lastData['csrfToken'])->toBe('test-csrf-token');
});

it('does not require a CSRF token for the GET login form route', function (): void {
    $middlewareAttributes = (new ReflectionMethod(LoginController::class, 'showLoginForm'))->getAttributes(
        Middleware::class,
    );

    expect($middlewareAttributes)->toBeEmpty();
});

it('redirects authenticated users from login page to dashboard', function (): void {
    $view = new LoginStubView();
    $guard = new FakeGuard(name: 'admin');
    $adminConfig = new LoginStubAdminConfig();

    // User is already authenticated
    $user = new FakeAuthenticatable(id: 1);
    $guard->setUser($user);

    $controller = new LoginController(
        view: $view,
        guard: $guard,
        adminConfig: $adminConfig,
        csrfTokenManager: new LoginStubCsrfTokenManager(),
    );

    $request = new Request();
    $response = $controller->showLoginForm($request);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($response->headers()['Location'])->toBe('/admin');
});

it('authenticates user on POST /admin/login with valid credentials', function (): void {
    $view = new LoginStubView();
    $guard = new FakeGuard(name: 'admin');
    $adminConfig = new LoginStubAdminConfig();
    $guard->setAttemptResult(true);

    $controller = new LoginController(
        view: $view,
        guard: $guard,
        adminConfig: $adminConfig,
        csrfTokenManager: new LoginStubCsrfTokenManager(),
    );

    $request = new Request(post: [
        'email' => 'admin@example.com',
        'password' => 'secret123',
    ]);
    $response = $controller->authenticate($request);

    $attempts = $guard->attempts;

    expect(end($attempts))->toBe([
        'email' => 'admin@example.com',
        'password' => 'secret123',
    ]);
});

it('redirects to dashboard after successful login', function (): void {
    $view = new LoginStubView();
    $guard = new FakeGuard(name: 'admin');
    $adminConfig = new LoginStubAdminConfig();
    $guard->setAttemptResult(true);

    $controller = new LoginController(
        view: $view,
        guard: $guard,
        adminConfig: $adminConfig,
        csrfTokenManager: new LoginStubCsrfTokenManager(),
    );

    $request = new Request(post: [
        'email' => 'admin@example.com',
        'password' => 'secret123',
    ]);
    $response = $controller->authenticate($request);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($response->headers()['Location'])->toBe('/admin');
});

it('returns to login with error on invalid credentials', function (): void {
    $view = new LoginStubView();
    $guard = new FakeGuard(name: 'admin', attemptResult: false);
    $adminConfig = new LoginStubAdminConfig();

    $controller = new LoginController(
        view: $view,
        guard: $guard,
        adminConfig: $adminConfig,
        csrfTokenManager: new LoginStubCsrfTokenManager(),
    );

    $request = new Request(post: [
        'email' => 'admin@example.com',
        'password' => 'wrongpassword',
    ]);
    $response = $controller->authenticate($request);

    expect($response->statusCode())->toBe(200)
        ->and($view->lastTemplate)->toBe('admin-panel::auth/login')
        ->and($view->lastData)->toHaveKey('error')
        ->and($view->lastData['error'])->toBe('Invalid email or password.')
        ->and($view->lastData)->toHaveKey('loginUrl')
        ->and($view->lastData['loginUrl'])->toBe('/admin/login');
});

it('logs out user on POST /admin/logout and redirects to login', function (): void {
    $view = new LoginStubView();
    $guard = new FakeGuard(name: 'admin');
    $adminConfig = new LoginStubAdminConfig();

    // User is authenticated
    $user = new FakeAuthenticatable(id: 1);
    $guard->setUser($user);

    $controller = new LoginController(
        view: $view,
        guard: $guard,
        adminConfig: $adminConfig,
        csrfTokenManager: new LoginStubCsrfTokenManager(),
    );

    $request = new Request();
    $response = $controller->logout($request);

    expect($guard->logoutCalled)->toBeTrue()
        ->and($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($response->headers()['Location'])->toBe('/admin/login');
});

it('renders login form on GET /admin/login', function (): void {
    $view = new LoginStubView();
    $guard = new FakeGuard(name: 'admin');
    $adminConfig = new LoginStubAdminConfig();

    $controller = new LoginController(
        view: $view,
        guard: $guard,
        adminConfig: $adminConfig,
        csrfTokenManager: new LoginStubCsrfTokenManager(),
    );

    $request = new Request();
    $response = $controller->showLoginForm($request);

    expect($response->statusCode())->toBe(200)
        ->and($response->headers())->toHaveKey('Content-Type')
        ->and($response->headers()['Content-Type'])->toContain('text/html')
        ->and($view->lastTemplate)->toBe('admin-panel::auth/login')
        ->and($view->lastData)->toHaveKey('loginUrl')
        ->and($view->lastData['loginUrl'])->toBe('/admin/login');
});
