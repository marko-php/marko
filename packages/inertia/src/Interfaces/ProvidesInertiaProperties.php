<?php

declare(strict_types=1);

namespace Marko\Inertia\Interfaces;

use Marko\Inertia\Rendering\RenderContext;

interface ProvidesInertiaProperties
{
    /**
     * @return array<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): array;
}
