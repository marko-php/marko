<?php

declare(strict_types=1);

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Event\PasswordResetEvent;

it('creates PasswordResetEvent with user', function () {
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

    $event = new PasswordResetEvent(
        user: $user,
    );

    expect($event->user)->toBe($user);
});
