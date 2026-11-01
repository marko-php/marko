<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Ceremony;

/**
 * The user a new passkey is created for, as WebAuthn's creation options name
 * them. The handle is opaque bytes that identify the account to the
 * authenticator without revealing who it is (see handleFor()).
 */
readonly class PasskeyUserEntity
{
    public function __construct(
        public string $handle,
        public string $name,
        public string $displayName,
    ) {}

    /**
     * The user handle for a user of a provider: the SHA-256 of both, so it is
     * stable across passkeys, carries no personal data, and lets a
     * discoverable sign-in be checked against the stored passkey's owner.
     */
    public static function handleFor(
        string $providerName,
        int|string $userId,
    ): string {
        return hash('sha256', $providerName . "\0" . $userId, true);
    }
}
