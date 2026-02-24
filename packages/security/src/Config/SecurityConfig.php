<?php

declare(strict_types=1);

namespace Marko\Security\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class SecurityConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function csrfSessionKey(): string
    {
        return $this->config->getString('security.csrf.session_key');
    }

    /**
     * @return array<int, string>
     */
    public function corsAllowedOrigins(): array
    {
        return $this->config->getArray('security.cors.allowed_origins');
    }

    /**
     * @return array<int, string>
     */
    public function corsAllowedMethods(): array
    {
        return $this->config->getArray('security.cors.allowed_methods');
    }

    /**
     * @return array<int, string>
     */
    public function corsAllowedHeaders(): array
    {
        return $this->config->getArray('security.cors.allowed_headers');
    }

    public function corsMaxAge(): int
    {
        return $this->config->getInt('security.cors.max_age');
    }

    public function headerXContentTypeOptions(): string
    {
        return $this->config->getString('security.headers.x_content_type_options');
    }

    public function headerXFrameOptions(): string
    {
        return $this->config->getString('security.headers.x_frame_options');
    }

    public function headerXXssProtection(): string
    {
        return $this->config->getString('security.headers.x_xss_protection');
    }

    public function headerStrictTransportSecurity(): string
    {
        return $this->config->getString('security.headers.strict_transport_security');
    }

    public function headerReferrerPolicy(): string
    {
        return $this->config->getString('security.headers.referrer_policy');
    }

    public function headerContentSecurityPolicy(): string
    {
        return $this->config->getString('security.headers.content_security_policy');
    }
}
