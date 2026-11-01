<?php

declare(strict_types=1);

namespace Marko\Authentication\PasswordConfirmation;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\PasswordConfirmationInterface;
use Marko\Authentication\Contracts\PasswordVerifierInterface;
use Marko\Authentication\Exceptions\TooManyPasswordConfirmationAttemptsException;
use Marko\RateLimiter\Contracts\RateLimiterInterface;
use Marko\Session\Contracts\SessionInterface;
use Psr\Clock\ClockInterface;

/**
 * Stamps the session with the confirmation time from the clock.
 *
 * Each confirm() call counts as an attempt for the user in the rate limiter; a
 * correct password clears the count, so only consecutive failures lead to a
 * lockout.
 */
readonly class PasswordConfirmation implements PasswordConfirmationInterface
{
    public function __construct(
        private PasswordVerifierInterface $passwordVerifier,
        private SessionInterface $session,
        private ClockInterface $clock,
        private RateLimiterInterface $rateLimiter,
        private AuthConfig $authConfig,
    ) {}

    public function confirm(
        AuthenticatableInterface $user,
        string $password,
    ): bool {
        $throttleKey = $this->throttleKey($user);
        $attempt = $this->rateLimiter->attempt(
            $throttleKey,
            $this->authConfig->passwordConfirmationThrottleMaxAttempts(),
            $this->authConfig->passwordConfirmationThrottleDecaySeconds(),
        );

        if (!$attempt->allowed()) {
            throw TooManyPasswordConfirmationAttemptsException::lockedOut(
                $attempt->retryAfter() ?? $this->authConfig->passwordConfirmationThrottleDecaySeconds(),
            );
        }

        if (!$this->passwordVerifier->verify($user, $password)) {
            return false;
        }

        $this->rateLimiter->clear($throttleKey);
        $this->markConfirmed();

        return true;
    }

    public function markConfirmed(): void
    {
        $this->session->set(self::SESSION_KEY, $this->clock->now()->getTimestamp());
    }

    public function isRecentlyConfirmed(): bool
    {
        $confirmedAt = $this->session->get(self::SESSION_KEY);

        if (!is_int($confirmedAt)) {
            return false;
        }

        $age = $this->clock->now()->getTimestamp() - $confirmedAt;

        return $age >= 0 && $age < $this->authConfig->passwordConfirmationTimeoutSeconds();
    }

    private function throttleKey(
        AuthenticatableInterface $user,
    ): string {
        return 'password_confirmation:' . $user::class . ':' . $user->getAuthIdentifier();
    }
}
