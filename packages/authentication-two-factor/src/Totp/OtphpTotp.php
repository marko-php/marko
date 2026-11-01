<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor\Totp;

use Marko\AuthenticationTwoFactor\Contracts\TotpInterface;
use OTPHP\TOTP;
use Psr\Clock\ClockInterface;

/**
 * RFC 6238 TOTP backed by spomky-labs/otphp.
 *
 * otphp computes the codes; this class adds the ±1 step window and replay
 * protection, and reports which step a code matched so it can be stored.
 */
readonly class OtphpTotp implements TotpInterface
{
    public const int PERIOD = 30;

    public const int WINDOW = 1;

    /** 160-bit secrets, the size RFC 4226 recommends. */
    private const int SECRET_BYTES = 20;

    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function generateSecret(): string
    {
        return TOTP::generate($this->clock, self::SECRET_BYTES)->getSecret();
    }

    public function verify(
        string $secret,
        string $code,
        ?int $lastUsedStep,
    ): ?int {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return null;
        }

        $totp = TOTP::createFromSecret($secret, $this->clock);
        $currentStep = intdiv($this->clock->now()->getTimestamp(), self::PERIOD);

        for ($step = $currentStep - self::WINDOW; $step <= $currentStep + self::WINDOW; $step++) {
            if ($step < 0 || ($lastUsedStep !== null && $step <= $lastUsedStep)) {
                continue;
            }

            if (hash_equals($totp->at($step * self::PERIOD), $code)) {
                return $step;
            }
        }

        return null;
    }

    public function provisioningUri(
        string $secret,
        string $label,
        string $issuer,
    ): string {
        $totp = TOTP::createFromSecret($secret, $this->clock);
        $totp->setLabel($label);
        $totp->setIssuer($issuer);

        return $totp->getProvisioningUri();
    }
}
