<?php

declare(strict_types=1);

namespace Marko\Authentication;

use DateTimeImmutable;

/**
 * A user whose email address must be verified before EnsureEmailVerifiedMiddleware lets them through.
 *
 * Apps implement it on their own user entity, typically backed by a nullable
 * `email_verified_at` column.
 */
interface MustVerifyEmailInterface extends AuthenticatableInterface
{
    /**
     * Whether the user has verified their email address.
     */
    public function hasVerifiedEmail(): bool;

    /**
     * Record the verification time. EmailVerifierInterface saves the user through
     * UserPersisterInterface afterwards.
     */
    public function markEmailAsVerified(
        DateTimeImmutable $verifiedAt,
    ): void;

    /**
     * The address the verification link is sent to. Changing it invalidates links already sent.
     */
    public function getEmailForVerification(): string;
}
