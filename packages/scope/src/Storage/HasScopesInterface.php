<?php

declare(strict_types=1);

namespace Marko\Scope\Storage;

interface HasScopesInterface
{
    public function setOverride(
        string $scopeKey,
        string $property,
        mixed $value,
    ): void;

    public function getOverride(
        string $scopeKey,
        string $property,
    ): mixed;

    public function hasOverride(
        string $scopeKey,
        string $property,
    ): bool;

    public function clearOverride(
        string $scopeKey,
        string $property,
    ): void;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allOverrides(): array;
}
