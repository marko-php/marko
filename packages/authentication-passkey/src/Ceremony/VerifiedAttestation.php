<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Ceremony;

/**
 * What a verified attestation yields: the credential to store.
 */
readonly class VerifiedAttestation
{
    /**
     * @param string $credentialId base64url credential id, equal to the response's rawId
     * @param string $publicKey The credential public key, PEM encoded
     * @param int $signCount The authenticator's signature counter (0 when it keeps none)
     */
    public function __construct(
        public string $credentialId,
        public string $publicKey,
        public int $signCount,
    ) {}
}
