<?php

declare(strict_types=1);

namespace Marko\Inertia\Svelte;

use Marko\Inertia\Frontend\InertiaFrontendInterface;

class SvelteInertiaFrontend implements InertiaFrontendInterface
{
    public function name(): string
    {
        return 'svelte';
    }
}
