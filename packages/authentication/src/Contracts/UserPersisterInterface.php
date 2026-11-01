<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

use Marko\Authentication\AuthenticatableInterface;

/**
 * Saves a user after an authentication feature changed it.
 *
 * Features such as password reset change the user through a small feature
 * interface the app implements on its entity (e.g. CanResetPasswordInterface),
 * then call persist(). The app implements this once for its user store and
 * binds it, or names a class per provider with authentication.providers.<name>.persister.
 */
interface UserPersisterInterface
{
    /**
     * Save every change made to the user.
     */
    public function persist(
        AuthenticatableInterface $user,
    ): void;
}
