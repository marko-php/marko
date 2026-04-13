<?php

declare(strict_types=1);

namespace Marko\Inertia\Props;

use Marko\Inertia\Contracts\Onceable;
use Marko\Inertia\Interfaces\ProvidesInertiaProperty;

final readonly class OnceProp implements Onceable, ProvidesInertiaProperty
{
    public function __construct(
        private mixed $value,
        private ?string $key = null,
        private bool $refresh = false,
    ) {}

    public function key(): ?string
    {
        return $this->key;
    }

    public function shouldRefresh(): bool
    {
        return $this->refresh;
    }

    public function refresh(): self
    {
        return new self($this->value, $this->key, true);
    }

    public function shouldInclude(PropertyContext $context): bool
    {
        return ! $context->isOnceLoaded($this->key() ?? $context->key) || $this->shouldRefresh();
    }

    public function resolve(PropertyContext $context): mixed
    {
        return $this->value;
    }
}
