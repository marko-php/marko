<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigException;
use Marko\Config\Exceptions\ConfigNotFoundException;

readonly class TokenConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * Default lifetime, in days, of a token created without an explicit expiry.
     * Null means tokens created without an expiry never expire.
     *
     * @throws ConfigException|ConfigNotFoundException
     */
    public function expirationDays(): ?int
    {
        $days = $this->config->get('authentication-token.token_expiration_days');

        if ($days === null) {
            return null;
        }

        if (
            !is_int($days)
            || $days < 1
        ) {
            throw new ConfigException(
                message: 'Configuration key "authentication-token.token_expiration_days" must be a positive integer or null',
                context: sprintf('Got %s', var_export($days, true)),
                suggestion: 'Set it to the number of days a new personal access token stays valid (e.g. 365), or null for tokens that never expire unless createToken() is given an expiresAt.',
            );
        }

        return $days;
    }
}
