<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor\Config;

use Marko\AuthenticationTwoFactor\Exceptions\TwoFactorException;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

readonly class TwoFactorConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * The issuer written into provisioning URIs.
     *
     * @throws TwoFactorException|ConfigNotFoundException
     */
    public function issuer(): string
    {
        $issuer = $this->config->get('authentication-two-factor.issuer');

        if (!is_string($issuer) || trim($issuer) === '') {
            throw TwoFactorException::missingIssuer();
        }

        return $issuer;
    }

    /**
     * Where a user without confirmed two-factor is sent to set it up.
     *
     * @throws ConfigNotFoundException
     */
    public function setupRoute(): string
    {
        return $this->config->getString('authentication-two-factor.setup_route');
    }

    /**
     * @throws TwoFactorException|ConfigNotFoundException
     */
    public function recoveryCodeCount(): int
    {
        return $this->positive('authentication-two-factor.recovery_code_count');
    }

    /**
     * @throws TwoFactorException|ConfigNotFoundException
     */
    public function throttleMaxAttempts(): int
    {
        return $this->positive('authentication-two-factor.throttle.max_attempts');
    }

    /**
     * @throws TwoFactorException|ConfigNotFoundException
     */
    public function throttleDecaySeconds(): int
    {
        return $this->positive('authentication-two-factor.throttle.decay_seconds');
    }

    /**
     * @throws TwoFactorException|ConfigNotFoundException
     */
    private function positive(
        string $key,
    ): int {
        $value = $this->config->getInt($key);

        if ($value < 1) {
            throw TwoFactorException::invalidSetting($key, $value);
        }

        return $value;
    }
}
