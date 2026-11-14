<?php

declare(strict_types=1);

namespace Marko\RateLimiter\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\RateLimiter\Exceptions\ClientIpException;

readonly class RateLimiterConfig
{
    public function __construct(
        private ConfigRepositoryInterface $configRepository,
    ) {}

    /**
     * @throws ConfigNotFoundException
     */
    public function defaultMaxAttempts(): int
    {
        return $this->configRepository->getInt('ratelimiter.default_max_attempts');
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function defaultDecaySeconds(): int
    {
        return $this->configRepository->getInt('ratelimiter.default_decay_seconds');
    }

    /**
     * The network prefix length IPv6 clients are bucketed by (1-128).
     *
     * @throws ClientIpException|ConfigNotFoundException
     */
    public function ipv6Prefix(): int
    {
        $prefix = $this->configRepository->getInt('ratelimiter.ipv6_prefix');

        if (
            $prefix < 1
            || $prefix > 128
        ) {
            throw ClientIpException::invalidIpv6Prefix($prefix);
        }

        return $prefix;
    }
}
