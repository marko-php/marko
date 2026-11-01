<?php

declare(strict_types=1);

namespace Marko\Authentication\PasswordReset;

use DateTimeImmutable;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\PasswordResetTokenRepositoryInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\Config\Exceptions\ConfigNotFoundException;

/**
 * Keeps password reset token hashes in the cache, one entry per user.
 *
 * Each entry is stored with authentication.password_reset.expire_seconds as its
 * time to live, so the cache removes expired tokens on its own and deleteExpired()
 * has nothing left to delete. Use a persistent cache driver (file, Redis) in
 * production: the array driver forgets tokens at the end of the request.
 */
readonly class CachePasswordResetTokenRepository implements PasswordResetTokenRepositoryInterface
{
    private const string KEY_PREFIX = 'auth.password_reset.';

    public function __construct(
        private CacheInterface $cache,
        private AuthConfig $authConfig,
    ) {}

    /**
     * @throws InvalidKeyException|AuthException|ConfigNotFoundException
     */
    public function store(
        int|string $userId,
        string $tokenHash,
        DateTimeImmutable $createdAt,
    ): void {
        $this->cache->set(
            $this->key($userId),
            ['token_hash' => $tokenHash, 'created_at' => $createdAt->getTimestamp()],
            $this->authConfig->passwordResetExpireSeconds(),
        );
    }

    /**
     * @throws InvalidKeyException|AuthException
     */
    public function find(
        int|string $userId,
    ): ?PasswordResetTokenRecord {
        $key = $this->key($userId);
        $data = $this->cache->get($key);

        if ($data === null) {
            return null;
        }

        if (
            !is_array($data)
            || !is_string($data['token_hash'] ?? null)
            || !is_int($data['created_at'] ?? null)
        ) {
            throw AuthException::corruptPasswordResetToken($key);
        }

        return new PasswordResetTokenRecord(
            tokenHash: $data['token_hash'],
            createdAt: new DateTimeImmutable()->setTimestamp($data['created_at']),
        );
    }

    /**
     * @throws InvalidKeyException
     */
    public function delete(
        int|string $userId,
    ): void {
        $this->cache->delete($this->key($userId));
    }

    /**
     * The cache expires every entry after expire_seconds, so there is never anything to delete here.
     */
    public function deleteExpired(
        DateTimeImmutable $before,
    ): int {
        return 0;
    }

    /**
     * Hash the identifier so any string identifier (a UUID, an email) is a valid cache key.
     */
    private function key(
        int|string $userId,
    ): string {
        return self::KEY_PREFIX . hash('sha256', (string) $userId);
    }
}
