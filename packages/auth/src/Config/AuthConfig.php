<?php

declare(strict_types=1);

namespace Marko\Auth\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class AuthConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function defaultGuard(): string
    {
        return $this->config->getString('auth.default.guard', 'session');
    }

    public function defaultProvider(): string
    {
        return $this->config->getString('auth.default.provider', 'users');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function guards(): array
    {
        return $this->config->getArray('auth.guards', []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function providers(): array
    {
        return $this->config->getArray('auth.providers', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function passwordConfig(): array
    {
        return $this->config->getArray('auth.password', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function rememberConfig(): array
    {
        return $this->config->getArray('auth.remember', []);
    }

    public function bcryptCost(): int
    {
        return $this->config->getInt('auth.password.bcrypt.cost', 12);
    }
}
