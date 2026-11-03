<?php

declare(strict_types=1);

namespace Marko\AdminPanel\Tests\Unit\Controller\Login;

use Marko\Admin\Config\AdminConfigInterface;
use Marko\AdminPanel\Controller\LoginController;
use Marko\AdminPanel\Tests\Fixtures\FixedAdminGuardResolver;
use Marko\Authentication\Exceptions\TooManyLoginAttemptsException;
use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\RateLimiter\Attributes\RateLimit;
use Marko\RateLimiter\Config\RateLimiterConfig;
use Marko\RateLimiter\Contracts\RateLimiterInterface;
use Marko\RateLimiter\Contracts\RateLimitKeyResolverInterface;
use Marko\RateLimiter\Middleware\RateLimitMiddleware;
use Marko\RateLimiter\RateLimitResult;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\WithoutMiddleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDiscovery;
use Marko\Routing\RouteMatcher;
use Marko\Routing\Router;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Exceptions\CsrfTokenMismatchException;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionInterface;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeGuard;
use Marko\Testing\Fake\FakeSession;
use Marko\View\ViewInterface;
use ReflectionAttribute;
use ReflectionClass;
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

function loginCsrfMiddleware(): CsrfMiddleware
{
    $session = new FakeSession();
    $session->start();

    return new CsrfMiddleware(
        tokenManager: new LoginStubCsrfTokenManager('test-csrf-token'),
        session: $session,
        sessionConfig: new SessionConfig(new FakeConfigRepository([
            'session.cookie.name' => 'marko_session',
            'session.cookie.path' => '/',
            'session.cookie.domain' => '',
            'session.cookie.secure' => true,
        ])),
    );
}

class CountingRateLimiter implements RateLimiterInterface
{
    public int $attempts = 0;

    public function attempt(
        string $key,
        int $maxAttempts,
        int $decaySeconds,
    ): RateLimitResult {
        $this->attempts++;

        return new RateLimitResult(allowed: true, remaining: $maxAttempts - $this->attempts);
    }

    public function tooManyAttempts(
        string $key,
        int $maxAttempts,
    ): bool {
        return false;
    }

    public function clear(
        string $key,
    ): void {}
}

it('answers a forged login POST with 419 before it uses up a rate limit attempt', function (): void {
    $routes = new RouteCollection();

    foreach (new RouteDiscovery()->discoverFromClass(LoginController::class) as $route) {
        $routes->add($route);
    }

    $session = new FakeSession();
    $session->start();
    $rateLimiter = new CountingRateLimiter();
    $container = new Container(new PreferenceRegistry());
    $container->instance(CsrfTokenManagerInterface::class, new LoginStubCsrfTokenManager('test-csrf-token'));
    $container->instance(SessionInterface::class, $session);
    $container->instance(SessionConfig::class, new SessionConfig(new FakeConfigRepository([
        'session.cookie.name' => 'marko_session',
        'session.cookie.path' => '/',
        'session.cookie.domain' => '',
        'session.cookie.secure' => true,
    ])));
    $container->instance(RateLimitMiddleware::class, new RateLimitMiddleware(
        rateLimiter: $rateLimiter,
        rateLimitKeyResolver: new class () implements RateLimitKeyResolverInterface
        {
            public function resolve(
                Request $request,
            ): string {
                return '203.0.113.7';
            }
        },
        rateLimiterConfig: new RateLimiterConfig(new FakeConfigRepository()),
    ));
    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
        globalMiddleware: [CsrfMiddleware::class],
    );

    $response = $router->handle(new Request(
        server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/login', 'HTTP_ACCEPT' => 'application/json'],
        post: ['email' => 'kira@example.ch', 'password' => 'guess', '_token' => 'forged'],
    ));

    expect($response->statusCode())->toBe(419)
        ->and($rateLimiter->attempts)->toBe(0);
});

it('relies on the global CsrfMiddleware from marko/security for the logout route', function (): void {
    $securityRoot = dirname((string) new ReflectionClass(CsrfMiddleware::class)->getFileName(), 3);
    $securityModule = require $securityRoot . '/module.php';
    $method = new ReflectionMethod(LoginController::class, 'logout');
    $routeMiddleware = array_merge(...array_map(
        fn (ReflectionAttribute $attribute): array => $attribute->newInstance()->middleware,
        $method->getAttributes(Middleware::class),
    ));

    expect($securityModule['globalMiddleware'])->toContain(CsrfMiddleware::class)
        ->and($routeMiddleware)->not->toContain(CsrfMiddleware::class)
        ->and($method->getAttributes(WithoutMiddleware::class))->toBeEmpty()
        ->and(new ReflectionClass(LoginController::class)->getAttributes(WithoutMiddleware::class))->toBeEmpty();
});

it('rejects a POST to the admin login route when no CSRF token is supplied', function (): void {
    $request = new Request(server: ['REQUEST_METHOD' => 'POST']);

    loginCsrfMiddleware()->handle($request, fn (Request $r) => new Response('OK', 200));
})->throws(CsrfTokenMismatchException::class);

it('rejects a POST to the admin login route when the CSRF token is invalid', function (): void {
    $request = new Request(
        server: ['REQUEST_METHOD' => 'POST'],
        post: ['_token' => 'wrong-token'],
    );

    loginCsrfMiddleware()->handle($request, fn (Request $r) => new Response('OK', 200));
})->throws(CsrfTokenMismatchException::class);

it('allows a POST to the admin login route when a valid CSRF token is supplied', function (): void {
    $request = new Request(
        server: ['REQUEST_METHOD' => 'POST'],
        post: ['_token' => 'test-csrf-token'],
    );

    $response = loginCsrfMiddleware()->handle($request, fn (Request $r) => new Response('OK', 200));

    expect($response->statusCode())->toBe(200);
});

it('exposes a CSRF token value to the login view so the form can submit it', function (): void {
    $view = new LoginStubView();
    $guard = new FakeGuard(name: 'admin');
    $adminConfig = new LoginStubAdminConfig();
    $csrfTokenManager = new LoginStubCsrfTokenManager('test-csrf-token');

    $controller = new LoginController(
        view: $view,
        adminGuard: new FixedAdminGuardResolver($guard),
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
        adminGuard: new FixedAdminGuardResolver($guard),
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
        adminGuard: new FixedAdminGuardResolver($guard),
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
        adminGuard: new FixedAdminGuardResolver($guard),
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
        adminGuard: new FixedAdminGuardResolver($guard),
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
        ->and($view->lastData['csrfToken'])->toBe('test-csrf-token')
        ->and($view->lastData)->toHaveKey('loginUrl')
        ->and($view->lastData['loginUrl'])->toBe('/admin/login');
});

it('rate limits the admin login POST per client IP with RateLimitMiddleware', function (): void {
    $method = new ReflectionMethod(LoginController::class, 'authenticate');
    $middleware = $method->getAttributes(Middleware::class)[0]->newInstance();
    $rateLimit = $method->getAttributes(RateLimit::class)[0]->newInstance();

    expect($middleware->middleware)->toContain(RateLimitMiddleware::class)
        ->and($rateLimit->maxAttempts)->toBe(10)
        ->and($rateLimit->decaySeconds)->toBe(60)
        ->and($rateLimit->name)->toBe('admin-login');
});

it('re-renders the login form as 429 with Retry-After when the login is locked out', function (): void {
    $view = new LoginStubView();
    $guard = new class (name: 'admin') extends FakeGuard
    {
        public function attempt(
            array $credentials,
            bool $remember = false,
        ): bool {
            throw TooManyLoginAttemptsException::lockedOut('admin', 45);
        }
    };

    $controller = new LoginController(
        view: $view,
        adminGuard: new FixedAdminGuardResolver($guard),
        adminConfig: new LoginStubAdminConfig(),
        csrfTokenManager: new LoginStubCsrfTokenManager(),
    );

    $response = $controller->authenticate(new Request(post: [
        'email' => 'admin@example.com',
        'password' => 'guess',
    ]));

    expect($response->statusCode())->toBe(429)
        ->and($response->headers()['Retry-After'])->toBe('45')
        ->and($view->lastTemplate)->toBe('admin-panel::auth/login')
        ->and($view->lastData['error'])->toBe('Too many login attempts. Please try again in 45 seconds.')
        ->and($view->lastData['csrfToken'])->toBe('test-csrf-token')
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
        adminGuard: new FixedAdminGuardResolver($guard),
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
        adminGuard: new FixedAdminGuardResolver($guard),
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
