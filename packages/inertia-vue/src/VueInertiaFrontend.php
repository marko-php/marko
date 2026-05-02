<?php

declare(strict_types=1);

namespace Marko\Inertia\Vue;

use Marko\Inertia\Frontend\InertiaFrontendInterface;

class VueInertiaFrontend implements InertiaFrontendInterface
{
    public function name(): string
    {
        return 'vue';
    }
}
