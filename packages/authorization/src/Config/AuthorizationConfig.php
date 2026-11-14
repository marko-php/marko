<?php

declare(strict_types=1);

namespace Marko\Authorization\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigException;
use Marko\Config\Exceptions\ConfigNotFoundException;

readonly class AuthorizationConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * Get the default guard name for authorization.
     *
     * Returns null (the shipped default) to use the auth system's default guard.
     *
     * @throws ConfigException|ConfigNotFoundException
     */
    public function defaultGuard(): ?string
    {
        $guard = $this->config->get('authorization.default_guard');

        if (
            $guard === null
            || $guard === ''
        ) {
            return null;
        }

        if (!is_string($guard)) {
            throw new ConfigException(
                message: 'Configuration key "authorization.default_guard" must be a string or null',
                context: sprintf('Got %s', get_debug_type($guard)),
                suggestion: 'Set it to a guard name from your authentication config, or null to use the authentication default guard.',
            );
        }

        return $guard;
    }
}
