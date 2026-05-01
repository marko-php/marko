<?php

declare(strict_types=1);

use Marko\Inertia\Frontend\InertiaFrontendInterface;
use Marko\Inertia\Svelte\SvelteInertiaFrontend;

return [
    'bindings' => [
        InertiaFrontendInterface::class => SvelteInertiaFrontend::class,
    ],
];
