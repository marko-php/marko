<?php

declare(strict_types=1);

use Demo\Greeter\Contracts\GreeterInterface;
use Demo\Greeter\DefaultGreeter;

return [
    'bindings' => [
        GreeterInterface::class => DefaultGreeter::class,
    ],
];
