<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

use Marko\Authentication\AuthenticatableInterface;

/**
 * A guard whose password logins can be held back by login challenges.
 *
 * When attempt() checks the credentials and a configured LoginChallengeInterface
 * requires itself for the user, the guard stores the pending user and challenge
 * name (under a fresh session ID) instead of logging the user in, and attempt()
 * returns false. The code handling the challenge (e.g. a two-factor form) then
 * calls completeChallenge(), which moves on to the next required challenge or,
 * when none is left, logs the user in and dispatches LoginEvent.
 *
 * login() and loginById() bypass challenges by design: app code calling them
 * has already decided the user is authenticated.
 */
interface ChallengeableGuardInterface extends GuardInterface
{
    /**
     * Whether a user passed the password check and is waiting on a challenge.
     */
    public function hasPendingChallenge(): bool;

    /**
     * The user waiting on a challenge, or null when none is pending (or the user no longer exists).
     */
    public function pendingChallengeUser(): ?AuthenticatableInterface;

    /**
     * The name of the challenge the pending user must complete next, or null when none is pending.
     */
    public function pendingChallengeName(): ?string;

    /**
     * Mark the pending challenge as passed. Moves on to the next challenge that
     * requires itself for the user, or logs the user in when none is left.
     *
     * @param bool|null $remember Issue a remember-me cookie when the user is logged in;
     *                            null keeps the flag the pending login was attempted with
     */
    public function completeChallenge(
        ?bool $remember = null,
    ): void;

    /**
     * Forget the pending challenge and user. The user stays logged out.
     */
    public function cancelChallenge(): void;
}
