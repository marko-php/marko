<?php

declare(strict_types=1);

use Marko\AuthenticationToken\Guard\TokenGuard;

return [
    'bindings' => [
        // TokenRepositoryInterface::class => ConcreteTokenRepository::class,
    ],
    'guards' => [
        'token' => TokenGuard::class,
    ],
];
