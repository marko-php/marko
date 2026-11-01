<?php

declare(strict_types=1);

use Marko\Forms\SessionFlashedFormState;
use Marko\Validation\Contracts\FlashedFormStateInterface;

return [
    'bindings' => [
        FlashedFormStateInterface::class => SessionFlashedFormState::class,
    ],
];
