<?php

declare(strict_types=1);

use Marko\Auth\AuthenticatableInterface;
use Marko\Auth\Event\FailedLoginEvent;
use Marko\Auth\Event\LoginEvent;
use Marko\Auth\Event\LogoutEvent;
use Marko\Auth\Event\PasswordResetEvent;

it('all events have getter methods', function () {
    $user = new class () implements AuthenticatableInterface
    {
        public function getAuthIdentifier(): int|string
        {
            return 1;
        }

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthPassword(): string
        {
            return 'hashed_password';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken(
            ?string $token,
        ): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };

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
