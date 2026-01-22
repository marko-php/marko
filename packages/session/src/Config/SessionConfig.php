<?php

declare(strict_types=1);

namespace Marko\Session\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class SessionConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function driver(): string
    {
        return $this->config->getString('session.driver', 'file');
    }

    public function lifetime(): int
    {
        return $this->config->getInt('session.lifetime', 120);
    }

    public function expireOnClose(): bool
    {
        return $this->config->getBool('session.expire_on_close', false);
    }

    public function path(): string
    {
        return $this->config->getString('session.path', 'storage/sessions');
    }

    public function cookieName(): string
    {
        return $this->config->getString('session.cookie.name', 'marko_session');
    }

    public function cookiePath(): string
    {
        return $this->config->getString('session.cookie.path', '/');
    }

    public function cookieDomain(): ?string
    {
        $domain = $this->config->getString('session.cookie.domain', '');

        return $domain !== '' ? $domain : null;
    }

    public function cookieSecure(): bool
    {
        return $this->config->getBool('session.cookie.secure', true);
    }

    public function cookieHttpOnly(): bool
    {
        return $this->config->getBool('session.cookie.httponly', true);
    }

    public function cookieSameSite(): string
    {
        return $this->config->getString('session.cookie.samesite', 'lax');
    }

    public function gcProbability(): int
    {
        return $this->config->getInt('session.gc_probability', 2);
    }

    public function gcDivisor(): int
    {
        return $this->config->getInt('session.gc_divisor', 100);
    }
}
