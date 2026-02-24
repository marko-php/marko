<?php

declare(strict_types=1);

namespace Marko\Authentication\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class AuthConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function defaultGuard(): string
    {
        return $this->config->getString('authentication.default.guard');
    }

    public function defaultProvider(): string
    {
        return $this->config->getString('authentication.default.provider');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function guards(): array
    {
        return $this->config->getArray('authentication.guards');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function providers(): array
    {
        return $this->config->getArray('authentication.providers');
    }

    /**
     * @return array<string, mixed>
     */
    public function passwordConfig(): array
    {
        return $this->config->getArray('authentication.password');
    }

    /**
     * @return array<string, mixed>
     */
    public function rememberConfig(): array
    {
        return $this->config->getArray('authentication.remember');
    }

    public function bcryptCost(): int
    {
        return $this->config->getInt('authentication.password.bcrypt.cost');
    }
}
