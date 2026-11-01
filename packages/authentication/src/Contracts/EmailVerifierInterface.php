<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

use Marko\Authentication\EmailVerification\VerificationLinkStatus;
use Marko\Authentication\EmailVerification\VerificationStatus;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\Authentication\Exceptions\UserPersisterException;
use Marko\Authentication\MustVerifyEmailInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Exceptions\InvalidSignatureException;
use Marko\Routing\Exceptions\UrlGenerationException;
use Marko\Routing\Exceptions\UrlSigningException;
use Marko\Routing\Http\Request;

/**
 * Sends signed email verification links and verifies users who follow them.
 */
interface EmailVerifierInterface
{
    /**
     * Send a signed, expiring verification link through VerificationNotifierInterface.
     * At most one link per user is sent every authentication.verification.throttle_seconds.
     *
     * @throws AuthException|ConfigNotFoundException|UrlGenerationException|UrlSigningException
     */
    public function sendVerificationLink(
        MustVerifyEmailInterface $user,
    ): VerificationLinkStatus;

    /**
     * Verify the logged-in user from the request to the signed verification link,
     * then persist them and dispatch EmailVerifiedEvent.
     *
     * A link that is tampered with, expired, sent to another user, or sent to an
     * email address the user has since changed throws InvalidSignatureException (403).
     *
     * @throws InvalidSignatureException|UnauthenticatedException|AuthException|UserPersisterException|UrlSigningException|ConfigNotFoundException
     */
    public function verify(
        Request $request,
    ): VerificationStatus;
}
