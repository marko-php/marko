<?php

declare(strict_types=1);

namespace Marko\Config;

use Marko\Config\Exceptions\ConfigNotFoundException;

interface ConfigRepositoryInterface
{
    /**
     * Get a configuration value.
     *
     * Supports dot notation for nested keys (e.g., 'database.host').
     *
     * @throws ConfigNotFoundException When the key does not exist
     */
    public function get(
        string $key,
        ?string $scope = null,
    ): mixed;

    public function has(
        string $key,
        ?string $scope = null,
    ): bool;

    /**
     * Get a configuration value as a string.
     *
     * @throws ConfigNotFoundException When the key does not exist
     */
    public function getString(
        string $key,
        ?string $scope = null,
    ): string;

    /**
     * Get a configuration value as an integer.
     *
     * @throws ConfigNotFoundException When the key does not exist
     */
    public function getInt(
        string $key,
        ?string $scope = null,
    ): int;

    /**
     * Get a configuration value as a boolean.
     *
     * @throws ConfigNotFoundException When the key does not exist
     */
    public function getBool(
        string $key,
        ?string $scope = null,
    ): bool;

    /**
     * Get a configuration value as a float.
     *
     * @throws ConfigNotFoundException When the key does not exist
     */
    public function getFloat(
        string $key,
        ?string $scope = null,
    ): float;

    /**
     * Get a configuration value as an array.
     *
     * @throws ConfigNotFoundException When the key does not exist
     */
    public function getArray(
        string $key,
        ?string $scope = null,
    ): array;

    public function all(?string $scope = null): array;

    /**
     * Create a new ConfigRepository with a default scope.
     *
     * Returns a new instance that automatically uses the given scope
     * for all configuration lookups without passing scope explicitly.
     */
    public function withScope(string $scope): ConfigRepositoryInterface;
}
