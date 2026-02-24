<?php

declare(strict_types=1);

namespace Marko\Authorization\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class AuthorizationConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * Get the default guard name for authorization.
     *
     * Returns null to use the auth system's default guard.
     */
    public function defaultGuard(): ?string
    {
        $guard = $this->config->getString('authorization.default_guard');

        return $guard !== '' ? $guard : null;
    }
}
