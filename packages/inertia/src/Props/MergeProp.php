<?php

declare(strict_types=1);

namespace Marko\Inertia\Props;

use Marko\Inertia\Contracts\Mergeable;
use Marko\Inertia\Interfaces\ProvidesInertiaProperty;

final readonly class MergeProp implements Mergeable, ProvidesInertiaProperty
{
    public function __construct(
        private mixed $value,
        private bool $deepMerge = false,
        private bool $prepend = false,
    ) {}

    public function shouldMerge(): bool
    {
        return true;
    }

    public function shouldDeepMerge(): bool
    {
        return $this->deepMerge;
    }

    public function shouldPrepend(): bool
    {
        return $this->prepend;
    }

    public function deepMerge(): self
    {
        return new self($this->value, deepMerge: true, prepend: $this->prepend);
    }

    public function prepend(): self
    {
        return new self($this->value, deepMerge: $this->deepMerge, prepend: true);
    }

    public function shouldInclude(PropertyContext $context): bool
    {
        if (! $context->isPartial) {
            return true;
        }

        if ($context->only !== []) {
            return in_array($context->key, $context->only, true)
                || $this->shouldPrepend()
                || $this->shouldDeepMerge();
        }

        return ! in_array($context->key, $context->except, true);
    }

    public function resolve(PropertyContext $context): mixed
    {
        return $this->value;
    }
}
