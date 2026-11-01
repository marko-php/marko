<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Ceremony;

use Marko\AuthenticationPasskey\Exceptions\PasskeyVerificationException;

/**
 * The browser's answer to navigator.credentials.create(): a new credential
 * and its attestation, decoded from the credential's JSON form.
 */
readonly class AttestationResponse
{
    /**
     * @param list<string> $transports
     */
    public function __construct(
        public string $credentialId,
        public ClientData $clientData,
        public string $attestationObject,
        public array $transports,
    ) {}

    /**
     * @param array<mixed> $credential The PublicKeyCredential JSON (credential.toJSON())
     * @throws PasskeyVerificationException
     */
    public static function fromArray(
        array $credential,
    ): self {
        $payload = CredentialPayload::fromArray($credential);

        return new self(
            credentialId: $payload->credentialId,
            clientData: $payload->clientData(),
            attestationObject: $payload->binary('attestationObject'),
            transports: $payload->transports(),
        );
    }
}
