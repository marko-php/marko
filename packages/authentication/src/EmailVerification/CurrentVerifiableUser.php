<?php

declare(strict_types=1);

namespace Marko\Authentication\EmailVerification;

use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\Authentication\MustVerifyEmailInterface;

/**
 * The logged-in user of the default guard, as a user whose email can be verified.
 *
 * Shared by EmailVerifier and EnsureEmailVerifiedMiddleware. A guest throws
 * UnauthenticatedException; a user type that does not implement
 * MustVerifyEmailInterface throws a configuration error instead of being let through.
 */
readonly class CurrentVerifiableUser
{
    public function __construct(
        private GuardInterface $guard,
    ) {}

    /**
     * @throws AuthException|UnauthenticatedException
     */
    public function get(): MustVerifyEmailInterface
    {
        $user = $this->guard->user() ?? throw UnauthenticatedException::forGuard($this->guard);

        if (!$user instanceof MustVerifyEmailInterface) {
            throw AuthException::userCannotVerifyEmail($this->guard->getName(), $user::class);
        }

        return $user;
    }

    /**
     * The name of the guard the user is logged in on.
     */
    public function guardName(): string
    {
        return $this->guard->getName();
    }
}
