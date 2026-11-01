<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Ceremony;

use Marko\AuthenticationPasskey\Config\PasskeyConfig;
use Marko\AuthenticationPasskey\Event\PasskeyFailureReason;
use Marko\AuthenticationPasskey\Exceptions\PasskeyConfigurationException;
use Marko\AuthenticationPasskey\Exceptions\PasskeyVerificationException;
use Marko\Session\Contracts\SessionInterface;
use Psr\Clock\ClockInterface;
use Random\RandomException;

/**
 * Issues one random challenge per ceremony and keeps it in the session until
 * the response comes back. consume() removes it whatever the outcome, so a
 * challenge signs at most one response: a replayed response finds no
 * challenge. A challenge also expires after timeout_seconds.
 */
readonly class PasskeyChallengeStore
{
    public const string REGISTRATION = 'registration';

    public const string AUTHENTICATION = 'authentication';

    private const string SESSION_KEY_PREFIX = 'passkey.challenge.';

    public function __construct(
        private SessionInterface $session,
        private ClockInterface $clock,
        private PasskeyConfig $config,
    ) {}

    /**
     * Issue a new challenge for $ceremony, replacing any pending one.
     *
     * @param string $owner Who the ceremony is for ('' when not yet known); consume() must be given the same value
     * @return string The challenge bytes
     * @throws RandomException
     */
    public function issue(
        string $ceremony,
        string $owner = '',
    ): string {
        $challenge = random_bytes(32);

        $this->session->set(self::SESSION_KEY_PREFIX . $ceremony, [
            'challenge' => Base64Url::encode($challenge),
            'owner' => $owner,
            'issued_at' => $this->clock->now()->getTimestamp(),
        ]);

        return $challenge;
    }

    /**
     * Take the pending challenge for $ceremony out of the session.
     *
     * @return string The challenge bytes
     * @throws PasskeyVerificationException|PasskeyConfigurationException
     */
    public function consume(
        string $ceremony,
        string $owner = '',
    ): string {
        $key = self::SESSION_KEY_PREFIX . $ceremony;
        $pending = $this->session->get($key);
        $this->session->remove($key);

        if (
            !is_array($pending)
            || !is_string($pending['challenge'] ?? null)
            || !is_int($pending['issued_at'] ?? null)
            || ($pending['owner'] ?? null) !== $owner
        ) {
            throw PasskeyVerificationException::because(
                PasskeyFailureReason::ChallengeMismatch,
                "no $ceremony challenge is pending for this session (never issued, already used, or issued to another user)",
            );
        }

        $age = $this->clock->now()->getTimestamp() - $pending['issued_at'];

        if ($age < 0 || $age > $this->config->timeoutSeconds()) {
            throw PasskeyVerificationException::because(
                PasskeyFailureReason::ChallengeMismatch,
                "the $ceremony challenge expired $age seconds after it was issued",
            );
        }

        $challenge = Base64Url::decode($pending['challenge']);

        if ($challenge === null) {
            throw PasskeyVerificationException::because(
                PasskeyFailureReason::ChallengeMismatch,
                "the stored $ceremony challenge is not base64url",
            );
        }

        return $challenge;
    }
}
