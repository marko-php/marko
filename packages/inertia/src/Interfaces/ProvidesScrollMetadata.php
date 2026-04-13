<?php

declare(strict_types=1);

namespace Marko\Inertia\Interfaces;

use Marko\Inertia\Rendering\RenderContext;

interface ProvidesScrollMetadata
{
    /**
     * @return array<string, mixed>
     */
    public function toScrollMetadata(RenderContext $context): array;
}
