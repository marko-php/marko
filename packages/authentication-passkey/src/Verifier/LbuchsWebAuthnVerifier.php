<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Verifier;

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use Marko\AuthenticationPasskey\Ceremony\AssertionResponse;
use Marko\AuthenticationPasskey\Ceremony\AttestationResponse;
use Marko\AuthenticationPasskey\Ceremony\Base64Url;
use Marko\AuthenticationPasskey\Ceremony\PasskeyUserEntity;
use Marko\AuthenticationPasskey\Ceremony\VerifiedAssertion;
use Marko\AuthenticationPasskey\Ceremony\VerifiedAttestation;
use Marko\AuthenticationPasskey\Config\PasskeyConfig;
use Marko\AuthenticationPasskey\Contracts\PasskeyVerifierInterface;
use Marko\AuthenticationPasskey\Entity\PasskeyCredential;
use Marko\AuthenticationPasskey\Event\PasskeyFailureReason;
use Marko\AuthenticationPasskey\Exceptions\PasskeyVerificationException;

/**
 * The default WebAuthn engine, built on lbuchs/webauthn: a dependency-free
 * library that verifies the none, packed, fido-u2f, tpm, apple, android-key
 * and android-safetynet attestation formats and ES256, RS256 and EdDSA
 * signatures.
 *
 * Passkeys are requested with attestation "none", so no attestation root
 * certificates are configured and any authenticator model is accepted.
 */
readonly class LbuchsWebAuthnVerifier implements PasskeyVerifierInterface
{
    /** COSE algorithms offered, in order of preference: ES256, EdDSA, RS256. */
    private const array ALGORITHMS = [-7, -8, -257];

    public function __construct(
        private PasskeyConfig $config,
    ) {}

    public function creationOptions(
        PasskeyUserEntity $user,
        string $challenge,
        array $excludeCredentials,
    ): array {
        return [
            'rp' => [
                'id' => $this->config->relyingPartyId(),
                'name' => $this->config->relyingPartyName(),
            ],
            'user' => [
                'id' => Base64Url::encode($user->handle),
                'name' => $user->name,
                'displayName' => $user->displayName,
            ],
            'challenge' => Base64Url::encode($challenge),
            'pubKeyCredParams' => array_map(
                static fn (int $algorithm): array => ['type' => 'public-key', 'alg' => $algorithm],
                self::ALGORITHMS,
            ),
            'timeout' => $this->config->timeoutSeconds() * 1000,
            'excludeCredentials' => $this->descriptors($excludeCredentials),
            'authenticatorSelection' => [
                'residentKey' => 'required',
                'requireResidentKey' => true,
                'userVerification' => 'required',
            ],
            'attestation' => 'none',
        ];
    }

    public function verifyAttestation(
        AttestationResponse $response,
        string $challenge,
    ): VerifiedAttestation {
        $webAuthn = $this->webAuthn();

        try {
            $data = $webAuthn->processCreate(
                $response->clientData->json,
                $response->attestationObject,
                $challenge,
                true,
                true,
            );
        } catch (WebAuthnException $exception) {
            throw $this->failed($exception);
        }

        $credentialId = Base64Url::encode((string) $data->credentialId);

        if (!hash_equals($response->credentialId, $credentialId)) {
            throw PasskeyVerificationException::because(
                PasskeyFailureReason::MalformedResponse,
                'the attested credential id does not match the response\'s rawId',
            );
        }

        return new VerifiedAttestation(
            credentialId: $credentialId,
            publicKey: (string) $data->credentialPublicKey,
            signCount: $webAuthn->getSignatureCounter() ?? 0,
        );
    }

    public function requestOptions(
        string $challenge,
        array $allowCredentials,
    ): array {
        return [
            'rpId' => $this->config->relyingPartyId(),
            'challenge' => Base64Url::encode($challenge),
            'timeout' => $this->config->timeoutSeconds() * 1000,
            'allowCredentials' => $this->descriptors($allowCredentials),
            'userVerification' => 'required',
        ];
    }

    public function verifyAssertion(
        AssertionResponse $response,
        PasskeyCredential $credential,
        string $challenge,
    ): VerifiedAssertion {
        $webAuthn = $this->webAuthn();

        try {
            $webAuthn->processGet(
                $response->clientData->json,
                $response->authenticatorData,
                $response->signature,
                $credential->publicKey,
                $challenge,
                null,
                true,
                true,
            );
        } catch (WebAuthnException $exception) {
            throw $this->failed($exception);
        }

        return new VerifiedAssertion(
            signCount: $webAuthn->getSignatureCounter() ?? 0,
        );
    }

    /**
     * A fresh engine per ceremony: lbuchs keeps the last signature counter on the instance.
     */
    private function webAuthn(): WebAuthn
    {
        return new WebAuthn(
            $this->config->relyingPartyName(),
            $this->config->relyingPartyId(),
        );
    }

    /**
     * @param list<PasskeyCredential> $credentials
     * @return list<array<string, mixed>>
     */
    private function descriptors(
        array $credentials,
    ): array {
        return array_map(
            static fn (PasskeyCredential $credential): array => array_filter([
                'type' => 'public-key',
                'id' => $credential->credentialId,
                'transports' => $credential->transports,
            ], static fn (mixed $value): bool => $value !== []),
            $credentials,
        );
    }

    private function failed(
        WebAuthnException $exception,
    ): PasskeyVerificationException {
        $reason = match ($exception->getCode()) {
            WebAuthnException::INVALID_CHALLENGE => PasskeyFailureReason::ChallengeMismatch,
            WebAuthnException::INVALID_ORIGIN => PasskeyFailureReason::OriginMismatch,
            WebAuthnException::INVALID_RELYING_PARTY => PasskeyFailureReason::RelyingPartyMismatch,
            WebAuthnException::USER_PRESENT, WebAuthnException::USER_VERIFICATED => PasskeyFailureReason::UserNotVerified,
            WebAuthnException::INVALID_SIGNATURE,
            WebAuthnException::INVALID_PUBLIC_KEY,
            WebAuthnException::CERTIFICATE_NOT_TRUSTED,
            WebAuthnException::ANDROID_NOT_TRUSTED => PasskeyFailureReason::InvalidSignature,
            default => PasskeyFailureReason::MalformedResponse,
        };

        return PasskeyVerificationException::because($reason, $exception->getMessage(), $exception);
    }
}
