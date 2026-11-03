<?php

declare(strict_types=1);

namespace Marko\Authentication\PasswordReset;

use JsonException;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\CanResetPasswordInterface;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Contracts\PasswordResetBrokerInterface;
use Marko\Authentication\Contracts\PasswordResetTokenRepositoryInterface;
use Marko\Authentication\Contracts\ResetLinkNotifierInterface;
use Marko\Authentication\Event\PasswordResetEvent;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\UserPersisterException;
use Marko\Authentication\UserPersisterResolver;
use Marko\Authentication\UserProviderResolver;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\RateLimiter\Contracts\RateLimiterInterface;
use Psr\Clock\ClockInterface;
use Random\RandomException;

/**
 * Issues and redeems password reset tokens.
 *
 * Tokens are 64 random bytes, sent to the user in hex and stored only as a SHA-256
 * hash with their creation time. A token is checked with hash_equals(), rejected
 * once it is expire_seconds old, and deleted when it is used, so it works once.
 *
 * Link requests are throttled to one per throttle_seconds: per user when the
 * credentials match one, per credentials when they do not, so the Throttled
 * status does not reveal which emails are registered either.
 */
readonly class PasswordResetBroker implements PasswordResetBrokerInterface
{
    private const int TOKEN_BYTES = 64;

    public function __construct(
        private AuthConfig $authConfig,
        private UserProviderResolver $userProviderResolver,
        private UserPersisterResolver $userPersisterResolver,
        private PasswordResetTokenRepositoryInterface $passwordResetTokenRepository,
        private ResetLinkNotifierInterface $resetLinkNotifier,
        private PasswordHasherInterface $passwordHasher,
        private RateLimiterInterface $rateLimiter,
        private EventDispatcherInterface $eventDispatcher,
        private ClockInterface $clock,
    ) {}

    /**
     * @throws AuthException|UserPersisterException|ConfigNotFoundException|JsonException|RandomException
     */
    public function sendResetLink(
        array $credentials,
    ): ResetLinkStatus {
        $providerName = $this->authConfig->passwordResetProvider();
        // Resolve the persister up front so a missing one fails on every request,
        // not only for registered emails (which would reveal them) or later at reset().
        $this->userPersisterResolver->forProvider($providerName);
        $user = $this->userProviderResolver->forProvider($providerName)->retrieveByCredentials($credentials);

        $attempt = $this->rateLimiter->attempt(
            $this->throttleKey($providerName, $user, $credentials),
            1,
            $this->authConfig->passwordResetThrottleSeconds(),
        );

        if (!$attempt->allowed()) {
            return ResetLinkStatus::Throttled;
        }

        if ($user === null) {
            return ResetLinkStatus::Sent;
        }

        $this->userPersisterResolver->assertUserSupports($user, CanResetPasswordInterface::class, $providerName);

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $this->passwordResetTokenRepository->store(
            $user->getAuthIdentifier(),
            hash('sha256', $token),
            $this->clock->now(),
        );
        $this->resetLinkNotifier->send($user, $token);

        return ResetLinkStatus::Sent;
    }

    /**
     * @throws AuthException|UserPersisterException|ConfigNotFoundException
     */
    public function tokenIsValid(
        array $credentials,
        string $token,
    ): bool {
        $providerName = $this->authConfig->passwordResetProvider();
        // Resolve the persister up front, as reset() does, so a missing one fails on every
        // check rather than only for registered emails, and before a link is called valid
        // that reset() could not redeem.
        $this->userPersisterResolver->forProvider($providerName);
        $user = $this->resettableUser($providerName, $credentials);

        if ($user === null) {
            return false;
        }

        $record = $this->matchingRecord($user, $token);

        return $record !== null && !$this->isExpired($record);
    }

    /**
     * @throws AuthException|UserPersisterException|ConfigNotFoundException
     */
    public function reset(
        array $credentials,
        string $token,
        string $newPassword,
    ): ResetStatus {
        $providerName = $this->authConfig->passwordResetProvider();
        $userPersister = $this->userPersisterResolver->forProvider($providerName);
        $user = $this->resettableUser($providerName, $credentials);

        if ($user === null) {
            return ResetStatus::InvalidUser;
        }

        $record = $this->matchingRecord($user, $token);

        if ($record === null) {
            return ResetStatus::InvalidToken;
        }

        // Single use: the token is gone before the password changes, whatever happens next.
        $this->passwordResetTokenRepository->delete($user->getAuthIdentifier());

        if ($this->isExpired($record)) {
            return ResetStatus::InvalidToken;
        }

        $user->setAuthPassword($this->passwordHasher->hash($newPassword));
        $userPersister->persist($user);
        $this->eventDispatcher->dispatch(new PasswordResetEvent($user));

        return ResetStatus::PasswordReset;
    }

    /**
     * The user matching $credentials, asserted to support password resets, or null when none matches.
     *
     * @param array<string, mixed> $credentials
     * @throws AuthException|UserPersisterException|ConfigNotFoundException
     */
    private function resettableUser(
        string $providerName,
        array $credentials,
    ): ?AuthenticatableInterface {
        $user = $this->userProviderResolver->forProvider($providerName)->retrieveByCredentials($credentials);

        if ($user !== null) {
            $this->userPersisterResolver->assertUserSupports($user, CanResetPasswordInterface::class, $providerName);
        }

        return $user;
    }

    /**
     * The user's stored token record when $token hashes to it, or null. Does not check expiry.
     */
    private function matchingRecord(
        AuthenticatableInterface $user,
        string $token,
    ): ?PasswordResetTokenRecord {
        $record = $this->passwordResetTokenRepository->find($user->getAuthIdentifier());

        if ($record === null || !hash_equals($record->tokenHash, hash('sha256', $token))) {
            return null;
        }

        return $record;
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function isExpired(
        PasswordResetTokenRecord $record,
    ): bool {
        $expiresAt = $record->createdAt->getTimestamp() + $this->authConfig->passwordResetExpireSeconds();

        return $this->clock->now()->getTimestamp() >= $expiresAt;
    }

    /**
     * Per user when the credentials match one, per credentials otherwise.
     *
     * @param array<string, mixed> $credentials
     * @throws JsonException
     */
    private function throttleKey(
        string $providerName,
        ?AuthenticatableInterface $user,
        array $credentials,
    ): string {
        if ($user !== null) {
            return "password_reset:$providerName:user:{$user->getAuthIdentifier()}";
        }

        $credentialsHash = hash('sha256', json_encode($credentials, JSON_THROW_ON_ERROR));

        return "password_reset:$providerName:credentials:$credentialsHash";
    }
}
