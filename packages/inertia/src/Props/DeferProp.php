<?php

declare(strict_types=1);

namespace Marko\Inertia\Props;

use Marko\Inertia\Contracts\Deferrable;
use Marko\Inertia\Interfaces\ProvidesInertiaProperty;

final readonly class DeferProp implements Deferrable, ProvidesInertiaProperty
{
    public function __construct(
        private mixed $value,
        private string $group = 'default',
    ) {}

    public function group(): string
    {
        return $this->group;
    }

    public function shouldInclude(PropertyContext $context): bool
    {
        if (! $context->isPartial) {
            return false;
        }

        if ($context->only === []) {
            return true;
        }

        return in_array($context->key, $context->only, true)
            && ! in_array($context->key, $context->except, true);
    }

    public function resolve(PropertyContext $context): mixed
    {
        return $this->value;
    }
}
