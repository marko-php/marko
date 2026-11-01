<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

use Marko\Authentication\AuthenticatableInterface;

/**
 * A step a user must complete after their password checks out and before they
 * are logged in, such as a two-factor code or a passkey.
 *
 * Challenges are listed per guard in config as
 * authentication.guards.<name>.challenges, a list of class names resolved from
 * the container. A guard that supports them (ChallengeableGuardInterface) asks
 * each challenge in order whether this user needs it.
 */
interface LoginChallengeInterface
{
    /**
     * Whether $user must complete this challenge before being logged in.
     */
    public function requiresChallenge(
        AuthenticatableInterface $user,
    ): bool;

    /**
     * The name stored with a pending challenge, e.g. 'two_factor'. Unique per guard.
     */
    public function getName(): string;
}
