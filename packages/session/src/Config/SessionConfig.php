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
        return $this->config->getString('session.driver');
    }

    public function lifetime(): int
    {
        return $this->config->getInt('session.lifetime');
    }

    public function expireOnClose(): bool
    {
        return $this->config->getBool('session.expire_on_close');
    }

    public function path(): string
    {
        return $this->config->getString('session.path');
    }

    public function cookieName(): string
    {
        return $this->config->getString('session.cookie.name');
    }

    public function cookiePath(): string
    {
        return $this->config->getString('session.cookie.path');
    }

    public function cookieDomain(): ?string
    {
        $domain = $this->config->getString('session.cookie.domain');

        return $domain !== '' ? $domain : null;
    }

    public function cookieSecure(): bool
    {
        return $this->config->getBool('session.cookie.secure');
    }

    public function cookieHttpOnly(): bool
    {
        return $this->config->getBool('session.cookie.httponly');
    }

    public function cookieSameSite(): string
    {
        return $this->config->getString('session.cookie.samesite');
    }

    public function gcProbability(): int
    {
        return $this->config->getInt('session.gc_probability');
    }

    public function gcDivisor(): int
    {
        return $this->config->getInt('session.gc_divisor');
    }
}
