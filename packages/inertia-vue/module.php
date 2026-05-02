<?php

declare(strict_types=1);

use Marko\Inertia\Frontend\InertiaFrontendInterface;
use Marko\Inertia\Vue\VueInertiaFrontend;

return [
    'bindings' => [
        InertiaFrontendInterface::class => VueInertiaFrontend::class,
    ],
];
