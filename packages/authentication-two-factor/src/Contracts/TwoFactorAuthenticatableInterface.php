<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor\Contracts;

use DateTimeImmutable;
use Marko\Authentication\AuthenticatableInterface;

/**
 * A user entity that can store a two-factor secret and recovery codes.
 *
 * The app implements it on its user entity and saves changes through its
 * UserPersisterInterface (marko/authentication). The secret must be reversible,
 * so store it encrypted at rest (e.g. marko/database's #[Encrypted] attribute).
 * Recovery codes are only ever given to this interface as SHA-256 hashes.
 */
interface TwoFactorAuthenticatableInterface extends AuthenticatableInterface
{
    /**
     * The base32 TOTP secret, or null when two-factor was never enabled (or was disabled).
     */
    public function getTwoFactorSecret(): ?string;

    public function setTwoFactorSecret(
        ?string $secret,
    ): void;

    /**
     * The SHA-256 hashes of the unused recovery codes.
     *
     * @return list<string>
     */
    public function getTwoFactorRecoveryCodes(): array;

    /**
     * @param list<string> $hashes SHA-256 hashes, never plain codes
     */
    public function setTwoFactorRecoveryCodes(
        array $hashes,
    ): void;

    /**
     * When the user confirmed the secret with a first code. Two-factor is only
     * active (and only challenges logins) once this is set.
     */
    public function getTwoFactorConfirmedAt(): ?DateTimeImmutable;

    public function setTwoFactorConfirmedAt(
        ?DateTimeImmutable $at,
    ): void;

    /**
     * The last TOTP time step accepted for this user. Codes for this step or an
     * earlier one are rejected, so a code can never be replayed.
     */
    public function getTwoFactorLastUsedStep(): ?int;

    public function setTwoFactorLastUsedStep(
        ?int $step,
    ): void;
}
