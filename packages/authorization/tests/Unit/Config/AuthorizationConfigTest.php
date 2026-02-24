<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Unit\Config;

use Marko\Authorization\Config\AuthorizationConfig;
use Marko\Config\ConfigRepositoryInterface;

// Minimal stub for ConfigRepositoryInterface
class StubConfigRepository implements ConfigRepositoryInterface
{
    public function __construct(
        private readonly array $data = [],
    ) {}

    public function get(
        string $key,
        ?string $scope = null,
    ): mixed {
        return $this->resolveKey($key);
    }

    public function has(
        string $key,
        ?string $scope = null,
    ): bool {
        return $this->resolveKey($key) !== null;
    }

    public function getString(
        string $key,
        ?string $scope = null,
    ): string {
        return (string) $this->resolveKey($key);
    }

    public function getInt(
        string $key,
        ?string $scope = null,
    ): int {
        return (int) $this->resolveKey($key);
    }

    public function getBool(
        string $key,
        ?string $scope = null,
    ): bool {
        return (bool) $this->resolveKey($key);
    }

    public function getFloat(
        string $key,
        ?string $scope = null,
    ): float {
        return (float) $this->resolveKey($key);
    }

    public function getArray(
        string $key,
        ?string $scope = null,
    ): array {
        return (array) $this->resolveKey($key);
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

    private function resolveKey(
        string $key,
    ): mixed {
        $parts = explode('.', $key);
        $current = $this->data;

        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }

            $current = $current[$part];
        }

        return $current;
    }
}

it('creates AuthorizationConfig with default guard accessor', function (): void {
    $config = new AuthorizationConfig(
        config: new StubConfigRepository([
            'authorization' => [
                'default_guard' => 'web',
            ],
        ]),
    );

    expect($config->defaultGuard())->toBe('web');
});

it('returns configured default guard', function (): void {
    $config = new AuthorizationConfig(
        config: new StubConfigRepository([
            'authorization' => [
                'default_guard' => 'api',
            ],
        ]),
    );

    expect($config->defaultGuard())->toBe('api');
});
