<?php

declare(strict_types=1);

use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Session;

return [
    'bindings' => [],
    'singletons' => [
        SessionInterface::class => Session::class,
    ],
];
