<?php

declare(strict_types=1);

use Marko\Authentication\Event\FailedLoginEvent;
use Marko\Authentication\Event\LoginEvent;
use Marko\Authentication\Event\LogoutEvent;
use Marko\Authentication\Event\PasswordResetEvent;
use Marko\Testing\Fake\FakeAuthenticatable;

it('all events have getter methods', function () {
    $user = new FakeAuthenticatable(id: 1);

    // LoginEvent getter methods
    $loginEvent = new LoginEvent(
        user: $user,
        guard: 'web',
        remember: true,
    );
    expect($loginEvent->getUser())->toBe($user)
        ->and($loginEvent->getGuard())->toBe('web')
        ->and($loginEvent->getRemember())->toBeTrue();

    // LogoutEvent getter methods
    $logoutEvent = new LogoutEvent(
        user: $user,
        guard: 'api',
    );
    expect($logoutEvent->getUser())->toBe($user)
        ->and($logoutEvent->getGuard())->toBe('api');

    // FailedLoginEvent getter methods
    $failedLoginEvent = new FailedLoginEvent(
        credentials: ['email' => 'test@example.com'],
        guard: 'web',
    );
    expect($failedLoginEvent->getCredentials())->toBe(['email' => 'test@example.com'])
        ->and($failedLoginEvent->getGuard())->toBe('web');

    // PasswordResetEvent getter methods
    $passwordResetEvent = new PasswordResetEvent(
        user: $user,
    );
    expect($passwordResetEvent->getUser())->toBe($user);
});
