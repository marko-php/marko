<?php

declare(strict_types=1);

use Marko\Auth\Config\AuthConfig;
use Marko\Config\ConfigRepositoryInterface;

function createAuthMockConfigRepository(
    array $configData = [],
): ConfigRepositoryInterface {
    /** @noinspection PhpMissingParentConstructorInspection */
    return new class ($configData) implements ConfigRepositoryInterface
    {
        public function __construct(
            private readonly array $data,
        ) {}

        public function get(
            string $key,
            mixed $default = null,
            ?string $scope = null,
        ): mixed {
            return $this->data[$key] ?? $default;
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return isset($this->data[$key]);
        }

        public function getString(
            string $key,
            ?string $default = null,
            ?string $scope = null,
        ): string {
            return (string) ($this->data[$key] ?? $default);
        }

        public function getInt(
            string $key,
            ?int $default = null,
            ?string $scope = null,
        ): int {
            return (int) ($this->data[$key] ?? $default);
        }

        public function getBool(
            string $key,
            ?bool $default = null,
            ?string $scope = null,
        ): bool {
            return (bool) ($this->data[$key] ?? $default);
        }

        public function getFloat(
            string $key,
            ?float $default = null,
            ?string $scope = null,
        ): float {
            return (float) ($this->data[$key] ?? $default);
        }

        public function getArray(
            string $key,
            ?array $default = null,
            ?string $scope = null,
        ): array {
            return (array) ($this->data[$key] ?? $default ?? []);
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
    $config = new AuthConfig(createAuthMockConfigRepository([]));

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
    $config = new AuthConfig(createAuthMockConfigRepository([]));

    expect($config->bcryptCost())->toBe(12);
});

it('provides default configuration file', function () {
    $configPath = dirname(__DIR__, 3) . '/config/auth.php';

    expect(file_exists($configPath))->toBeTrue()
        ->and(is_array(require $configPath))->toBeTrue();
});
