<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Contracts;

use Marko\AuthenticationPasskey\Ceremony\AssertionResponse;
use Marko\AuthenticationPasskey\Ceremony\AttestationResponse;
use Marko\AuthenticationPasskey\Ceremony\PasskeyUserEntity;
use Marko\AuthenticationPasskey\Ceremony\VerifiedAssertion;
use Marko\AuthenticationPasskey\Ceremony\VerifiedAttestation;
use Marko\AuthenticationPasskey\Entity\PasskeyCredential;
use Marko\AuthenticationPasskey\Exceptions\PasskeyConfigurationException;
use Marko\AuthenticationPasskey\Exceptions\PasskeyVerificationException;

/**
 * The WebAuthn engine: the only code that touches a WebAuthn library.
 *
 * Everything around it lives in the package and is shared by every engine:
 * challenges are issued, stored and consumed by PasskeyChallengeStore; the
 * client data's type, challenge and origin are checked by ClientDataVerifier;
 * the signature counter, the user handle and throttling are checked by
 * PasskeyAuthentication. An engine builds the options JSON and does the
 * cryptographic verification: attestation parsing, the relying party id
 * hash, the user presence and verification flags, and signatures.
 *
 * Passkeys are always created and used with user verification required.
 */
interface PasskeyVerifierInterface
{
    /**
     * PublicKeyCredentialCreationOptions in their JSON form, ready for
     * PublicKeyCredential.parseCreationOptionsFromJSON() in the browser.
     *
     * @param list<PasskeyCredential> $excludeCredentials The user's passkeys, so an authenticator is not registered twice
     * @return array<string, mixed>
     * @throws PasskeyConfigurationException
     */
    public function creationOptions(
        PasskeyUserEntity $user,
        string $challenge,
        array $excludeCredentials,
    ): array;

    /**
     * Verify a registration response against the challenge it must sign.
     *
     * @throws PasskeyVerificationException|PasskeyConfigurationException
     */
    public function verifyAttestation(
        AttestationResponse $response,
        string $challenge,
    ): VerifiedAttestation;

    /**
     * PublicKeyCredentialRequestOptions in their JSON form, ready for
     * PublicKeyCredential.parseRequestOptionsFromJSON() in the browser. An
     * empty $allowCredentials asks for a discoverable credential (passwordless).
     *
     * @param list<PasskeyCredential> $allowCredentials
     * @return array<string, mixed>
     * @throws PasskeyConfigurationException
     */
    public function requestOptions(
        string $challenge,
        array $allowCredentials,
    ): array;

    /**
     * Verify a sign-in response against the stored passkey and the challenge
     * it must sign. Does not judge the signature counter; the caller does.
     *
     * @throws PasskeyVerificationException|PasskeyConfigurationException
     */
    public function verifyAssertion(
        AssertionResponse $response,
        PasskeyCredential $credential,
        string $challenge,
    ): VerifiedAssertion;
}
