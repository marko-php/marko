<?php

declare(strict_types=1);

use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Session;

return [
    'enabled' => true,
    'bindings' => [
        SessionInterface::class => Session::class,
    ],
];
