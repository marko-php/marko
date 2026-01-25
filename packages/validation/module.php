<?php

declare(strict_types=1);

use Marko\Validation\Contracts\ValidatorInterface;
use Marko\Validation\Validation\Validator;

return [
    'bindings' => [
        ValidatorInterface::class => Validator::class,
    ],
];
