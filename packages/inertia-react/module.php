<?php

declare(strict_types=1);

use Marko\Inertia\Frontend\InertiaFrontendInterface;
use Marko\Inertia\React\ReactInertiaFrontend;

return [
    'bindings' => [
        InertiaFrontendInterface::class => ReactInertiaFrontend::class,
    ],
];
