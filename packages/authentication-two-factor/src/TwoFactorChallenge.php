<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor;

use Marko\Authentication\AuthManager;
use Marko\Authentication\Contracts\ChallengeableGuardInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\AuthenticationTwoFactor\Config\TwoFactorConfig;
use Marko\AuthenticationTwoFactor\Contracts\TwoFactorAuthenticatableInterface;
use Marko\AuthenticationTwoFactor\Contracts\TwoFactorManagerInterface;
use Marko\AuthenticationTwoFactor\Event\TwoFactorChallengeFailedEvent;
use Marko\AuthenticationTwoFactor\Exceptions\TooManyTwoFactorAttemptsException;
use Marko\AuthenticationTwoFactor\Exceptions\TwoFactorException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\RateLimiter\Contracts\RateLimiterInterface;

/**
 * Handles the two-factor step of a login held by TwoFactorLoginChallenge.
 *
 * After attempt() returns false with isPending() true, show the code form and
 * pass the submitted code to verify(). A valid TOTP or recovery code completes
 * the challenge on the guard, which logs the user in.
 *
 * Attempts are throttled per guard and pending user through marko/ratelimiter:
 * more than authentication-two-factor.throttle.max_attempts within
 * decay_seconds throws TooManyTwoFactorAttemptsException (429).
 */
readonly class TwoFactorChallenge
{
    public function __construct(
        private AuthManager $authManager,
        private TwoFactorManagerInterface $twoFactorManager,
        private TwoFactorConfig $twoFactorConfig,
        private RateLimiterInterface $rateLimiter,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Whether a user on $guard is waiting to enter a two-factor code.
     *
     * @throws AuthException|ConfigNotFoundException|TwoFactorException
     */
    public function isPending(
        ?string $guard = null,
    ): bool {
        $challengeableGuard = $this->guard($guard);

        return $challengeableGuard->hasPendingChallenge()
            && $challengeableGuard->pendingChallengeName() === TwoFactorLoginChallenge::NAME;
    }

    /**
     * Check a TOTP or recovery code for the pending user, completing the login when it is valid.
     *
     * @param bool $remember Issue a remember-me cookie when the login completes
     * @throws AuthException|ConfigNotFoundException|TooManyTwoFactorAttemptsException|TwoFactorException
     */
    public function verify(
        string $code,
        bool $remember = false,
        ?string $guard = null,
    ): bool {
        $challengeableGuard = $this->guard($guard);
        $guardName = $challengeableGuard->getName();
        $user = $challengeableGuard->pendingChallengeUser();

        if ($user === null || $challengeableGuard->pendingChallengeName() !== TwoFactorLoginChallenge::NAME) {
            throw TwoFactorException::noPendingTwoFactorChallenge($guardName);
        }

        if (!$user instanceof TwoFactorAuthenticatableInterface) {
            throw TwoFactorException::userNotTwoFactorAuthenticatable($user::class);
        }

        $throttleKey = "two_factor:$guardName:user:{$user->getAuthIdentifier()}";
        $attempt = $this->rateLimiter->attempt(
            $throttleKey,
            $this->twoFactorConfig->throttleMaxAttempts(),
            $this->twoFactorConfig->throttleDecaySeconds(),
        );

        if (!$attempt->allowed()) {
            throw TooManyTwoFactorAttemptsException::lockedOut(
                $guardName,
                $attempt->retryAfter() ?? $this->twoFactorConfig->throttleDecaySeconds(),
            );
        }

        if (!$this->twoFactorManager->verify($user, $code, $guardName)) {
            $this->eventDispatcher->dispatch(new TwoFactorChallengeFailedEvent($user, $guardName));

            return false;
        }

        $this->rateLimiter->clear($throttleKey);
        $challengeableGuard->completeChallenge($remember);

        return true;
    }

    /**
     * Abandon the pending login, e.g. from a "cancel" link on the code form.
     *
     * @throws AuthException|ConfigNotFoundException|TwoFactorException
     */
    public function cancel(
        ?string $guard = null,
    ): void {
        $this->guard($guard)->cancelChallenge();
    }

    /**
     * @throws AuthException|ConfigNotFoundException|TwoFactorException
     */
    private function guard(
        ?string $guard,
    ): ChallengeableGuardInterface {
        $resolved = $this->authManager->guard($guard);

        if (!$resolved instanceof ChallengeableGuardInterface) {
            throw TwoFactorException::guardNotChallengeable($resolved->getName());
        }

        return $resolved;
    }
}
