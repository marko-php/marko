<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

class FakeConfigRepository implements ConfigRepositoryInterface
{
    /**
     * @param array<string, mixed> $config Flat key-value pairs using dot notation
     */
    public function __construct(
        private array $config = [],
        private readonly ?string $defaultScope = null,
    ) {}

    public function get(
        string $key,
        ?string $scope = null,
    ): mixed {
        $effectiveScope = $scope ?? $this->defaultScope;

        if (!$this->has($key, $scope)) {
            throw new ConfigNotFoundException($key);
        }

        if ($effectiveScope !== null && array_key_exists("scopes.$effectiveScope.$key", $this->config)) {
            return $this->config["scopes.$effectiveScope.$key"];
        }

        if ($effectiveScope !== null && array_key_exists("default.$key", $this->config)) {
            return $this->config["default.$key"];
        }

        return $this->config[$key];
    }

    public function has(
        string $key,
        ?string $scope = null,
    ): bool {
        $effectiveScope = $scope ?? $this->defaultScope;

        if ($effectiveScope !== null) {
            return array_key_exists("scopes.$effectiveScope.$key", $this->config)
                || array_key_exists("default.$key", $this->config)
                || array_key_exists($key, $this->config);
        }

        return array_key_exists($key, $this->config);
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
        return $this->config;
    }

    public function withScope(
        string $scope,
    ): ConfigRepositoryInterface {
        return new self($this->config, $scope);
    }

    public function set(
        string $key,
        mixed $value,
    ): void {
        $this->config[$key] = $value;
    }
}
