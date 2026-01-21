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
     *
     * Throws ConfigNotFoundException if key is not found and no default is provided.
     *
     * @throws Exceptions\ConfigNotFoundException
     */
    public function getString(
        string $key,
        ?string $default = null,
        ?string $scope = null,
    ): string;

    /**
     * Get a configuration value as an integer.
     *
     * Throws ConfigNotFoundException if key is not found and no default is provided.
     *
     * @throws Exceptions\ConfigNotFoundException
     */
    public function getInt(
        string $key,
        ?int $default = null,
        ?string $scope = null,
    ): int;

    /**
     * Get a configuration value as a boolean.
     *
     * Throws ConfigNotFoundException if key is not found and no default is provided.
     *
     * @throws Exceptions\ConfigNotFoundException
     */
    public function getBool(
        string $key,
        ?bool $default = null,
        ?string $scope = null,
    ): bool;

    /**
     * Get a configuration value as a float.
     *
     * Throws ConfigNotFoundException if key is not found and no default is provided.
     *
     * @throws Exceptions\ConfigNotFoundException
     */
    public function getFloat(
        string $key,
        ?float $default = null,
        ?string $scope = null,
    ): float;

    /**
     * Get a configuration value as an array.
     *
     * Throws ConfigNotFoundException if key is not found and no default is provided.
     *
     * @throws Exceptions\ConfigNotFoundException
     */
    public function getArray(
        string $key,
        ?array $default = null,
        ?string $scope = null,
    ): array;

    public function all(?string $scope = null): array;
}
