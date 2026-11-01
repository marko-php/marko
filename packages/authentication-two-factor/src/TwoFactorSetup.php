<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor;

/**
 * What enable() hands back to show the user once: the secret (for manual
 * entry), the otpauth:// URI (to render as a QR code) and the plain recovery
 * codes. Only hashes of the codes are stored, so they cannot be shown again.
 */
readonly class TwoFactorSetup
{
    /**
     * @param list<string> $recoveryCodes
     */
    public function __construct(
        public string $secret,
        public string $provisioningUri,
        public array $recoveryCodes,
    ) {}
}
