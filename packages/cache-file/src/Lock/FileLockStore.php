<?php

declare(strict_types=1);

namespace Marko\Cache\File\Lock;

use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Exceptions\TamperedCacheValueException;
use Marko\Cache\File\Exceptions\FileCacheException;
use Marko\Cache\File\Storage\FileEntryStorage;
use Marko\Cache\Lock\LockScope;

/**
 * Lock store on the local filesystem: excludes processes on the same host.
 *
 * A lock is a persisted record (owner and expiry) in the cache directory,
 * claimed and freed through FileEntryStorage::mutateLocked(), the same locked
 * read-modify-write behind FileCacheDriver::increment() and add(). The flock()
 * guards only that brief read-modify-write and is never held for the lock's
 * lifetime: a held flock() dies with its process, so restore() and release()
 * from another process, and locks that outlive a request, would not work.
 */
readonly class FileLockStore implements LockStoreInterface
{
    /**
     * Cache keys may not contain ':', so no cache entry can share a lock's file.
     */
    private const string KEY_PREFIX = 'lock:';

    public function __construct(
        private FileEntryStorage $storage,
    ) {}

    /**
     * @throws FileCacheException|TamperedCacheValueException
     */
    public function acquire(
        string $name,
        string $owner,
        int $seconds,
    ): bool {
        return $this->storage->mutateLocked(
            $this->key($name),
            fn (?array $data): array => $data === null
                ? [$this->storage->newEntry($owner, $seconds), true]
                : [null, false],
        );
    }

    /**
     * @throws FileCacheException|TamperedCacheValueException
     */
    public function release(
        string $name,
        string $owner,
    ): bool {
        return $this->storage->mutateLocked(
            $this->key($name),
            fn (?array $data): array => match (true) {
                $data === null => [false, false],
                $data['value'] === $owner => [false, true],
                default => [null, false],
            },
        );
    }

    public function forceRelease(
        string $name,
    ): void {
        $this->storage->delete($this->key($name));
    }

    /**
     * Reads under the entry's file lock, so it never sees a record half written.
     *
     * @throws FileCacheException|TamperedCacheValueException
     */
    public function currentOwner(
        string $name,
    ): ?string {
        return $this->storage->mutateLocked(
            $this->key($name),
            fn (?array $data): array => $data === null ? [false, null] : [null, $data['value']],
        );
    }

    public function scope(): LockScope
    {
        return LockScope::Host;
    }

    private function key(
        string $name,
    ): string {
        return self::KEY_PREFIX . $name;
    }
}
