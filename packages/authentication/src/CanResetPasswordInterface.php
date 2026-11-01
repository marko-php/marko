<?php

declare(strict_types=1);

namespace Marko\Authentication;

/**
 * A user whose password can be reset through PasswordResetBrokerInterface.
 */
interface CanResetPasswordInterface extends AuthenticatableInterface
{
    /**
     * Replace the stored password hash. The broker hashes the password and saves
     * the user through UserPersisterInterface afterwards.
     */
    public function setAuthPassword(
        string $hash,
    ): void;

    /**
     * The address the reset link is sent to.
     */
    public function getEmailForPasswordReset(): string;
}
