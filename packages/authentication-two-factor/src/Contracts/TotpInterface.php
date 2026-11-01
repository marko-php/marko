<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor\Contracts;

/**
 * Time-based one-time passwords (RFC 6238): 6 digits, 30-second steps.
 */
interface TotpInterface
{
    /**
     * A new random base32 secret.
     */
    public function generateSecret(): string;

    /**
     * Check $code against the current time step and one step either side.
     *
     * Steps at or before $lastUsedStep are rejected (replay protection).
     *
     * @return int|null The accepted time step, to store as the new last used step, or null when the code is wrong
     */
    public function verify(
        string $secret,
        string $code,
        ?int $lastUsedStep,
    ): ?int;

    /**
     * The otpauth:// URI an authenticator app scans (render it as a QR code).
     *
     * @param string $label The account name shown in the app, e.g. the user's email
     * @param string $issuer The app name shown in the app
     */
    public function provisioningUri(
        string $secret,
        string $label,
        string $issuer,
    ): string;
}
