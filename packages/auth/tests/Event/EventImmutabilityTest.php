<?php

declare(strict_types=1);

use Marko\Auth\AuthenticatableInterface;
use Marko\Auth\Event\FailedLoginEvent;
use Marko\Auth\Event\LoginEvent;
use Marko\Auth\Event\LogoutEvent;
use Marko\Auth\Event\PasswordResetEvent;

it('all events are immutable', function () {
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

    // Check LoginEvent properties are readonly
    $loginEvent = new ReflectionClass(LoginEvent::class);
    expect($loginEvent->getProperty('user')->isReadOnly())->toBeTrue()
        ->and($loginEvent->getProperty('guard')->isReadOnly())->toBeTrue()
        ->and($loginEvent->getProperty('remember')->isReadOnly())->toBeTrue();

    // Check LogoutEvent properties are readonly
    $logoutEvent = new ReflectionClass(LogoutEvent::class);
    expect($logoutEvent->getProperty('user')->isReadOnly())->toBeTrue()
        ->and($logoutEvent->getProperty('guard')->isReadOnly())->toBeTrue();

    // Check FailedLoginEvent properties are readonly
    $failedLoginEvent = new ReflectionClass(FailedLoginEvent::class);
    expect($failedLoginEvent->getProperty('credentials')->isReadOnly())->toBeTrue()
        ->and($failedLoginEvent->getProperty('guard')->isReadOnly())->toBeTrue();

    // Check PasswordResetEvent properties are readonly
    $passwordResetEvent = new ReflectionClass(PasswordResetEvent::class);
    expect($passwordResetEvent->getProperty('user')->isReadOnly())->toBeTrue();
});
