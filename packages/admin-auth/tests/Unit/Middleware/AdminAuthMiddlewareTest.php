<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Unit\Middleware;

use Marko\Admin\Config\AdminConfigInterface;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Contracts\PermissionRegistryInterface;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\AdminAuth\PermissionRegistry;
use Marko\Auth\AuthenticatableInterface;
use Marko\Auth\Contracts\GuardInterface;
use Marko\Auth\Contracts\UserProviderInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

// Test controller classes for attribute reflection
class TestControllerWithPermission
{
    #[RequiresPermission('posts.create')]
    public function create(): Response
    {
        return new Response(body: 'created', statusCode: 200);
    }
}

class TestControllerWithoutPermission
{
    public function index(): Response
    {
        return new Response(body: 'index', statusCode: 200);
    }
}

class TestControllerWithWildcardPermission
{
    #[RequiresPermission('posts.delete')]
    public function delete(): Response
    {
        return new Response(body: 'deleted', statusCode: 200);
    }
}

// Simple stub for GuardInterface
class StubGuard implements GuardInterface
{
    private ?AuthenticatableInterface $authenticatedUser = null;

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
        return false;
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
        $this->authenticatedUser = null;
    }

    public function getName(): string
    {
        return 'admin';
    }
}

// Simple stub for AdminConfigInterface
class StubAdminConfig implements AdminConfigInterface
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

// Helper to create a standard middleware instance
function createMiddleware(
    ?GuardInterface $guard = null,
    ?AdminConfigInterface $adminConfig = null,
    ?string $controller = null,
    ?string $action = null,
    ?PermissionRegistryInterface $permissionRegistry = null,
): AdminAuthMiddleware {
    return new AdminAuthMiddleware(
        guard: $guard ?? new StubGuard(),
        adminConfig: $adminConfig ?? new StubAdminConfig(),
        permissionRegistry: $permissionRegistry ?? new PermissionRegistry(),
        controller: $controller,
        action: $action,
    );
}

function createAdminUser(
    ?array $roles = null,
    array $permissionKeys = [],
): AdminUser {
    $user = new AdminUser();
    $user->id = 1;
    $user->email = 'admin@example.com';
    $user->password = 'hashed';
    $user->name = 'Admin User';

    if ($roles !== null) {
        $user->setRoles(roles: $roles, permissionKeys: $permissionKeys);
    }

    return $user;
}

function createSuccessNext(): callable
{
    return fn (Request $r): Response => new Response(body: 'success', statusCode: 200);
}

it('returns 401 when user is not authenticated', function (): void {
    $guard = new StubGuard(); // No user set
    $middleware = createMiddleware(
        guard: $guard,
        controller: TestControllerWithPermission::class,
        action: 'create',
    );

    $request = new Request(server: [
        'HTTP_ACCEPT' => 'application/json',
    ]);

    $response = $middleware->handle($request, createSuccessNext());

    expect($response->statusCode())->toBe(401);
});

it('passes through when user is authenticated and no RequiresPermission attribute present', function (): void {
    $guard = new StubGuard();
    $user = createAdminUser();
    $guard->setUser($user);

    $middleware = createMiddleware(
        guard: $guard,
        controller: TestControllerWithoutPermission::class,
        action: 'index',
    );

    $request = new Request();

    $response = $middleware->handle($request, createSuccessNext());

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('success');
});

it('passes through when user has the required permission', function (): void {
    $guard = new StubGuard();
    $editorRole = new Role();
    $editorRole->id = 1;
    $editorRole->name = 'Editor';
    $editorRole->slug = 'editor';

    $user = createAdminUser(
        roles: [$editorRole],
        permissionKeys: ['posts.create', 'posts.edit'],
    );
    $guard->setUser($user);

    $middleware = createMiddleware(
        guard: $guard,
        controller: TestControllerWithPermission::class,
        action: 'create',
    );

    $request = new Request();

    $response = $middleware->handle($request, createSuccessNext());

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('success');
});

it('returns 403 when user lacks the required permission', function (): void {
    $guard = new StubGuard();
    $editorRole = new Role();
    $editorRole->id = 1;
    $editorRole->name = 'Editor';
    $editorRole->slug = 'editor';

    // User has posts.edit but NOT posts.create
    $user = createAdminUser(
        roles: [$editorRole],
        permissionKeys: ['posts.edit'],
    );
    $guard->setUser($user);

    $middleware = createMiddleware(
        guard: $guard,
        controller: TestControllerWithPermission::class,
        action: 'create',
    );

    $request = new Request();

    $response = $middleware->handle($request, createSuccessNext());

    expect($response->statusCode())->toBe(403)
        ->and($response->body())->toBe('Forbidden');
});

it('passes through for super admin users regardless of permission', function (): void {
    $guard = new StubGuard();
    $superAdminRole = new Role();
    $superAdminRole->id = 1;
    $superAdminRole->name = 'Super Admin';
    $superAdminRole->slug = 'super-admin';
    $superAdminRole->isSuperAdmin = '1';

    // Super admin has NO explicit permission keys, but should still pass
    $user = createAdminUser(
        roles: [$superAdminRole],
    );
    $guard->setUser($user);

    $middleware = createMiddleware(
        guard: $guard,
        controller: TestControllerWithPermission::class,
        action: 'create',
    );

    $request = new Request();

    $response = $middleware->handle($request, createSuccessNext());

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('success');
});

it('redirects to admin login for unauthenticated web requests', function (): void {
    $guard = new StubGuard(); // No user
    $adminConfig = new StubAdminConfig(routePrefix: '/admin');

    $middleware = createMiddleware(
        guard: $guard,
        adminConfig: $adminConfig,
        controller: TestControllerWithPermission::class,
        action: 'create',
    );

    // Web request: no Accept: application/json header
    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/admin/posts/create',
    ]);

    $response = $middleware->handle($request, createSuccessNext());

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($response->headers()['Location'])->toBe('/admin/login');
});

it('returns JSON 401 for unauthenticated API requests', function (): void {
    $guard = new StubGuard(); // No user

    $middleware = createMiddleware(
        guard: $guard,
        controller: TestControllerWithPermission::class,
        action: 'create',
    );

    // API request: has "Accept: application/json" header
    $request = new Request(server: [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/admin/api/posts',
        'HTTP_ACCEPT' => 'application/json',
    ]);

    $response = $middleware->handle($request, createSuccessNext());

    expect($response->statusCode())->toBe(401)
        ->and($response->headers())->toHaveKey('Content-Type')
        ->and($response->headers()['Content-Type'])->toBe('application/json')
        ->and(json_decode($response->body(), true))->toBe(['error' => 'Unauthorized']);
});

it('returns JSON 403 for unauthorized API requests', function (): void {
    $guard = new StubGuard();
    $viewerRole = new Role();
    $viewerRole->id = 1;
    $viewerRole->name = 'Viewer';
    $viewerRole->slug = 'viewer';

    // User has posts.view but NOT posts.create
    $user = createAdminUser(
        roles: [$viewerRole],
        permissionKeys: ['posts.view'],
    );
    $guard->setUser($user);

    $middleware = createMiddleware(
        guard: $guard,
        controller: TestControllerWithPermission::class,
        action: 'create',
    );

    // API request
    $request = new Request(server: [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/admin/api/posts',
        'HTTP_ACCEPT' => 'application/json',
    ]);

    $response = $middleware->handle($request, createSuccessNext());

    expect($response->statusCode())->toBe(403)
        ->and($response->headers())->toHaveKey('Content-Type')
        ->and($response->headers()['Content-Type'])->toBe('application/json')
        ->and(json_decode($response->body(), true))->toBe(['error' => 'Forbidden']);
});

it('supports wildcard permission matching via user roles', function (): void {
    $guard = new StubGuard();
    $managerRole = new Role();
    $managerRole->id = 1;
    $managerRole->name = 'Posts Manager';
    $managerRole->slug = 'posts-manager';

    // User has wildcard 'posts.*' permission, controller requires 'posts.delete'
    $user = createAdminUser(
        roles: [$managerRole],
        permissionKeys: ['posts.*'],
    );
    $guard->setUser($user);

    $middleware = createMiddleware(
        guard: $guard,
        controller: TestControllerWithWildcardPermission::class,
        action: 'delete',
    );

    $request = new Request();

    $response = $middleware->handle($request, createSuccessNext());

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('success');
});
