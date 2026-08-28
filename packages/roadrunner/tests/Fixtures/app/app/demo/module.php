<?php

declare(strict_types=1);

use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeUserProvider;

return [
    'bindings' => [
        UserProviderInterface::class => static fn (): UserProviderInterface => new FakeUserProvider(
            users: [1 => new FakeAuthenticatable(id: 1)],
        ),
    ],
];
