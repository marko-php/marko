<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Unit\Admin\Api;

use Marko\Admin\Config\AdminConfigInterface;
use Marko\AdminAuth\Contracts\PermissionRegistryInterface;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Auth\AuthenticatableInterface;
use Marko\Auth\Contracts\GuardInterface;
use Marko\Auth\Contracts\UserProviderInterface;
use Marko\Blog\Admin\Api\PostApiController;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

it('returns 401 when bearer token is missing', function (): void {
    // Create a guard that is not authenticated
    $guard = new BlogApiStubGuard();

    $adminConfig = new BlogApiStubAdminConfig();
    $permissionRegistry = new BlogApiStubPermissionRegistry();

    $middleware = new AdminAuthMiddleware(
        guard: $guard,
        adminConfig: $adminConfig,
        permissionRegistry: $permissionRegistry,
        controller: PostApiController::class,
        action: 'index',
    );

    // JSON request without authentication
    $request = new Request(server: [
        'HTTP_ACCEPT' => 'application/json',
    ]);

    $response = $middleware->handle($request, function (Request $request): Response {
        return new Response('should not reach here', 200);
    });

    expect($response->statusCode())->toBe(401)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);
    expect($body['error'])->toBe('Unauthorized');
});

it('returns 403 when user lacks required permission', function (): void {
    // Create a guard that is authenticated but without needed permissions
    $guard = new BlogApiStubGuard();
    $user = new AdminUser();
    $user->id = 1;
    $user->email = 'editor@example.com';
    $user->password = 'hashed';
    $user->name = 'Editor';

    $editorRole = new Role();
    $editorRole->id = 2;
    $editorRole->name = 'Editor';
    $editorRole->slug = 'editor';

    // User has no permissions at all
    $user->setRoles(roles: [$editorRole], permissionKeys: []);
    $guard->login($user);

    $adminConfig = new BlogApiStubAdminConfig();
    $permissionRegistry = new BlogApiStubPermissionRegistry();

    $middleware = new AdminAuthMiddleware(
        guard: $guard,
        adminConfig: $adminConfig,
        permissionRegistry: $permissionRegistry,
        controller: PostApiController::class,
        action: 'index',
    );

    // JSON request with authentication but no permissions
    $request = new Request(server: [
        'HTTP_ACCEPT' => 'application/json',
    ]);

    $response = $middleware->handle($request, function (Request $request): Response {
        return new Response('should not reach here', 200);
    });

    expect($response->statusCode())->toBe(403)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);
    expect($body['error'])->toBe('Forbidden');
});

// Stub classes for auth testing

class BlogApiStubGuard implements GuardInterface
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
        return 'admin-api';
    }
}

class BlogApiStubAdminConfig implements AdminConfigInterface
{
    public function getRoutePrefix(): string
    {
        return '/admin';
    }

    public function getName(): string
    {
        return 'Admin';
    }
}

class BlogApiStubPermissionRegistry implements PermissionRegistryInterface
{
    public function register(
        string $key,
        string $label,
        string $group,
    ): void {}

    public function all(): array
    {
        return [];
    }

    public function getByGroup(
        string $group,
    ): array {
        return [];
    }

    public function matches(
        string $pattern,
        string $permissionKey,
    ): bool {
        return false;
    }
}
