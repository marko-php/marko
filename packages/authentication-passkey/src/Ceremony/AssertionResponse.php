<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Ceremony;

use Marko\AuthenticationPasskey\Exceptions\PasskeyVerificationException;

/**
 * The browser's answer to navigator.credentials.get(): a signature over the
 * challenge by an existing credential, decoded from the credential's JSON form.
 */
readonly class AssertionResponse
{
    public function __construct(
        public string $credentialId,
        public ClientData $clientData,
        public string $authenticatorData,
        public string $signature,
        public ?string $userHandle,
    ) {}

    /**
     * @param array<mixed> $credential The PublicKeyCredential JSON (credential.toJSON())
     * @throws PasskeyVerificationException
     */
    public static function fromArray(
        array $credential,
    ): self {
        $payload = CredentialPayload::fromArray($credential);
        $userHandle = $payload->optionalBinary('userHandle');

        return new self(
            credentialId: $payload->credentialId,
            clientData: $payload->clientData(),
            authenticatorData: $payload->binary('authenticatorData'),
            signature: $payload->binary('signature'),
            userHandle: $userHandle === '' ? null : $userHandle,
        );
    }
}
