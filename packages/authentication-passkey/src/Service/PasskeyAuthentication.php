<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Service;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\ChallengeableGuardInterface;
use Marko\Authentication\Contracts\PasswordConfirmationInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\Authentication\Http\CurrentRequest;
use Marko\AuthenticationPasskey\Ceremony\AssertionResponse;
use Marko\AuthenticationPasskey\Ceremony\ClientDataVerifier;
use Marko\AuthenticationPasskey\Ceremony\PasskeyChallengeStore;
use Marko\AuthenticationPasskey\Ceremony\PasskeyUserEntity;
use Marko\AuthenticationPasskey\Config\PasskeyConfig;
use Marko\AuthenticationPasskey\Contracts\PasskeyCredentialRepositoryInterface;
use Marko\AuthenticationPasskey\Contracts\PasskeyVerifierInterface;
use Marko\AuthenticationPasskey\Entity\PasskeyCredential;
use Marko\AuthenticationPasskey\Event\PasskeyFailureReason;
use Marko\AuthenticationPasskey\Event\PasskeyLoginFailedEvent;
use Marko\AuthenticationPasskey\Exceptions\PasskeyAuthenticationFailedException;
use Marko\AuthenticationPasskey\Exceptions\PasskeyConfigurationException;
use Marko\AuthenticationPasskey\Exceptions\PasskeyVerificationException;
use Marko\AuthenticationPasskey\Exceptions\TooManyPasskeyAttemptsException;
use Marko\AuthenticationPasskey\Guard\PasskeyGuard;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\RateLimiter\Contracts\RateLimiterInterface;
use Marko\RateLimiter\Contracts\RateLimitKeyResolverInterface;
use Psr\Clock\ClockInterface;
use Random\RandomException;

/**
 * Signs users in with a passkey in two steps: requestOptions() issues a
 * challenge for the browser's navigator.credentials.get(), and authenticate()
 * verifies the browser's response and logs the passkey's owner in.
 *
 * A passkey sign-in is already multi-factor (possession of the authenticator
 * plus user verification by PIN or biometric, which is always required), so
 * authenticate() logs in with GuardInterface::login() directly: it never
 * goes through attempt() and so never triggers the guard's login challenges
 * (authentication.guards.<name>.challenges, such as two-factor codes), and it
 * cancels a challenge left pending by an abandoned password login. It also
 * counts as a password confirmation for
 * RequirePasswordConfirmationMiddleware.
 */
readonly class PasskeyAuthentication
{
    public function __construct(
        private PasskeyGuard $passkeyGuard,
        private PasskeyVerifierInterface $passkeyVerifier,
        private PasskeyCredentialRepositoryInterface $passkeyCredentialRepository,
        private PasskeyChallengeStore $passkeyChallengeStore,
        private ClientDataVerifier $clientDataVerifier,
        private PasswordConfirmationInterface $passwordConfirmation,
        private RateLimiterInterface $rateLimiter,
        private RateLimitKeyResolverInterface $rateLimitKeyResolver,
        private CurrentRequest $currentRequest,
        private EventDispatcherInterface $eventDispatcher,
        private PasskeyConfig $config,
        private ClockInterface $clock,
    ) {}

    /**
     * Request options as a JSON-ready array. The challenge is kept in the
     * session for authenticate() or confirm().
     *
     * Without a user the browser offers every passkey it holds for this site
     * (a discoverable, passwordless sign-in). With a user (a username-first
     * form, or confirm() for the signed-in user) only their passkeys are allowed.
     *
     * @return array<string, mixed>
     * @throws PasskeyConfigurationException|ConfigNotFoundException|RandomException
     */
    public function requestOptions(
        ?AuthenticatableInterface $user = null,
    ): array {
        $allowCredentials = $user === null
            ? []
            : $this->passkeyCredentialRepository->forUser(
                $this->passkeyGuard->providerName(),
                $user->getAuthIdentifier(),
            );

        return $this->passkeyVerifier->requestOptions(
            $this->passkeyChallengeStore->issue(PasskeyChallengeStore::AUTHENTICATION),
            $allowCredentials,
        );
    }

    /**
     * Verify the browser's response to the request options and log the
     * passkey's owner in. Afterwards the session counts as password-confirmed.
     *
     * @param array<mixed> $credential The PublicKeyCredential JSON from the browser
     * @throws PasskeyAuthenticationFailedException|TooManyPasskeyAttemptsException|PasskeyConfigurationException|AuthException|ConfigNotFoundException
     */
    public function authenticate(
        array $credential,
    ): AuthenticatableInterface {
        $guard = $this->passkeyGuard->guard();
        $throttleKey = $this->throttle();

        [$passkey, $user] = $this->verify($credential, null);
        $this->touch($passkey);

        // A password login abandoned at its challenge (e.g. a two-factor code) is superseded:
        // the passkey already proves both factors, so drop the pending challenge rather than
        // leaving it in the session, and log in directly instead of through attempt().
        if ($guard instanceof ChallengeableGuardInterface && $guard->hasPendingChallenge()) {
            $guard->cancelChallenge();
        }

        $guard->login($user);
        // After login(): the LoginEvent observer clears any earlier confirmation stamp.
        $this->passwordConfirmation->markConfirmed();
        $this->rateLimiter->clear($throttleKey);

        return $user;
    }

    /**
     * Verify the signed-in user's response to the request options and mark
     * the session as password-confirmed, without logging in again. This is
     * how a user without a password reaches routes behind
     * RequirePasswordConfirmationMiddleware.
     *
     * @param array<mixed> $credential The PublicKeyCredential JSON from the browser
     * @throws PasskeyAuthenticationFailedException|TooManyPasskeyAttemptsException|UnauthenticatedException|PasskeyConfigurationException|AuthException|ConfigNotFoundException
     */
    public function confirm(
        array $credential,
    ): void {
        $user = $this->passkeyGuard->user();
        $throttleKey = $this->throttle();

        [$passkey] = $this->verify($credential, $user);
        $this->touch($passkey);

        $this->passwordConfirmation->markConfirmed();
        $this->rateLimiter->clear($throttleKey);
    }

    /**
     * Count this attempt for the client and stop it when the client is locked out.
     *
     * @return string The throttle key
     * @throws TooManyPasskeyAttemptsException|PasskeyConfigurationException
     */
    private function throttle(): string
    {
        $key = 'passkey:' . hash('xxh128', $this->config->guard() . "\0" . $this->client());
        $attempt = $this->rateLimiter->attempt(
            $key,
            $this->config->throttleMaxAttempts(),
            $this->config->throttleDecaySeconds(),
        );

        if (!$attempt->allowed()) {
            throw TooManyPasskeyAttemptsException::lockedOut(
                $attempt->retryAfter() ?? $this->config->throttleDecaySeconds(),
            );
        }

        return $key;
    }

    /**
     * @param array<mixed> $credential
     * @param AuthenticatableInterface|null $expectedUser The user the passkey must belong to, or null for any user
     * @return array{PasskeyCredential, AuthenticatableInterface}
     * @throws PasskeyAuthenticationFailedException|PasskeyConfigurationException|AuthException|ConfigNotFoundException
     */
    private function verify(
        array $credential,
        ?AuthenticatableInterface $expectedUser,
    ): array {
        $credentialId = is_string($credential['rawId'] ?? null) ? $credential['rawId'] : null;

        try {
            $challenge = $this->passkeyChallengeStore->consume(PasskeyChallengeStore::AUTHENTICATION);
            $response = AssertionResponse::fromArray($credential);
            $this->clientDataVerifier->verify($response->clientData, ClientDataVerifier::GET, $challenge);

            $passkey = $this->passkeyCredentialRepository->findByCredentialId($response->credentialId);

            if ($passkey === null || $passkey->provider !== $this->passkeyGuard->providerName()) {
                throw PasskeyVerificationException::because(
                    PasskeyFailureReason::UnknownCredential,
                    'no passkey with this credential id is registered for the guard\'s user provider',
                );
            }

            if ($expectedUser !== null && !$this->passkeyGuard->owns($expectedUser, $passkey)) {
                throw PasskeyVerificationException::because(
                    PasskeyFailureReason::UnknownCredential,
                    'the passkey belongs to another user than the signed-in one',
                );
            }

            $expectedHandle = PasskeyUserEntity::handleFor($passkey->provider, $passkey->userId);

            if ($response->userHandle !== null && !hash_equals($expectedHandle, $response->userHandle)) {
                throw PasskeyVerificationException::because(
                    PasskeyFailureReason::UserHandleMismatch,
                    'the response\'s user handle does not belong to the passkey\'s owner',
                );
            }

            $verified = $this->passkeyVerifier->verifyAssertion($response, $passkey, $challenge);

            // Authenticators that keep no counter always report 0 (WebAuthn §6.1.1); any other counter must grow.
            if (($verified->signCount !== 0 || $passkey->signCount !== 0) && $verified->signCount <= $passkey->signCount) {
                throw PasskeyVerificationException::because(
                    PasskeyFailureReason::SignCountRegressed,
                    "the signature counter went from $passkey->signCount to $verified->signCount; the authenticator may have been cloned",
                );
            }

            $user = $this->passkeyGuard->provider()->retrieveById($passkey->userId);

            if ($user === null) {
                throw PasskeyVerificationException::because(
                    PasskeyFailureReason::UnknownUser,
                    "user '$passkey->userId' of provider '$passkey->provider' no longer exists",
                );
            }
        } catch (PasskeyVerificationException $exception) {
            $this->eventDispatcher->dispatch(new PasskeyLoginFailedEvent(
                guard: $this->config->guard(),
                reason: $exception->getReason(),
                credentialId: $credentialId,
                ipAddress: $this->currentRequest->get()?->ip(),
            ));

            throw PasskeyAuthenticationFailedException::fromVerification($exception);
        }

        $passkey->signCount = $verified->signCount;

        return [$passkey, $user];
    }

    /**
     * Store the new signature counter and the time of use.
     */
    private function touch(
        PasskeyCredential $passkey,
    ): void {
        $passkey->lastUsedAt = $this->clock->now();
        $this->passkeyCredentialRepository->save($passkey);
    }

    private function client(): string
    {
        $request = $this->currentRequest->get();

        if ($request === null || $request->ip() === null || $request->ip() === '') {
            return '';
        }

        return $this->rateLimitKeyResolver->resolve($request);
    }
}
