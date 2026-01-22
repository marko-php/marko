<?php

declare(strict_types=1);

use Marko\Validation\Contracts\ValidatorInterface;
use Marko\Validation\Validation\Validator;

return [
    'enabled' => true,

    'bindings' => [
        ValidatorInterface::class => Validator::class,
    ],
];
