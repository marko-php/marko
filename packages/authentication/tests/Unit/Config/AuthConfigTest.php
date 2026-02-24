<?php

declare(strict_types=1);

use Marko\Authentication\Config\AuthConfig;
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
        'authentication.default.guard' => 'session',
    ]));

    expect($config->defaultGuard())->toBe('session');
});

it('loads default provider name', function () {
    $config = new AuthConfig(createAuthMockConfigRepository([
        'authentication.default.provider' => 'users',
    ]));

    expect($config->defaultProvider())->toBe('users');
});

it('loads guards configuration array', function () {
    $guardsConfig = [
        'session' => ['driver' => 'session', 'provider' => 'users'],
        'token' => ['driver' => 'token', 'provider' => 'users'],
    ];
    $config = new AuthConfig(createAuthMockConfigRepository([
        'authentication.guards' => $guardsConfig,
    ]));

    expect($config->guards())->toBe($guardsConfig);
});

it('loads providers configuration array', function () {
    $providersConfig = [
        'users' => ['driver' => 'eloquent', 'model' => 'App\\User'],
        'admins' => ['driver' => 'database', 'table' => 'admins'],
    ];
    $config = new AuthConfig(createAuthMockConfigRepository([
        'authentication.providers' => $providersConfig,
    ]));

    expect($config->providers())->toBe($providersConfig);
});

it('loads password hasher settings', function () {
    $passwordConfig = [
        'driver' => 'bcrypt',
        'bcrypt' => ['cost' => 12],
    ];
    $config = new AuthConfig(createAuthMockConfigRepository([
        'authentication.password' => $passwordConfig,
    ]));

    expect($config->passwordConfig())->toBe($passwordConfig);
});

it('loads remember token settings', function () {
    $rememberConfig = [
        'expiration' => 43200,
        'cookie' => 'remember_token',
    ];
    $config = new AuthConfig(createAuthMockConfigRepository([
        'authentication.remember' => $rememberConfig,
    ]));

    expect($config->rememberConfig())->toBe($rememberConfig);
});

it('provides getter for bcrypt cost', function () {
    $config = new AuthConfig(createAuthMockConfigRepository([
        'authentication.password.bcrypt.cost' => 14,
    ]));

    expect($config->bcryptCost())->toBe(14);
});

it('reads default guard from config without fallback', function () {
    $config = new AuthConfig(createAuthMockConfigRepository([
        'authentication.default.guard' => 'token',
    ]));

    expect($config->defaultGuard())->toBe('token');
});

it('reads default provider from config without fallback', function () {
    $config = new AuthConfig(createAuthMockConfigRepository([
        'authentication.default.provider' => 'admins',
    ]));

    expect($config->defaultProvider())->toBe('admins');
});

it('reads bcrypt cost from config without fallback', function () {
    $config = new AuthConfig(createAuthMockConfigRepository([
        'authentication.password.bcrypt.cost' => 10,
    ]));

    expect($config->bcryptCost())->toBe(10);
});

it('config file contains all required keys with defaults', function () {
    $configPath = dirname(__DIR__, 3) . '/config/authentication.php';
    $config = require $configPath;

    expect(file_exists($configPath))->toBeTrue()
        ->and($config)->toBeArray()
        ->and($config)->toHaveKey('default')
        ->and($config['default'])->toHaveKey('guard')
        ->and($config['default'])->toHaveKey('provider')
        ->and($config)->toHaveKey('password')
        ->and($config['password'])->toHaveKey('bcrypt')
        ->and($config['password']['bcrypt'])->toHaveKey('cost');
});
