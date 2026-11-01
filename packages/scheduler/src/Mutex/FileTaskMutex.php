<?php

declare(strict_types=1);

namespace Marko\Scheduler\Mutex;

use Marko\Scheduler\Exceptions\SchedulerException;
use Psr\Clock\ClockInterface;

/**
 * Local file mutex for scheduled tasks.
 *
 * Holds an exclusive, non-blocking flock() on {directory}/{mutexName} for as long as the
 * task runs, and writes the expiry timestamp into the file. If the holder crashes, the
 * kernel drops the lock. If the holder hangs past its expiry, the next contender unlinks
 * the file and locks a fresh one. Only protects processes on the same host.
 */
class FileTaskMutex implements TaskMutexInterface
{
    private const int MAX_ATTEMPTS = 3;

    /** @var array<string, resource> */
    private array $handles = [];

    public function __construct(
        private readonly string $directory,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @throws SchedulerException
     */
    public function acquire(
        string $name,
        int $expiresAfterSeconds,
    ): bool {
        if (isset($this->handles[$name])) {
            return false;
        }

        $this->ensureDirectoryExists();
        $path = $this->path($name);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $handle = $this->open($path, 'c+');

            if (flock($handle, LOCK_EX | LOCK_NB)) {
                if (!$this->isCurrentFile($handle, $path)) {
                    // A contender reclaimed (unlinked) the file between our open and lock.
                    flock($handle, LOCK_UN);
                    fclose($handle);

                    continue;
                }

                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, (string) ($this->now() + $expiresAfterSeconds));
                fflush($handle);
                $this->handles[$name] = $handle;

                return true;
            }

            $expiresAt = $this->readExpiry($handle);
            fclose($handle);

            if ($expiresAt === null || $expiresAt > $this->now()) {
                return false;
            }

            // Stale: the holder is still alive but past its expiry. Detach it from the
            // path so the next attempt locks a fresh file.
            clearstatcache(true, $path);
            if (is_file($path)) {
                unlink($path);
            }
        }

        return false;
    }

    /**
     * @throws SchedulerException
     */
    public function release(
        string $name,
    ): void {
        if (!isset($this->handles[$name])) {
            return;
        }

        $handle = $this->handles[$name];
        unset($this->handles[$name]);

        ftruncate($handle, 0);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * @throws SchedulerException
     */
    public function exists(
        string $name,
    ): bool {
        if (isset($this->handles[$name])) {
            return true;
        }

        $path = $this->path($name);
        clearstatcache(true, $path);

        if (!is_file($path)) {
            return false;
        }

        $handle = $this->open($path, 'r');

        if (flock($handle, LOCK_SH | LOCK_NB)) {
            flock($handle, LOCK_UN);
            fclose($handle);

            return false;
        }

        $expiresAt = $this->readExpiry($handle);
        fclose($handle);

        return $expiresAt === null || $expiresAt > $this->now();
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }

    /**
     * @throws SchedulerException When the name is not a safe file name
     */
    private function path(
        string $name,
    ): string {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name) !== 1) {
            throw SchedulerException::invalidMutexName($name);
        }

        return $this->directory . '/' . $name;
    }

    /**
     * @throws SchedulerException
     */
    private function ensureDirectoryExists(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (!mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            throw SchedulerException::mutexDirectoryNotWritable($this->directory);
        }
    }

    /**
     * @return resource
     * @throws SchedulerException
     */
    private function open(
        string $path,
        string $mode,
    ): mixed {
        $handle = fopen($path, $mode);

        if ($handle === false) {
            throw SchedulerException::mutexFileNotOpenable($path);
        }

        return $handle;
    }

    /**
     * @param resource $handle
     */
    private function isCurrentFile(
        mixed $handle,
        string $path,
    ): bool {
        clearstatcache(true, $path);

        if (!is_file($path)) {
            return false;
        }

        $handleStat = fstat($handle);
        $pathStat = stat($path);

        return $handleStat !== false
            && $pathStat !== false
            && $handleStat['ino'] === $pathStat['ino']
            && $handleStat['dev'] === $pathStat['dev'];
    }

    /**
     * @param resource $handle
     */
    private function readExpiry(
        mixed $handle,
    ): ?int {
        rewind($handle);
        $contents = trim((string) stream_get_contents($handle));

        return ctype_digit($contents) ? (int) $contents : null;
    }
}
