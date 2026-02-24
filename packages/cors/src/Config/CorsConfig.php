<?php

declare(strict_types=1);

namespace Marko\Cors\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

readonly class CorsConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * @return array<string>
     * @throws ConfigNotFoundException
     */
    public function allowedOrigins(): array
    {
        return $this->config->getArray('cors.allowed_origins');
    }

    /**
     * @return array<string>
     * @throws ConfigNotFoundException
     */
    public function allowedMethods(): array
    {
        return $this->config->getArray('cors.allowed_methods');
    }

    /**
     * @return array<string>
     * @throws ConfigNotFoundException
     */
    public function allowedHeaders(): array
    {
        return $this->config->getArray('cors.allowed_headers');
    }

    /**
     * @return array<string>
     * @throws ConfigNotFoundException
     */
    public function exposeHeaders(): array
    {
        return $this->config->getArray('cors.expose_headers');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function supportsCredentials(): bool
    {
        return $this->config->getBool('cors.supports_credentials');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function maxAge(): int
    {
        return $this->config->getInt('cors.max_age');
    }
}
