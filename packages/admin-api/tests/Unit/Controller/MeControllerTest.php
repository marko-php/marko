<?php

declare(strict_types=1);

namespace Marko\AdminApi\Tests\Unit\Controller;

use Marko\AdminApi\Controller\MeController;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;
use ReflectionClass;
use ReflectionMethod;

class MeStubGuard implements GuardInterface
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

function createMeTestAdminUser(
    array $roles = [],
    array $permissionKeys = [],
): AdminUser {
    $user = new AdminUser();
    $user->id = 1;
    $user->email = 'admin@example.com';
    $user->password = 'hashed';
    $user->name = 'Admin User';
    $user->setRoles(roles: $roles, permissionKeys: $permissionKeys);

    return $user;
}

it('returns current user info with roles and permissions on GET /admin/api/v1/me', function (): void {
    $guard = new MeStubGuard();

    $editorRole = new Role();
    $editorRole->id = 1;
    $editorRole->name = 'Editor';
    $editorRole->slug = 'editor';

    $user = createMeTestAdminUser(
        roles: [$editorRole],
        permissionKeys: ['posts.create', 'posts.edit', 'posts.view'],
    );
    $guard->setUser($user);

    $controller = new MeController(
        guard: $guard,
    );

    $response = $controller->me();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);

    expect($body)->toHaveKey('data')
        ->and($body['data']['id'])->toBe(1)
        ->and($body['data']['email'])->toBe('admin@example.com')
        ->and($body['data']['name'])->toBe('Admin User')
        ->and($body['data']['roles'])->toHaveCount(1)
        ->and($body['data']['roles'][0]['id'])->toBe(1)
        ->and($body['data']['roles'][0]['name'])->toBe('Editor')
        ->and($body['data']['roles'][0]['slug'])->toBe('editor')
        ->and($body['data']['permissions'])->toBe(['posts.create', 'posts.edit', 'posts.view']);
});

it('returns 401 when not authenticated', function (): void {
    $guard = new MeStubGuard(); // No user set

    $controller = new MeController(
        guard: $guard,
    );

    $response = $controller->me();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(401)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);

    expect($body)->toHaveKey('errors')
        ->and($body['errors'][0]['message'])->toBe('Unauthorized');
});

it('uses ApiResponse format for all responses', function (): void {
    $guard = new MeStubGuard();

    $editorRole = new Role();
    $editorRole->id = 1;
    $editorRole->name = 'Editor';
    $editorRole->slug = 'editor';

    $guard->setUser(createMeTestAdminUser(
        roles: [$editorRole],
        permissionKeys: ['posts.view'],
    ));

    $controller = new MeController(
        guard: $guard,
    );

    // Authenticated response has data and meta keys
    $response = $controller->me();
    $body = json_decode($response->body(), true);

    expect($body)->toHaveKey('data')
        ->and($body)->toHaveKey('meta');

    // Unauthenticated response has errors key
    $unauthGuard = new MeStubGuard();
    $unauthController = new MeController(
        guard: $unauthGuard,
    );

    $unauthResponse = $unauthController->me();
    $unauthBody = json_decode($unauthResponse->body(), true);

    expect($unauthBody)->toHaveKey('errors');
});

it('applies AdminAuthMiddleware to all routes', function (): void {
    $reflection = new ReflectionClass(MeController::class);

    // Check class-level Middleware attribute
    $middlewareAttributes = $reflection->getAttributes(Middleware::class);

    expect($middlewareAttributes)->toHaveCount(1);

    $middleware = $middlewareAttributes[0]->newInstance();

    expect($middleware->middleware)->toContain(AdminAuthMiddleware::class);

    // Verify route attribute exists on the "me" method
    $meMethod = new ReflectionMethod(MeController::class, 'me');
    $meRouteAttributes = $meMethod->getAttributes(Get::class);

    expect($meRouteAttributes)->toHaveCount(1);

    $meRoute = $meRouteAttributes[0]->newInstance();

    expect($meRoute->path)->toBe('/admin/api/v1/me');
});
