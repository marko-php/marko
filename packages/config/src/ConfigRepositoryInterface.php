<?php

declare(strict_types=1);

namespace Marko\Config;

interface ConfigRepositoryInterface
{
    /**
     * Get a configuration value.
     *
     * Supports dot notation for nested keys (e.g., 'database.host').
     */
    public function get(
        string $key,
        mixed $default = null,
        ?string $scope = null,
    ): mixed;

    public function has(
        string $key,
        ?string $scope = null,
    ): bool;

    /**
     * Get a configuration value as a string.
     */
    public function getString(
        string $key,
        ?string $default = null,
        ?string $scope = null,
    ): string;

    /**
     * Get a configuration value as an integer.
     */
    public function getInt(
        string $key,
        ?int $default = null,
        ?string $scope = null,
    ): int;

    /**
     * Get a configuration value as a boolean.
     */
    public function getBool(
        string $key,
        ?bool $default = null,
        ?string $scope = null,
    ): bool;

    /**
     * Get a configuration value as a float.
     */
    public function getFloat(
        string $key,
        ?float $default = null,
        ?string $scope = null,
    ): float;

    /**
     * Get a configuration value as an array.
     */
    public function getArray(
        string $key,
        ?array $default = null,
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
