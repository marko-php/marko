<?php

declare(strict_types=1);

namespace Marko\Scope\Storage;

use Marko\Database\Attributes\Column;
use Marko\Database\Entity\Entity;

abstract class ScopedOverridesEntity extends Entity
{
    #[Column(name: 'scopes', type: 'json', nullable: true)]
    public ?array $scopes = null;

    public function setOverride(
        string $scopeKey,
        string $property,
        mixed $value,
    ): void
    {
        $scopes = $this->scopes ?? [];
        $scopes[$scopeKey][$property] = $value;
        ksort($scopes[$scopeKey]);
        ksort($scopes);
        $this->scopes = $scopes;
    }

    public function getOverride(
        string $scopeKey,
        string $property,
    ): mixed
    {
        return $this->scopes[$scopeKey][$property] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allOverrides(): array
    {
        return $this->scopes ?? [];
    }

    public function hasOverride(
        string $scopeKey,
        string $property,
    ): bool
    {
        return array_key_exists($scopeKey, $this->scopes ?? [])
            && array_key_exists($property, $this->scopes[$scopeKey]);
    }

    public function clearOverride(
        string $scopeKey,
        string $property,
    ): void
    {
        if (!isset($this->scopes[$scopeKey])) {
            return;
        }

        $scopes = $this->scopes;
        unset($scopes[$scopeKey][$property]);

        if ($scopes[$scopeKey] === []) {
            unset($scopes[$scopeKey]);
        }

        ksort($scopes);
        $this->scopes = $scopes ?: null;
    }
}
