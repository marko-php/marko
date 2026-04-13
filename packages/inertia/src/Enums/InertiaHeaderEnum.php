<?php

declare(strict_types=1);

namespace Marko\Inertia\Enums;

enum InertiaHeaderEnum: string
{
    case INERTIA = 'X-Inertia';
    case VERSION = 'X-Inertia-Version';
    case LOCATION = 'X-Inertia-Location';
    case PARTIAL_COMPONENT = 'X-Inertia-Partial-Component';
    case PARTIAL_DATA = 'X-Inertia-Partial-Data';
    case PARTIAL_EXCEPT = 'X-Inertia-Partial-Except';
    case RESET = 'X-Inertia-Reset';
    case EXCEPT_ONCE = 'X-Inertia-Except-Once';
}
