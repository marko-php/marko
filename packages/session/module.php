<?php

declare(strict_types=1);

use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Session;

return [
    'bindings' => [
        SessionInterface::class => Session::class,
    ],
];
