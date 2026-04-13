<?php

declare(strict_types=1);

namespace Marko\Inertia\Interfaces;

use Marko\Inertia\Props\PropertyContext;

interface ProvidesInertiaProperty
{
    public function shouldInclude(PropertyContext $context): bool;

    public function resolve(PropertyContext $context): mixed;
}
