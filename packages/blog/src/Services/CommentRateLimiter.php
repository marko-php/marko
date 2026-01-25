<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

use Marko\Blog\Config\BlogConfigInterface;
use Marko\Cache\Contracts\CacheInterface;

/**
 * Rate limiter for comment submissions using cache storage.
 *
 * Uses hashed cache keys for IP addresses for GDPR compliance.
 * Relies on cache TTL for automatic cleanup.
 */
class CommentRateLimiter implements CommentRateLimiterInterface
{
    private const string CACHE_PREFIX_IP = 'comment_rate_ip_';
    private const string CACHE_PREFIX_EMAIL = 'comment_rate_email_';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly BlogConfigInterface $config,
    ) {}

    public function isAllowed(
        string $ipAddress,
        ?string $email = null,
    ): bool {
        $ipKey = $this->getIpCacheKey($ipAddress);

        if ($this->cache->has($ipKey)) {
            return false;
        }

        if ($email !== null) {
            $emailKey = $this->getEmailCacheKey($email);

            if ($this->cache->has($emailKey)) {
                return false;
            }
        }

        return true;
    }

    public function recordSubmission(
        string $ipAddress,
        ?string $email = null,
    ): void {
        $ttl = $this->config->getCommentRateLimitSeconds();
        $timestamp = time();

        $ipKey = $this->getIpCacheKey($ipAddress);
        $this->cache->set($ipKey, $timestamp, $ttl);

        if ($email !== null) {
            $emailKey = $this->getEmailCacheKey($email);
            $this->cache->set($emailKey, $timestamp, $ttl);
        }
    }

    public function getSecondsRemaining(
        string $ipAddress,
        ?string $email = null,
    ): int {
        $ttl = $this->config->getCommentRateLimitSeconds();
        $maxRemaining = 0;

        $ipKey = $this->getIpCacheKey($ipAddress);
        $ipTimestamp = $this->cache->get($ipKey);

        if ($ipTimestamp !== null) {
            $elapsed = time() - (int) $ipTimestamp;
            $remaining = $ttl - $elapsed;
            $maxRemaining = max($maxRemaining, $remaining);
        }

        if ($email !== null) {
            $emailKey = $this->getEmailCacheKey($email);
            $emailTimestamp = $this->cache->get($emailKey);

            if ($emailTimestamp !== null) {
                $elapsed = time() - (int) $emailTimestamp;
                $remaining = $ttl - $elapsed;
                $maxRemaining = max($maxRemaining, $remaining);
            }
        }

        return max(0, $maxRemaining);
    }

    private function getIpCacheKey(
        string $ipAddress,
    ): string {
        return self::CACHE_PREFIX_IP . hash('sha256', $ipAddress);
    }

    private function getEmailCacheKey(
        string $email,
    ): string {
        return self::CACHE_PREFIX_EMAIL . hash('sha256', strtolower($email));
    }
}
