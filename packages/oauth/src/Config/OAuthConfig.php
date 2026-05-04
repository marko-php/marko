<?php

declare(strict_types=1);

namespace Marko\OAuth\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class OAuthConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function routesEnabled(): bool
    {
        return $this->config->getBool('oauth.routes.enabled');
    }

    public function routePrefix(): string
    {
        return rtrim($this->config->getString('oauth.routes.prefix'), '/');
    }

    public function managementRoutesEnabled(): bool
    {
        return $this->config->getBool('oauth.routes.management');
    }

    public function privateKeyPath(): string
    {
        return $this->config->getString('oauth.keys.private');
    }

    public function publicKeyPath(): string
    {
        return $this->config->getString('oauth.keys.public');
    }

    public function keyPassphrase(): ?string
    {
        $passphrase = $this->config->get('oauth.keys.passphrase');

        return is_string($passphrase) && $passphrase !== '' ? $passphrase : null;
    }

    public function accessTokenTtl(): string
    {
        return $this->config->getString('oauth.tokens.access_token_ttl');
    }

    public function refreshTokenTtl(): string
    {
        return $this->config->getString('oauth.tokens.refresh_token_ttl');
    }

    public function authCodeTtl(): string
    {
        return $this->config->getString('oauth.tokens.auth_code_ttl');
    }

    public function checkRevocation(): bool
    {
        return $this->config->getBool('oauth.tokens.check_revocation');
    }

    public function rotateRefreshTokens(): bool
    {
        return $this->config->getBool('oauth.refresh_tokens.rotate');
    }

    public function detectRefreshTokenReuse(): bool
    {
        return $this->config->getBool('oauth.refresh_tokens.reuse_detection');
    }

    public function rememberConsent(): bool
    {
        return $this->config->getBool('oauth.consent.remember');
    }

    public function consentTtl(): string
    {
        return $this->config->getString('oauth.consent.ttl');
    }

    /**
     * @return array<string, string>
     */
    public function scopes(): array
    {
        return $this->config->getArray('oauth.scopes');
    }

    /**
     * @return array<string>
     */
    public function defaultScopes(): array
    {
        return $this->config->getArray('oauth.default_scopes');
    }
}
