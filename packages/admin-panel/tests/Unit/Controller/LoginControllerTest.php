<?php

declare(strict_types=1);

namespace Marko\AdminPanel\Tests\Unit\Controller\Login;

use Marko\Admin\Config\AdminConfigInterface;
use Marko\AdminPanel\Controller\LoginController;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
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

// Stub for GuardInterface
class LoginStubGuard implements GuardInterface
{
    private ?AuthenticatableInterface $authenticatedUser = null;

    private bool $attemptResult = false;

    /** @var array<string, mixed> */
    public array $lastAttemptedCredentials = [];

    public bool $logoutCalled = false;

    public ?UserProviderInterface $provider = null {
        set {
            $this->provider = $value;
        }
    }

    public function setUser(
        ?AuthenticatableInterface $user,
    ): void {
        $this->authenticatedUser = $user;
    }

    public function setAttemptResult(
        bool $result,
    ): void {
        $this->attemptResult = $result;
    }

    public function check(): bool
    {
        return $this->authenticatedUser !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?AuthenticatableInterface
    {
        return $this->authenticatedUser;
    }

    public function id(): int|string|null
    {
        return $this->authenticatedUser?->getAuthIdentifier();
    }

    public function attempt(
        array $credentials,
    ): bool {
        $this->lastAttemptedCredentials = $credentials;

        return $this->attemptResult;
    }

    public function login(
        AuthenticatableInterface $user,
    ): void {
        $this->authenticatedUser = $user;
    }

    public function loginById(
        int|string $id,
    ): ?AuthenticatableInterface {
        return null;
    }

    public function logout(): void
    {
        $this->logoutCalled = true;
        $this->authenticatedUser = null;
    }

    public function getName(): string
    {
        return 'admin';
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

// Stub for AuthenticatableInterface
class LoginStubAdminUser implements AuthenticatableInterface
{
    public function __construct(
        private readonly int $id = 1,
        private readonly string $name = 'Admin User',
        private readonly string $email = 'admin@example.com',
    ) {}

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return 'hashed';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken(
        ?string $token,
    ): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function getName(): string
    {
        return $this->name;
    }
}

it('redirects authenticated users from login page to dashboard', function (): void {
    $view = new LoginStubView();
    $guard = new LoginStubGuard();
    $adminConfig = new LoginStubAdminConfig();

    // User is already authenticated
    $user = new LoginStubAdminUser();
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
    $guard = new LoginStubGuard();
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

    expect($guard->lastAttemptedCredentials)->toBe([
        'email' => 'admin@example.com',
        'password' => 'secret123',
    ]);
});

it('redirects to dashboard after successful login', function (): void {
    $view = new LoginStubView();
    $guard = new LoginStubGuard();
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
    $guard = new LoginStubGuard();
    $adminConfig = new LoginStubAdminConfig();
    $guard->setAttemptResult(false);

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
    $guard = new LoginStubGuard();
    $adminConfig = new LoginStubAdminConfig();

    // User is authenticated
    $user = new LoginStubAdminUser();
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
    $guard = new LoginStubGuard();
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
