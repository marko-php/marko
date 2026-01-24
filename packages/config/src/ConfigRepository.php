<?php

declare(strict_types=1);

namespace Marko\Config;

use Marko\Config\Exceptions\ConfigException;
use Marko\Config\Exceptions\ConfigNotFoundException;

readonly class ConfigRepository implements ConfigRepositoryInterface
{
    public function __construct(
        private array $config,
        private ?string $defaultScope = null,
    ) {}

    public function get(
        string $key,
        mixed $default = null,
        ?string $scope = null,
    ): mixed {
        $effectiveScope = $scope ?? $this->defaultScope;

        if ($effectiveScope !== null) {
            [$found, $value] = $this->resolveScopedKey($key, $effectiveScope);
            if ($found) {
                return $value;
            }
        }

        [$found, $value] = $this->resolveKey($key);

        return $found ? $value : $default;
    }

    public function has(
        string $key,
        ?string $scope = null,
    ): bool {
        $effectiveScope = $scope ?? $this->defaultScope;

        if ($effectiveScope !== null) {
            [$found] = $this->resolveScopedKey($key, $effectiveScope);
            if ($found) {
                return true;
            }
        }

        [$found] = $this->resolveKey($key);

        return $found;
    }

    /**
     * @return array{0: bool, 1: mixed} [found, value]
     */
    private function resolveKey(
        string $key,
    ): array {
        $segments = explode('.', $key);
        $value = $this->config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return [false, null];
            }
            $value = $value[$segment];
        }

        return [true, $value];
    }

    /**
     * Resolve a key with scope support.
     *
     * For key 'store.currency' with scope 'us':
     * 1. First check: $config['store']['scopes']['us']['currency']
     * 2. Then fall back to: $config['store']['default']['currency']
     *
     * @return array{0: bool, 1: mixed} [found, value]
     */
    private function resolveScopedKey(
        string $key,
        string $scope,
    ): array {
        $segments = explode('.', $key);
        if (count($segments) < 2) {
            return [false, null];
        }

        $topKey = $segments[0];
        $restOfKey = implode('.', array_slice($segments, 1));

        // Try scope-specific value: $config[$topKey]['scopes'][$scope][$restOfKey]
        $scopedKey = "$topKey.scopes.$scope.$restOfKey";
        [$found, $value] = $this->resolveKey($scopedKey);
        if ($found) {
            return [true, $value];
        }

        // Try default value: $config[$topKey]['default'][$restOfKey]
        $defaultKey = "$topKey.default.$restOfKey";

        return $this->resolveKey($defaultKey);
    }

    /**
     * @throws ConfigNotFoundException|ConfigException
     */
    public function getString(
        string $key,
        ?string $default = null,
        ?string $scope = null,
    ): string {
        $this->ensureKeyExists($key, $default, $scope);
        $value = $this->get($key, $default, $scope);

        if ($value !== null && !is_scalar($value)) {
            throw new ConfigException(
                sprintf('Configuration key "%s" is not a string', $key),
                sprintf('Expected string, got %s', get_debug_type($value)),
                'Ensure your config file returns a string for this key.',
            );
        }

        return (string) $value;
    }

    /**
     * @throws ConfigException|ConfigNotFoundException
     */
    public function getInt(
        string $key,
        ?int $default = null,
        ?string $scope = null,
    ): int {
        $this->ensureKeyExists($key, $default, $scope);
        $value = $this->get($key, $default, $scope);

        if ($value !== null && !is_numeric($value)) {
            throw new ConfigException(
                sprintf('Configuration key "%s" is not an integer', $key),
                sprintf('Expected integer, got %s', get_debug_type($value)),
                'Ensure your config file returns an integer for this key.',
            );
        }

        return (int) $value;
    }

    /**
     * @throws ConfigException|ConfigNotFoundException
     */
    public function getBool(
        string $key,
        ?bool $default = null,
        ?string $scope = null,
    ): bool {
        $this->ensureKeyExists($key, $default, $scope);
        $value = $this->get($key, $default, $scope);

        if ($value !== null && !is_scalar($value)) {
            throw new ConfigException(
                sprintf('Configuration key "%s" is not a boolean', $key),
                sprintf('Expected boolean, got %s', get_debug_type($value)),
                'Ensure your config file returns a boolean for this key.',
            );
        }

        return (bool) $value;
    }

    /**
     * @throws ConfigNotFoundException|ConfigException
     */
    public function getFloat(
        string $key,
        ?float $default = null,
        ?string $scope = null,
    ): float {
        $this->ensureKeyExists($key, $default, $scope);
        $value = $this->get($key, $default, $scope);

        if ($value !== null && !is_numeric($value)) {
            throw new ConfigException(
                sprintf('Configuration key "%s" is not a float', $key),
                sprintf('Expected float, got %s', get_debug_type($value)),
                'Ensure your config file returns a float for this key.',
            );
        }

        return (float) $value;
    }

    /**
     * @throws ConfigNotFoundException|ConfigException
     */
    public function getArray(
        string $key,
        ?array $default = null,
        ?string $scope = null,
    ): array {
        $this->ensureKeyExists($key, $default, $scope);
        $value = $this->get($key, $default, $scope);

        if ($value !== null && !is_array($value)) {
            throw new ConfigException(
                sprintf('Configuration key "%s" is not an array', $key),
                sprintf('Expected array, got %s', get_debug_type($value)),
                'Ensure your config file returns an array for this key.',
            );
        }

        return (array) $value;
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function ensureKeyExists(
        string $key,
        mixed $default,
        ?string $scope,
    ): void {
        if ($default === null && !$this->has($key, $scope)) {
            throw new ConfigNotFoundException($key);
        }
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
}
