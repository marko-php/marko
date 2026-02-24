<?php

declare(strict_types=1);

namespace Marko\AdminPanel\Tests\Unit\Controller\Login;

use Marko\Admin\Config\AdminConfigInterface;
use Marko\AdminPanel\Controller\LoginController;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeGuard;
use Marko\View\ViewInterface;

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
class LoginStubAdminConfig implements AdminConfigInterface
{
    public function __construct(
        private readonly string $routePrefix = '/admin',
        private readonly string $name = 'Admin',
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
