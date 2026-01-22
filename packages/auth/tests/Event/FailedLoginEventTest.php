<?php

declare(strict_types=1);

use Marko\Auth\Event\FailedLoginEvent;

it('creates FailedLoginEvent with credentials and guard name', function () {
    $credentials = [
        'email' => 'test@example.com',
        'password' => 'secret123',
    ];

    $event = new FailedLoginEvent(
        credentials: $credentials,
        guard: 'web',
    );

    expect($event->credentials)->toBe(['email' => 'test@example.com'])
        ->and($event->guard)->toBe('web');
});

it('creates FailedLoginEvent without exposing password', function () {
    $credentials = [
        'email' => 'test@example.com',
        'password' => 'super_secret_password_123',
    ];

    $event = new FailedLoginEvent(
        credentials: $credentials,
        guard: 'web',
    );

    expect($event->credentials)->not->toHaveKey('password')
        ->and($event->credentials)->toHaveKey('email');
});
