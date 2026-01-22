<?php

declare(strict_types=1);

use Marko\Auth\AuthenticatableInterface;
use Marko\Auth\Event\LoginEvent;

it('creates LoginEvent with user and guard name', function () {
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

    $event = new LoginEvent(
        user: $user,
        guard: 'web',
    );

    expect($event->user)->toBe($user)
        ->and($event->guard)->toBe('web');
});

it('creates LoginEvent with remember flag', function () {
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
