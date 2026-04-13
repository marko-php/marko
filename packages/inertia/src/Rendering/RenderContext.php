<?php

declare(strict_types=1);

namespace Marko\Inertia\Rendering;

use Marko\Routing\Http\Request;

readonly class RenderContext
{
    public function __construct(
        public string $component,
        public Request $request,
    ) {}
}
