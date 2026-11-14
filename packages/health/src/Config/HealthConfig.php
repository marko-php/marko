<?php

declare(strict_types=1);

namespace Marko\Health\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Health\Exceptions\HealthException;

readonly class HealthConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * The secret that protects the endpoint, or null when it is public.
     *
     * @throws ConfigNotFoundException|HealthException
     */
    public function getSecret(): ?string
    {
        $secret = $this->config->get('health.secret');

        if (
            $secret === null
            || $secret === ''
        ) {
            return null;
        }

        if (!is_string($secret)) {
            throw HealthException::invalidSecret(get_debug_type($secret));
        }

        return $secret;
    }
}
