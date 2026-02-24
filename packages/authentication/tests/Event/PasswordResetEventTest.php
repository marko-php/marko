<?php

declare(strict_types=1);

use Marko\Authentication\Event\PasswordResetEvent;
use Marko\Testing\Fake\FakeAuthenticatable;

it('creates PasswordResetEvent with user', function () {
    $user = new FakeAuthenticatable(id: 1);

    $event = new PasswordResetEvent(
        user: $user,
    );

    expect($event->user)->toBe($user);
});
