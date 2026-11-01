<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Service;

use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\AuthenticationPasskey\Ceremony\AttestationResponse;
use Marko\AuthenticationPasskey\Ceremony\ClientDataVerifier;
use Marko\AuthenticationPasskey\Ceremony\PasskeyChallengeStore;
use Marko\AuthenticationPasskey\Ceremony\PasskeyUserEntity;
use Marko\AuthenticationPasskey\Contracts\PasskeyCredentialRepositoryInterface;
use Marko\AuthenticationPasskey\Contracts\PasskeyVerifierInterface;
use Marko\AuthenticationPasskey\Entity\PasskeyCredential;
use Marko\AuthenticationPasskey\Event\PasskeyFailureReason;
use Marko\AuthenticationPasskey\Event\PasskeyRegisteredEvent;
use Marko\AuthenticationPasskey\Exceptions\InvalidPasskeyNameException;
use Marko\AuthenticationPasskey\Exceptions\PasskeyConfigurationException;
use Marko\AuthenticationPasskey\Exceptions\PasskeyVerificationException;
use Marko\AuthenticationPasskey\Guard\PasskeyGuard;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Event\EventDispatcherInterface;
use Psr\Clock\ClockInterface;
use Random\RandomException;

/**
 * Adds a passkey to the signed-in user's account in two steps: creationOptions()
 * issues a challenge for the browser's navigator.credentials.create(), and
 * register() verifies the browser's response and stores the passkey.
 */
readonly class PasskeyRegistration
{
    public function __construct(
        private PasskeyGuard $passkeyGuard,
        private PasskeyVerifierInterface $passkeyVerifier,
        private PasskeyCredentialRepositoryInterface $passkeyCredentialRepository,
        private PasskeyChallengeStore $passkeyChallengeStore,
        private ClientDataVerifier $clientDataVerifier,
        private EventDispatcherInterface $eventDispatcher,
        private ClockInterface $clock,
    ) {}

    /**
     * Creation options for the signed-in user, as JSON-ready array. The
     * challenge is kept in the session for register().
     *
     * @param string $userName The account name the authenticator shows, e.g. the email address
     * @param string $displayName The user's name, e.g. "Ada Lovelace"
     * @return array<string, mixed>
     * @throws UnauthenticatedException|PasskeyConfigurationException|AuthException|ConfigNotFoundException|RandomException
     */
    public function creationOptions(
        string $userName,
        string $displayName,
    ): array {
        $user = $this->passkeyGuard->user();
        $providerName = $this->passkeyGuard->providerName();

        $challenge = $this->passkeyChallengeStore->issue(
            PasskeyChallengeStore::REGISTRATION,
            $this->passkeyGuard->ownerKey($user),
        );

        return $this->passkeyVerifier->creationOptions(
            new PasskeyUserEntity(
                handle: PasskeyUserEntity::handleFor($providerName, $user->getAuthIdentifier()),
                name: $userName,
                displayName: $displayName,
            ),
            $challenge,
            $this->passkeyCredentialRepository->forUser($providerName, $user->getAuthIdentifier()),
        );
    }

    /**
     * Verify the browser's response to the creation options and store the passkey.
     *
     * @param array<mixed> $credential The PublicKeyCredential JSON from the browser
     * @param string $name A label for the passkey, e.g. "MacBook Touch ID"
     * @throws PasskeyVerificationException|InvalidPasskeyNameException|UnauthenticatedException|PasskeyConfigurationException|AuthException|ConfigNotFoundException
     */
    public function register(
        array $credential,
        string $name,
    ): PasskeyCredential {
        $name = PasskeyName::normalise($name);
        $user = $this->passkeyGuard->user();

        $challenge = $this->passkeyChallengeStore->consume(
            PasskeyChallengeStore::REGISTRATION,
            $this->passkeyGuard->ownerKey($user),
        );
        $response = AttestationResponse::fromArray($credential);
        $this->clientDataVerifier->verify($response->clientData, ClientDataVerifier::CREATE, $challenge);

        if ($this->passkeyCredentialRepository->findByCredentialId($response->credentialId) !== null) {
            throw PasskeyVerificationException::because(
                PasskeyFailureReason::CredentialAlreadyRegistered,
                'a passkey with this credential id is already registered',
            );
        }

        $verified = $this->passkeyVerifier->verifyAttestation($response, $challenge);

        $passkey = new PasskeyCredential();
        $passkey->credentialId = $verified->credentialId;
        $passkey->provider = $this->passkeyGuard->providerName();
        $passkey->userId = (string) $user->getAuthIdentifier();
        $passkey->name = $name;
        $passkey->publicKey = $verified->publicKey;
        $passkey->signCount = $verified->signCount;
        $passkey->transports = $response->transports;
        $passkey->createdAt = $this->clock->now();

        $this->passkeyCredentialRepository->save($passkey);

        $this->eventDispatcher->dispatch(new PasskeyRegisteredEvent(
            user: $user,
            credential: $passkey,
            guard: $this->passkeyGuard->guard()->getName(),
        ));

        return $passkey;
    }
}
