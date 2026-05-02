<?php

declare(strict_types=1);

namespace Marko\Inertia\React;

use Marko\Inertia\Frontend\InertiaFrontendInterface;

class ReactInertiaFrontend implements InertiaFrontendInterface
{
    public function name(): string
    {
        return 'react';
    }
}
