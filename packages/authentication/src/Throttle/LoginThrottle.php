<?php

declare(strict_types=1);

namespace Marko\Authentication\Throttle;

use Marko\Authentication\Contracts\LoginThrottleInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\TooManyLoginAttemptsException;
use Marko\Authentication\Http\CurrentRequest;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Exceptions\CacheException;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\RateLimiter\Contracts\RateLimitKeyResolverInterface;
use Psr\Clock\ClockInterface;

/**
 * Cache-backed login throttle keyed on the normalised login identifier
 * (every non-password credential, trimmed and lower-cased) plus the client.
 *
 * After maxAttempts failures within decaySeconds, the identifier is locked
 * out for that client for lockoutSeconds. Each further lockout while the
 * previous one is remembered doubles the duration, up to maxLockoutSeconds.
 * Lockout history lasts twice maxLockoutSeconds from the first lockout, and
 * a successful login clears it.
 *
 * The client is the bound RateLimitKeyResolverInterface's identity when
 * marko/ratelimiter is installed (trusted proxies, IPv6 /64 bucketing), and
 * REMOTE_ADDR otherwise. Keying on identifier and client together means an
 * attacker cannot lock a user out from every other network.
 *
 * Cache failures propagate: the throttle fails closed rather than allowing
 * attempts it could not count.
 */
readonly class LoginThrottle implements LoginThrottleInterface
{
    private const string KEY_PREFIX = 'auth_throttle.';

    /**
     * @throws AuthException
     */
    public function __construct(
        private ?CacheInterface $cache,
        private ClockInterface $clock,
        private CurrentRequest $currentRequest,
        private int $maxAttempts = 5,
        private int $decaySeconds = 60,
        private int $lockoutSeconds = 60,
        private int $maxLockoutSeconds = 3600,
        private ?RateLimitKeyResolverInterface $rateLimitKeyResolver = null,
    ) {
        $limits = [
            'max_attempts' => $maxAttempts,
            'decay_seconds' => $decaySeconds,
            'lockout_seconds' => $lockoutSeconds,
            'max_lockout_seconds' => $maxLockoutSeconds,
        ];

        foreach ($limits as $name => $value) {
            if ($value < 1) {
                throw AuthException::invalidThrottleLimit($name, $value);
            }
        }
    }

    /**
     * @param array<string, mixed> $credentials
     *
     * @throws AuthException|CacheException|InvalidKeyException|TooManyLoginAttemptsException
     */
    public function ensureNotLockedOut(
        string $guard,
        array $credentials,
    ): void {
        $lockedUntil = $this->cache()->get($this->key($guard, $credentials, 'lockout'));

        if (!is_int($lockedUntil)) {
            return;
        }

        $retryAfter = $lockedUntil - $this->clock->now()->getTimestamp();

        if ($retryAfter > 0) {
            throw TooManyLoginAttemptsException::lockedOut($guard, $retryAfter);
        }
    }

    /**
     * @param array<string, mixed> $credentials
     *
     * @throws AuthException|CacheException|InvalidKeyException
     */
    public function recordFailure(
        string $guard,
        array $credentials,
    ): void {
        $cache = $this->cache();
        $failuresKey = $this->key($guard, $credentials, 'failures');

        if ($cache->increment($failuresKey, $this->decaySeconds) < $this->maxAttempts) {
            return;
        }

        $cache->delete($failuresKey);

        $lockouts = $cache->increment(
            $this->key($guard, $credentials, 'lockouts'),
            $this->maxLockoutSeconds * 2,
        );
        $duration = $this->lockoutDuration($lockouts);

        $cache->set(
            $this->key($guard, $credentials, 'lockout'),
            $this->clock->now()->getTimestamp() + $duration,
            $duration,
        );
    }

    /**
     * @param array<string, mixed> $credentials
     *
     * @throws AuthException|InvalidKeyException
     */
    public function clear(
        string $guard,
        array $credentials,
    ): void {
        $this->cache()->deleteMultiple([
            $this->key($guard, $credentials, 'failures'),
            $this->key($guard, $credentials, 'lockouts'),
            $this->key($guard, $credentials, 'lockout'),
        ]);
    }

    /**
     * lockoutSeconds doubled for every lockout before this one, capped at maxLockoutSeconds.
     */
    private function lockoutDuration(
        int $lockouts,
    ): int {
        $duration = $this->lockoutSeconds;

        for ($i = 1; $i < $lockouts
            && $duration < $this->maxLockoutSeconds; $i++) {
            $duration *= 2;
        }

        return min($duration, $this->maxLockoutSeconds);
    }

    /**
     * @throws AuthException
     */
    private function cache(): CacheInterface
    {
        return $this->cache ?? throw AuthException::throttleRequiresCache();
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function key(
        string $guard,
        array $credentials,
        string $suffix,
    ): string {
        $identity = implode("\0", [$guard, $this->identifier($credentials), $this->client()]);

        return self::KEY_PREFIX . hash('xxh128', $identity) . '.' . $suffix;
    }

    /**
     * Every non-password scalar credential, trimmed and lower-cased, so
     * "Admin@Example.com " and "admin@example.com" share one counter.
     *
     * @param array<string, mixed> $credentials
     */
    private function identifier(
        array $credentials,
    ): string {
        $parts = [];

        foreach ($credentials as $name => $value) {
            if (
                str_contains(strtolower((string) $name), 'password')
                || !is_scalar($value)
            ) {
                continue;
            }

            $parts[(string) $name] = mb_strtolower(trim((string) $value));
        }

        ksort($parts);

        return http_build_query($parts);
    }

    private function client(): string
    {
        $request = $this->currentRequest->get();
        $ip = $request?->ip();

        if (
            $request === null
            || $ip === null
            || $ip === ''
        ) {
            return '';
        }

        return $this->rateLimitKeyResolver?->resolve($request) ?? $ip;
    }
}
