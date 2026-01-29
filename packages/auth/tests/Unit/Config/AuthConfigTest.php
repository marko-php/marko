<?php

declare(strict_types=1);

use Marko\Auth\Config\AuthConfig;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

function createAuthMockConfigRepository(
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

it('creates AuthConfig class', function () {
    $config = new AuthConfig(createAuthMockConfigRepository());

    expect($config)->toBeInstanceOf(AuthConfig::class);
});

it('loads default guard name', function () {
    $config = new AuthConfig(createAuthMockConfigRepository([
        'auth.default.guard' => 'session',
    ]));

    expect($config->defaultGuard())->toBe('session');
});

it('loads default provider name', function () {
    $config = new AuthConfig(createAuthMockConfigRepository([
        'auth.default.provider' => 'users',
    ]));

    expect($config->defaultProvider())->toBe('users');
});

it('loads guards configuration array', function () {
    $guardsConfig = [
        'session' => ['driver' => 'session', 'provider' => 'users'],
        'token' => ['driver' => 'token', 'provider' => 'users'],
    ];
    $config = new AuthConfig(createAuthMockConfigRepository([
        'auth.guards' => $guardsConfig,
    ]));

    expect($config->guards())->toBe($guardsConfig);
});

it('loads providers configuration array', function () {
    $providersConfig = [
        'users' => ['driver' => 'eloquent', 'model' => 'App\\User'],
        'admins' => ['driver' => 'database', 'table' => 'admins'],
    ];
    $config = new AuthConfig(createAuthMockConfigRepository([
        'auth.providers' => $providersConfig,
    ]));

    expect($config->providers())->toBe($providersConfig);
});

it('loads password hasher settings', function () {
    $passwordConfig = [
        'driver' => 'bcrypt',
        'bcrypt' => ['cost' => 12],
    ];
    $config = new AuthConfig(createAuthMockConfigRepository([
        'auth.password' => $passwordConfig,
    ]));

    expect($config->passwordConfig())->toBe($passwordConfig);
});

it('loads remember token settings', function () {
    $rememberConfig = [
        'expiration' => 43200,
        'cookie' => 'remember_token',
    ];
    $config = new AuthConfig(createAuthMockConfigRepository([
        'auth.remember' => $rememberConfig,
    ]));

    expect($config->rememberConfig())->toBe($rememberConfig);
});

it('provides getter for bcrypt cost', function () {
    $config = new AuthConfig(createAuthMockConfigRepository([
        'auth.password.bcrypt.cost' => 14,
    ]));

    expect($config->bcryptCost())->toBe(14);
});

it('provides default bcrypt cost of 12', function () {
    $config = new AuthConfig(createAuthMockConfigRepository());

    expect($config->bcryptCost())->toBe(12);
});

it('provides default configuration file', function () {
    $configPath = dirname(__DIR__, 3) . '/config/auth.php';

    expect(file_exists($configPath))->toBeTrue()
        ->and(is_array(require $configPath))->toBeTrue();
});
