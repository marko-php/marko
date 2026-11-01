<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

use Marko\Authentication\MustVerifyEmailInterface;

/**
 * Delivers an email verification link. The app implements it, so it owns the message.
 */
interface VerificationNotifierInterface
{
    /**
     * Send the signed verification URL to the user's getEmailForVerification() address.
     */
    public function send(
        MustVerifyEmailInterface $user,
        string $url,
    ): void;
}
