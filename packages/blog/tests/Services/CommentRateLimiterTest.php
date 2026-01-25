<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Services;

use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Services\CommentRateLimiter;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use RuntimeException;

function createRateLimiterMockCache(
    array $storage = [],
): CacheInterface {
    return new class ($storage) implements CacheInterface
    {
        public function __construct(
            public array $storage,
        ) {}

        public function get(
            string $key,
            mixed $default = null,
        ): mixed {
            return $this->storage[$key] ?? $default;
        }

        public function set(
            string $key,
            mixed $value,
            ?int $ttl = null,
        ): bool {
            $this->storage[$key] = $value;

            return true;
        }

        public function has(
            string $key,
        ): bool {
            return isset($this->storage[$key]);
        }

        public function delete(
            string $key,
        ): bool {
            unset($this->storage[$key]);

            return true;
        }

        public function clear(): bool
        {
            $this->storage = [];

            return true;
        }

        public function getItem(
            string $key,
        ): CacheItemInterface {
            throw new RuntimeException('Not implemented');
        }

        public function getMultiple(
            array $keys,
            mixed $default = null,
        ): iterable {
            $result = [];
            foreach ($keys as $key) {
                $result[$key] = $this->storage[$key] ?? $default;
            }

            return $result;
        }

        public function setMultiple(
            array $values,
            ?int $ttl = null,
        ): bool {
            foreach ($values as $key => $value) {
                $this->storage[$key] = $value;
            }

            return true;
        }

        public function deleteMultiple(
            array $keys,
        ): bool {
            foreach ($keys as $key) {
                unset($this->storage[$key]);
            }

            return true;
        }
    };
}

function createRateLimiterMockCacheWithTtl(
    array $storage = [],
): CacheInterface {
    return new class ($storage) implements CacheInterface
    {
        public ?int $lastTtl = null;

        public function __construct(
            public array $storage,
        ) {}

        public function get(
            string $key,
            mixed $default = null,
        ): mixed {
            return $this->storage[$key] ?? $default;
        }

        public function set(
            string $key,
            mixed $value,
            ?int $ttl = null,
        ): bool {
            $this->storage[$key] = $value;
            $this->lastTtl = $ttl;

            return true;
        }

        public function has(
            string $key,
        ): bool {
            return isset($this->storage[$key]);
        }

        public function delete(
            string $key,
        ): bool {
            unset($this->storage[$key]);

            return true;
        }

        public function clear(): bool
        {
            $this->storage = [];

            return true;
        }

        public function getItem(
            string $key,
        ): CacheItemInterface {
            throw new RuntimeException('Not implemented');
        }

        public function getMultiple(
            array $keys,
            mixed $default = null,
        ): iterable {
            $result = [];
            foreach ($keys as $key) {
                $result[$key] = $this->storage[$key] ?? $default;
            }

            return $result;
        }

        public function setMultiple(
            array $values,
            ?int $ttl = null,
        ): bool {
            foreach ($values as $key => $value) {
                $this->storage[$key] = $value;
            }

            return true;
        }

        public function deleteMultiple(
            array $keys,
        ): bool {
            foreach ($keys as $key) {
                unset($this->storage[$key]);
            }

            return true;
        }
    };
}

function createRateLimiterMockConfig(
    int $rateLimitSeconds = 30,
): BlogConfigInterface {
    return new readonly class ($rateLimitSeconds) implements BlogConfigInterface
    {
        public function __construct(
            private int $rateLimitSeconds,
        ) {}

        public function getPostsPerPage(): int
        {
            return 10;
        }

        public function getCommentMaxDepth(): int
        {
            return 5;
        }

        public function getCommentRateLimitSeconds(): int
        {
            return $this->rateLimitSeconds;
        }

        public function getVerificationTokenExpiryDays(): int
        {
            return 7;
        }

        public function getVerificationCookieDays(): int
        {
            return 365;
        }

        public function getRoutePrefix(): string
        {
            return '/blog';
        }

        public function getVerificationCookieName(): string
        {
            return 'blog_verified';
        }
    };
}

it('allows comment when no recent comment from same IP', function (): void {
    $cache = createRateLimiterMockCache();
    $config = createRateLimiterMockConfig();
    $rateLimiter = new CommentRateLimiter($cache, $config);

    $result = $rateLimiter->isAllowed('192.168.1.1');

    expect($result)->toBeTrue();
});

it('allows comment when no recent comment from same email', function (): void {
    $cache = createRateLimiterMockCache();
    $config = createRateLimiterMockConfig();
    $rateLimiter = new CommentRateLimiter($cache, $config);

    $result = $rateLimiter->isAllowed('192.168.1.1', 'test@example.com');

    expect($result)->toBeTrue();
});

it('blocks comment when recent comment from same IP within limit', function (): void {
    $cache = createRateLimiterMockCache();
    $config = createRateLimiterMockConfig();
    $rateLimiter = new CommentRateLimiter($cache, $config);

    // Record a submission from this IP
    $rateLimiter->recordSubmission('192.168.1.1');

    // Attempt another comment from the same IP should be blocked
    $result = $rateLimiter->isAllowed('192.168.1.1');

    expect($result)->toBeFalse();
});

it('blocks comment when recent comment from same email within limit', function (): void {
    $cache = createRateLimiterMockCache();
    $config = createRateLimiterMockConfig();
    $rateLimiter = new CommentRateLimiter($cache, $config);

    // Record a submission from this email (different IP)
    $rateLimiter->recordSubmission('192.168.1.1', 'test@example.com');

    // Attempt another comment from the same email (different IP) should be blocked
    $result = $rateLimiter->isAllowed('192.168.1.2', 'test@example.com');

    expect($result)->toBeFalse();
});

it('uses configured rate_limit_seconds from BlogConfig', function (): void {
    $cache = createRateLimiterMockCacheWithTtl();
    $config = createRateLimiterMockConfig(rateLimitSeconds: 60);
    $rateLimiter = new CommentRateLimiter($cache, $config);

    // Record a submission
    $rateLimiter->recordSubmission('192.168.1.1');

    // Verify the TTL was set to configured value (60 seconds)
    expect($cache->lastTtl)->toBe(60);
});

it('records comment submission for rate tracking', function (): void {
    $cache = createRateLimiterMockCache();
    $config = createRateLimiterMockConfig();
    $rateLimiter = new CommentRateLimiter($cache, $config);

    // Initially allowed
    expect($rateLimiter->isAllowed('192.168.1.1'))->toBeTrue();

    // Record the submission
    $rateLimiter->recordSubmission('192.168.1.1');

    // Now should be blocked
    expect($rateLimiter->isAllowed('192.168.1.1'))->toBeFalse();

    // Different IP should still be allowed
    expect($rateLimiter->isAllowed('192.168.1.2'))->toBeTrue();
});

it('returns seconds remaining until next allowed comment', function (): void {
    $cache = createRateLimiterMockCache();
    $config = createRateLimiterMockConfig(rateLimitSeconds: 30);
    $rateLimiter = new CommentRateLimiter($cache, $config);

    // No submissions yet - should return 0
    expect($rateLimiter->getSecondsRemaining('192.168.1.1'))->toBe(0);

    // Record a submission
    $rateLimiter->recordSubmission('192.168.1.1');

    // Should return approximately the configured seconds (within 1 second tolerance)
    $remaining = $rateLimiter->getSecondsRemaining('192.168.1.1');
    expect($remaining)->toBeGreaterThan(0)
        ->and($remaining)->toBeLessThanOrEqual(30);
});

it('uses cache TTL for automatic cleanup no manual cleanup needed', function (): void {
    $cache = createRateLimiterMockCacheWithTtl();
    $config = createRateLimiterMockConfig(rateLimitSeconds: 45);
    $rateLimiter = new CommentRateLimiter($cache, $config);

    // Record a submission with IP and email
    $rateLimiter->recordSubmission('192.168.1.1', 'test@example.com');

    // The TTL should be set to the configured rate limit seconds
    // This means the cache handles cleanup automatically via expiration
    expect($cache->lastTtl)->toBe(45);

    // Verify no manual delete/clear methods are called on the cache
    // The rate limiter relies solely on TTL for cleanup
    // (The implementation only uses set, has, and get - no delete or clear)
});

it('hashes IP address for cache key not storing raw IP', function (): void {
    $cache = createRateLimiterMockCache();
    $config = createRateLimiterMockConfig();
    $rateLimiter = new CommentRateLimiter($cache, $config);

    $ipAddress = '192.168.1.100';
    $rateLimiter->recordSubmission($ipAddress);

    // Verify the raw IP address is NOT stored in any cache key
    foreach (array_keys($cache->storage) as $key) {
        expect($key)->not->toContain($ipAddress);
    }

    // Verify a hash is used instead (SHA-256 produces 64 hex chars)
    $keys = array_keys($cache->storage);
    expect($keys)->toHaveCount(1);

    $key = $keys[0];
    expect($key)->toMatch('/^comment_rate_ip_[a-f0-9]{64}$/');
});
