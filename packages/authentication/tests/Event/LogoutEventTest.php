<?php

declare(strict_types=1);

use Marko\Authentication\Event\LogoutEvent;
use Marko\Testing\Fake\FakeAuthenticatable;

it('creates LogoutEvent with user and guard name', function () {
    $user = new FakeAuthenticatable(id: 1);

    $event = new LogoutEvent(
        user: $user,
        guard: 'web',
    );

    expect($event->user)->toBe($user)
        ->and($event->guard)->toBe('web');
});
