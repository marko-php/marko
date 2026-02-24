<?php

declare(strict_types=1);

use Marko\AdminApi\ApiResponse;
use Marko\AdminApi\Config\AdminApiConfig;
use Marko\AdminApi\Config\AdminApiConfigInterface;
use Marko\AdminApi\Controller\MeController;
use Marko\AdminApi\Controller\SectionController;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Guard\TokenGuard;
use Marko\Routing\Attributes\Get;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeUserProvider;

it('creates AdminApiConfig with version and rate limit settings', function (): void {
    $config = new AdminApiConfig(new FakeConfigRepository([
        'admin-api.version' => 'v1',
        'admin-api.rate_limit' => 60,
        'admin-api.guard' => 'admin-api',
    ]));

    expect($config)->toBeInstanceOf(AdminApiConfigInterface::class)
        ->and($config->getVersion())->toBe('v1')
        ->and($config->getRateLimit())->toBe(60)
        ->and($config->getGuardName())->toBe('admin-api');
});

it('configures admin-api token guard for API authentication', function (): void {
    $tokenGuard = new TokenGuard(
        name: 'admin-api',
    );

    // Config reflects the guard name
    $config = new AdminApiConfig(new FakeConfigRepository([
        'admin-api.version' => 'v1',
        'admin-api.rate_limit' => 60,
        'admin-api.guard' => 'admin-api',
    ]));

    // Without headers set, no user is authenticated
    expect($tokenGuard)->toBeInstanceOf(GuardInterface::class)
        ->and($tokenGuard->getName())->toBe('admin-api')
        ->and($tokenGuard->check())->toBeFalse()
        ->and($tokenGuard->guest())->toBeTrue()
        ->and($tokenGuard->user())->toBeNull()
        ->and($config->getGuardName())->toBe('admin-api');
});

it('registers API routes under /admin/api/v1 prefix', function (): void {
    // Verify MeController routes
    $meRouteAttributes = (new ReflectionMethod(MeController::class, 'me'))->getAttributes(Get::class);

    // Verify SectionController routes
    $indexRouteAttributes = (new ReflectionMethod(SectionController::class, 'index'))->getAttributes(Get::class);
    $showRouteAttributes = (new ReflectionMethod(SectionController::class, 'show'))->getAttributes(Get::class);

    expect($meRouteAttributes)->toHaveCount(1)
        ->and($meRouteAttributes[0]->newInstance()->path)->toStartWith('/admin/api/v1/')
        ->and($meRouteAttributes[0]->newInstance()->path)->toBe('/admin/api/v1/me')
        ->and($indexRouteAttributes)->toHaveCount(1)
        ->and($indexRouteAttributes[0]->newInstance()->path)->toStartWith('/admin/api/v1/')
        ->and($indexRouteAttributes[0]->newInstance()->path)->toBe('/admin/api/v1/sections')
        ->and($showRouteAttributes)->toHaveCount(1)
        ->and($showRouteAttributes[0]->newInstance()->path)->toStartWith('/admin/api/v1/')
        ->and($showRouteAttributes[0]->newInstance()->path)->toBe('/admin/api/v1/sections/{id}');
});

it('does not conflict with admin-panel routes', function (): void {
    // Admin API routes use /admin/api/v1/ prefix
    $apiRoutes = [];

    $meMethod = new ReflectionMethod(MeController::class, 'me');
    $meRouteAttrs = $meMethod->getAttributes(Get::class);
    $apiRoutes[] = $meRouteAttrs[0]->newInstance()->path;

    $indexMethod = new ReflectionMethod(SectionController::class, 'index');
    $indexRouteAttrs = $indexMethod->getAttributes(Get::class);
    $apiRoutes[] = $indexRouteAttrs[0]->newInstance()->path;

    $showMethod = new ReflectionMethod(SectionController::class, 'show');
    $showRouteAttrs = $showMethod->getAttributes(Get::class);
    $apiRoutes[] = $showRouteAttrs[0]->newInstance()->path;

    // All API routes must contain /api/v1/ which distinguishes them from panel routes
    foreach ($apiRoutes as $route) {
        expect($route)->toContain('/api/v1/');
    }

    // Admin panel routes are at /admin, /admin/login, /admin/logout
    // These should never match API route patterns
    $panelRoutes = ['/admin', '/admin/login', '/admin/logout'];

    foreach ($apiRoutes as $apiRoute) {
        foreach ($panelRoutes as $panelRoute) {
            expect($apiRoute)->not->toBe($panelRoute);
        }
    }
});

it('returns JSON 401 for missing bearer token', function (): void {
    $tokenGuard = new TokenGuard(
        name: 'admin-api',
    );

    // No headers set means no token
    expect($tokenGuard->check())->toBeFalse()
        ->and($tokenGuard->user())->toBeNull();

    // The middleware handles the 401 response, but the guard correctly reports no auth
    $tokenGuard->setHeaders([]);

    // Verify ApiResponse generates correct JSON 401
    $response = ApiResponse::unauthorized();
    $body = json_decode($response->body(), true);

    expect($tokenGuard->check())->toBeFalse()
        ->and($response->statusCode())->toBe(401)
        ->and($response->headers()['Content-Type'])->toBe('application/json')
        ->and($body)->toHaveKey('errors')
        ->and($body['errors'][0]['message'])->toBe('Unauthorized');
});

it('returns JSON 401 for invalid bearer token', function (): void {
    $provider = new FakeUserProvider();

    $tokenGuard = new TokenGuard(
        name: 'admin-api',
        provider: $provider,
    );

    $tokenGuard->setHeaders(['Authorization' => 'Bearer invalid-token-xyz']);

    expect($tokenGuard->check())->toBeFalse()
        ->and($tokenGuard->user())->toBeNull();

    // Verify ApiResponse generates correct JSON 401 for invalid tokens
    $response = ApiResponse::unauthorized();
    $body = json_decode($response->body(), true);

    expect($response->statusCode())->toBe(401)
        ->and($response->headers()['Content-Type'])->toBe('application/json')
        ->and($body)->toHaveKey('errors')
        ->and($body['errors'][0]['message'])->toBe('Unauthorized');
});

it('authenticates with valid bearer token and returns user', function (): void {
    $adminUser = new AdminUser();
    $adminUser->id = 42;
    $adminUser->email = 'api@example.com';
    $adminUser->password = 'hashed';
    $adminUser->name = 'API User';

    $provider = new class ($adminUser) implements UserProviderInterface
    {
        public function __construct(
            private readonly AuthenticatableInterface $user,
        ) {}

        public function retrieveById(
            int|string $identifier,
        ): ?AuthenticatableInterface {
            return null;
        }

        public function retrieveByCredentials(
            array $credentials,
        ): ?AuthenticatableInterface {
            if (isset($credentials['api_token']) && $credentials['api_token'] === 'valid-token-123') {
                return $this->user;
            }

            return null;
        }

        public function validateCredentials(
            AuthenticatableInterface $user,
            array $credentials,
        ): bool {
            return false;
        }

        public function retrieveByRememberToken(
            int|string $identifier,
            string $token,
        ): ?AuthenticatableInterface {
            return null;
        }

        public function updateRememberToken(
            AuthenticatableInterface $user,
            ?string $token,
        ): void {}
    };

    $tokenGuard = new TokenGuard(
        name: 'admin-api',
        provider: $provider,
    );

    $tokenGuard->setHeaders(['Authorization' => 'Bearer valid-token-123']);

    expect($tokenGuard->check())->toBeTrue()
        ->and($tokenGuard->user())->toBe($adminUser)
        ->and($tokenGuard->user()->getAuthIdentifier())->toBe(42);
});

it('has valid config/admin-api.php with default values', function (): void {
    $configPath = dirname(__DIR__, 3) . '/config/admin-api.php';
    $configData = require $configPath;

    expect(file_exists($configPath))->toBeTrue()
        ->and($configData)->toBeArray()
        ->and($configData)->toHaveKey('version')
        ->and($configData)->toHaveKey('rate_limit')
        ->and($configData)->toHaveKey('guard')
        ->and($configData['version'])->toBe('v1')
        ->and($configData['rate_limit'])->toBe(60)
        ->and($configData['guard'])->toBe('admin-api');
});

it('has module.php with AdminApiConfig binding', function (): void {
    $modulePath = dirname(__DIR__, 3) . '/module.php';
    $module = require $modulePath;

    expect(file_exists($modulePath))->toBeTrue()
        ->and($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(AdminApiConfigInterface::class)
        ->and($module['bindings'][AdminApiConfigInterface::class])
            ->toBe(AdminApiConfig::class);
});
