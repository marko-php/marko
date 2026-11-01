<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

use Marko\Authentication\CanResetPasswordInterface;

/**
 * Delivers a password reset link. The app implements it, so it owns the message and the URL.
 *
 * Queue the delivery rather than sending inline: the broker answers an unknown
 * email immediately, so a slow inline send would let response timing reveal
 * which emails are registered.
 */
interface ResetLinkNotifierInterface
{
    /**
     * Send the plain token to the user, typically as a link to the app's reset form.
     */
    public function send(
        CanResetPasswordInterface $user,
        string $token,
    ): void;
}
