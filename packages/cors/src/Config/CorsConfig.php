<?php

declare(strict_types=1);

namespace Marko\Cors\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class CorsConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * @return array<string>
     */
    public function allowedOrigins(): array
    {
        return $this->config->getArray('cors.allowed_origins');
    }

    /**
     * @return array<string>
     */
    public function allowedMethods(): array
    {
        return $this->config->getArray('cors.allowed_methods');
    }

    /**
     * @return array<string>
     */
    public function allowedHeaders(): array
    {
        return $this->config->getArray('cors.allowed_headers');
    }

    /**
     * @return array<string>
     */
    public function exposeHeaders(): array
    {
        return $this->config->getArray('cors.expose_headers');
    }

    public function supportsCredentials(): bool
    {
        return $this->config->getBool('cors.supports_credentials');
    }

    public function maxAge(): int
    {
        return $this->config->getInt('cors.max_age');
    }
}
