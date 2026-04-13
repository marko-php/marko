<?php

declare(strict_types=1);

namespace Marko\Inertia\Props;

use Marko\Inertia\Interfaces\ProvidesInertiaProperty;

final readonly class AlwaysProp implements ProvidesInertiaProperty
{
    public function __construct(
        private mixed $value,
    ) {}

    public function shouldInclude(PropertyContext $context): bool
    {
        return ! in_array($context->key, $context->except, true);
    }

    public function resolve(PropertyContext $context): mixed
    {
        return $this->value;
    }
}
