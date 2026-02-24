<?php

declare(strict_types=1);

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Event\LogoutEvent;

it('creates LogoutEvent with user and guard name', function () {
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

    $event = new LogoutEvent(
        user: $user,
        guard: 'web',
    );

    expect($event->user)->toBe($user)
        ->and($event->guard)->toBe('web');
});
