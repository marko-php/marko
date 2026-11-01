<?php

declare(strict_types=1);

namespace Marko\Cache\File\Storage;

use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Exceptions\TamperedCacheValueException;
use Marko\Cache\File\Exceptions\FileCacheException;
use Marko\Cache\Signer\CacheValueSigner;
use Marko\Core\Support\ErrorCapture;
use Psr\Clock\ClockInterface;

/**
 * Signed entry files in the cache directory: the storage FileCacheDriver and
 * FileLockStore share, including the one locked read-modify-write path
 * (mutateLocked()) behind increment(), add() and the lock store.
 *
 * Keys are not validated here: FileCacheDriver validates cache keys before it
 * calls in, and FileLockStore uses keys cache keys can never take (they
 * contain ':'), so locks and cache entries never collide.
 */
readonly class FileEntryStorage
{
    public function __construct(
        private CacheConfig $config,
        private ClockInterface $clock,
        private CacheValueSigner $cacheValueSigner,
    ) {}

    public function path(
        string $key,
    ): string {
        return $this->config->path() . '/' . hash('xxh128', $key) . '.cache';
    }

    /**
     * Read and verify an entry. The HMAC is bound to the key, so an entry whose
     * HMAC does not verify (tampered, corrupted, copied from another key's file,
     * or signed by an older release) is a miss and is deleted, so a planted file
     * can never reach unserialize(). Expiry is not checked here.
     *
     * @return array{value: mixed, expires_at: ?int, created_at: int}|null
     *
     * @throws TamperedCacheValueException when no signing key is configured
     */
    public function read(
        string $key,
    ): ?array {
        $filePath = $this->path($key);

        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            return null;
        }

        $serialized = $this->cacheValueSigner->unwrap($content, $key);

        if ($serialized === null) {
            @unlink($filePath);

            return null;
        }

        $data = unserialize($serialized);

        if (!is_array($data) || !array_key_exists('value', $data) || !isset($data['created_at'])) {
            return null;
        }

        return $data;
    }

    /**
     * Write an entry atomically (temp file plus rename).
     *
     * @param array{value: mixed, expires_at: ?int, created_at: int} $data
     *
     * @throws FileCacheException|TamperedCacheValueException
     */
    public function write(
        string $key,
        array $data,
    ): void {
        $this->ensureDirectoryExists();

        $filePath = $this->path($key);
        $tempPath = $filePath . '.tmp.' . uniqid();

        $serialized = $this->cacheValueSigner->wrap(serialize($data), $key);

        $written = ErrorCapture::run($reason, fn (): int|false => file_put_contents($tempPath, $serialized, LOCK_EX));

        if ($written === false) {
            throw FileCacheException::writeFailed($filePath, $reason);
        }

        if (!ErrorCapture::run($reason, fn (): bool => rename($tempPath, $filePath))) {
            @unlink($tempPath);

            throw FileCacheException::writeFailed($filePath, $reason);
        }
    }

    public function delete(
        string $key,
    ): bool {
        $filePath = $this->path($key);

        if (!file_exists($filePath)) {
            return true;
        }

        return unlink($filePath);
    }

    /**
     * Atomic read-modify-write of one entry under an exclusive lock on its file.
     *
     * $mutate receives the current entry, or null when the entry is missing,
     * expired, unsigned or tampered (such an entry is never unserialized). It
     * returns what to do with the entry and the result to return: an entry
     * array writes it, null leaves the file untouched, and false deletes the
     * file. The lock is held from the read to the write or delete, so
     * concurrent callers in other processes run one after another. The lock is
     * released before this returns: it guards one read-modify-write, never
     * longer.
     *
     * @template TResult
     *
     * @param callable(array{value: mixed, expires_at: ?int, created_at: int}|null): array{0: array{value: mixed, expires_at: ?int, created_at: int}|false|null, 1: TResult} $mutate
     *
     * @throws FileCacheException|TamperedCacheValueException
     *
     * @return TResult
     */
    public function mutateLocked(
        string $key,
        callable $mutate,
    ): mixed {
        $this->ensureDirectoryExists();

        $filePath = $this->path($key);
        $fh = $this->openLocked($filePath);

        try {
            $content = stream_get_contents($fh);
            $serialized = $content !== '' && $content !== false
                ? $this->cacheValueSigner->unwrap($content, $key)
                : null;
            $data = $serialized !== null ? unserialize($serialized) : null;

            if (!is_array($data)
                || !array_key_exists('value', $data)
                || !isset($data['created_at'])
                || $this->isExpired($data)
            ) {
                $data = null;
            }

            [$newData, $result] = $mutate($data);

            if ($newData === false) {
                // Unlinking while holding the lock is safe: a process waiting
                // for this lock sees the file is gone and reopens the path.
                if (!ErrorCapture::run($reason, fn (): bool => unlink($filePath))) {
                    throw FileCacheException::deleteFailed($filePath, $reason);
                }
            } elseif ($newData !== null) {
                $envelope = $this->cacheValueSigner->wrap(serialize($newData), $key);

                $written = ErrorCapture::run(
                    $reason,
                    fn (): int|false => ftruncate($fh, 0) && rewind($fh) ? fwrite($fh, $envelope) : false,
                );

                if ($written !== strlen($envelope)) {
                    throw FileCacheException::writeFailed($filePath, $reason);
                }
            }
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }

        return $result;
    }

    /**
     * @return array{value: mixed, expires_at: ?int, created_at: int}
     */
    public function newEntry(
        mixed $value,
        int $ttl,
    ): array {
        $now = $this->clock->now()->getTimestamp();

        return [
            'value' => $value,
            'expires_at' => $ttl > 0 ? $now + $ttl : null,
            'created_at' => $now,
        ];
    }

    /**
     * @param array{value: mixed, expires_at: ?int, created_at: int} $data
     */
    public function isExpired(
        array $data,
    ): bool {
        if ($data['expires_at'] === null) {
            return false;
        }

        return $this->clock->now()->getTimestamp() > $data['expires_at'];
    }

    /**
     * Open the entry's file and take an exclusive lock on it.
     *
     * A file that was unlinked or replaced while this process waited for the
     * lock (by delete(), an expired-entry cleanup, a locked delete or set()) is
     * no longer the entry, and a write to it would be lost, so the open and
     * lock are retried on the file now at the path.
     *
     * @throws FileCacheException
     *
     * @return resource
     */
    private function openLocked(
        string $filePath,
    ): mixed {
        while (true) {
            $fh = ErrorCapture::run($reason, fn (): mixed => fopen($filePath, 'c+'));

            if (!is_resource($fh)) {
                throw FileCacheException::openFailed($filePath, $reason);
            }

            if (!ErrorCapture::run($reason, fn (): bool => flock($fh, LOCK_EX))) {
                fclose($fh);

                throw FileCacheException::lockFailed($filePath, $reason);
            }

            $stat = fstat($fh);

            if ($stat !== false && $stat['nlink'] > 0) {
                return $fh;
            }

            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /**
     * Creates the cache directory only when it is missing, so the common case
     * (it already exists) never calls mkdir(). A concurrent creator winning the
     * race is fine: mkdir() fails but the directory then exists.
     *
     * @throws FileCacheException
     */
    private function ensureDirectoryExists(): void
    {
        $path = $this->config->path();

        if (is_dir($path)) {
            return;
        }

        if (!ErrorCapture::run($reason, fn (): bool => mkdir($path, 0755, recursive: true)) && !is_dir($path)) {
            throw FileCacheException::directoryNotCreatable($path, $reason);
        }
    }
}
