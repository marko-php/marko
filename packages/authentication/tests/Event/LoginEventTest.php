<?php

declare(strict_types=1);

use Marko\Authentication\Event\LoginEvent;
use Marko\Testing\Fake\FakeAuthenticatable;

it('creates LoginEvent with user and guard name', function () {
    $user = new FakeAuthenticatable(id: 1);

    $event = new LoginEvent(
        user: $user,
        guard: 'web',
    );

    expect($event->user)->toBe($user)
        ->and($event->guard)->toBe('web');
});

it('creates LoginEvent with remember flag', function () {
    $user = new FakeAuthenticatable(id: 1);

    $eventWithRemember = new LoginEvent(
        user: $user,
        guard: 'web',
        remember: true,
    );

    $eventWithoutRemember = new LoginEvent(
        user: $user,
        guard: 'web',
        remember: false,
    );

    expect($eventWithRemember->remember)->toBeTrue()
        ->and($eventWithoutRemember->remember)->toBeFalse();
});
