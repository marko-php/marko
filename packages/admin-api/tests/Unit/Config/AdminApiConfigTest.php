<?php

declare(strict_types=1);

use Marko\AdminApi\ApiResponse;
use Marko\AdminApi\Config\AdminApiConfig;
use Marko\AdminApi\Config\AdminApiConfigInterface;
use Marko\AdminApi\Controller\MeController;
use Marko\AdminApi\Controller\SectionController;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\Auth\AuthenticatableInterface;
use Marko\Auth\Contracts\GuardInterface;
use Marko\Auth\Contracts\UserProviderInterface;
use Marko\Auth\Guard\TokenGuard;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Attributes\Get;

function createAdminApiMockConfigRepository(
    array $configData = [],
): ConfigRepositoryInterface {
    return new readonly class ($configData) implements ConfigRepositoryInterface
    {
        public function __construct(
            private array $data,
        ) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            if (!$this->has($key, $scope)) {
                throw new ConfigNotFoundException($key);
            }

            return $this->data[$key];
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return isset($this->data[$key]);
        }

        public function getString(
            string $key,
            ?string $scope = null,
        ): string {
            return (string) $this->get($key, $scope);
        }

        public function getInt(
            string $key,
            ?string $scope = null,
        ): int {
            return (int) $this->get($key, $scope);
        }

        public function getBool(
            string $key,
            ?string $scope = null,
        ): bool {
            return (bool) $this->get($key, $scope);
        }

        public function getFloat(
            string $key,
            ?string $scope = null,
        ): float {
            return (float) $this->get($key, $scope);
        }

        public function getArray(
            string $key,
            ?string $scope = null,
        ): array {
            return (array) $this->get($key, $scope);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return $this->data;
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };
}

it('creates AdminApiConfig with version and rate limit settings', function (): void {
    $config = new AdminApiConfig(createAdminApiMockConfigRepository([
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

    expect($tokenGuard)->toBeInstanceOf(GuardInterface::class)
        ->and($tokenGuard->getName())->toBe('admin-api')
        ->and($tokenGuard->check())->toBeFalse()
        ->and($tokenGuard->guest())->toBeTrue()
        ->and($tokenGuard->user())->toBeNull();

    // Without headers set, no user is authenticated

    // Config reflects the guard name
    $config = new AdminApiConfig(createAdminApiMockConfigRepository([
        'admin-api.version' => 'v1',
        'admin-api.rate_limit' => 60,
        'admin-api.guard' => 'admin-api',
    ]));

    expect($config->getGuardName())->toBe('admin-api');
});

it('registers API routes under /admin/api/v1 prefix', function (): void {
    // Verify MeController routes
    $meMethod = new ReflectionMethod(MeController::class, 'me');
    $meRouteAttributes = $meMethod->getAttributes(Get::class);

    expect($meRouteAttributes)->toHaveCount(1);

    $meRoute = $meRouteAttributes[0]->newInstance();

    expect($meRoute->path)->toStartWith('/admin/api/v1/')
        ->and($meRoute->path)->toBe('/admin/api/v1/me');

    // Verify SectionController routes
    $indexMethod = new ReflectionMethod(SectionController::class, 'index');
    $indexRouteAttributes = $indexMethod->getAttributes(Get::class);

    expect($indexRouteAttributes)->toHaveCount(1);

    $indexRoute = $indexRouteAttributes[0]->newInstance();

    expect($indexRoute->path)->toStartWith('/admin/api/v1/')
        ->and($indexRoute->path)->toBe('/admin/api/v1/sections');

    $showMethod = new ReflectionMethod(SectionController::class, 'show');
    $showRouteAttributes = $showMethod->getAttributes(Get::class);

    expect($showRouteAttributes)->toHaveCount(1);

    $showRoute = $showRouteAttributes[0]->newInstance();

    expect($showRoute->path)->toStartWith('/admin/api/v1/')
        ->and($showRoute->path)->toBe('/admin/api/v1/sections/{id}');
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

    expect($tokenGuard->check())->toBeFalse();

    // Verify ApiResponse generates correct JSON 401
    $response = ApiResponse::unauthorized();

    expect($response->statusCode())->toBe(401)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);

    expect($body)->toHaveKey('errors')
        ->and($body['errors'][0]['message'])->toBe('Unauthorized');
});

it('returns JSON 401 for invalid bearer token', function (): void {
    $provider = new class () implements UserProviderInterface
    {
        public function retrieveById(
            int|string $identifier,
        ): ?AuthenticatableInterface {
            return null;
        }

        public function retrieveByCredentials(
            array $credentials,
        ): ?AuthenticatableInterface {
            // Invalid token returns null
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

    $tokenGuard->setHeaders(['Authorization' => 'Bearer invalid-token-xyz']);

    expect($tokenGuard->check())->toBeFalse()
        ->and($tokenGuard->user())->toBeNull();

    // Verify ApiResponse generates correct JSON 401 for invalid tokens
    $response = ApiResponse::unauthorized();

    expect($response->statusCode())->toBe(401)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);

    expect($body)->toHaveKey('errors')
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

    expect(file_exists($configPath))->toBeTrue();

    $configData = require $configPath;

    expect($configData)->toBeArray()
        ->and($configData)->toHaveKey('version')
        ->and($configData)->toHaveKey('rate_limit')
        ->and($configData)->toHaveKey('guard')
        ->and($configData['version'])->toBe('v1')
        ->and($configData['rate_limit'])->toBe(60)
        ->and($configData['guard'])->toBe('admin-api');
});

it('has module.php with AdminApiConfig binding', function (): void {
    $modulePath = dirname(__DIR__, 3) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(AdminApiConfigInterface::class)
        ->and($module['bindings'][AdminApiConfigInterface::class])
            ->toBe(AdminApiConfig::class);
});
