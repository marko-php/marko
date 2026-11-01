<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

use Marko\Authentication\AuthenticatableInterface;

/**
 * The framework's one check of a user's password.
 *
 * Password confirmation, the CurrentPassword validation rule and any later
 * feature that asks "is this the user's password?" go through here rather
 * than calling PasswordHasherInterface::verify() themselves.
 */
interface PasswordVerifierInterface
{
    /**
     * Whether the password matches the user's stored hash. It has no side effects:
     * no session writes, no throttling, no events.
     *
     * A null user (no account to check) still costs one dummy hash check and returns
     * false, so response timing does not reveal whether the user exists.
     */
    public function verify(
        ?AuthenticatableInterface $user,
        string $password,
    ): bool;
}
