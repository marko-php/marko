<?php

declare(strict_types=1);

namespace Marko\Session\File\Handler;

use Marko\Core\Path\ProjectPaths;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\File\Exceptions\InsecureSessionPathException;
use Marko\Session\File\Exceptions\SessionWriteException;
use Psr\Clock\ClockInterface;

readonly class FileSessionHandler implements SessionHandlerInterface
{
    private const int SECONDS_PER_MINUTE = 60;

    private string $path;

    /**
     * @throws InsecureSessionPathException
     */
    public function __construct(
        private SessionConfig $config,
        private ClockInterface $clock,
        ProjectPaths $paths,
    ) {
        $path = $config->path();

        if (str_contains($path, '://')) {
            $this->path = $path;

            return;
        }

        // Resolve against the project root, never getcwd(): FPM, CGI and
        // mod_php chdir into public/ before running the front controller.
        $path = $paths->resolve($path);

        $publicDirectory = $this->normalize($paths->base . '/public');
        $normalized = $this->normalize($path);

        if ($normalized === $publicDirectory || str_starts_with($normalized, $publicDirectory . '/')) {
            throw InsecureSessionPathException::insidePublicDirectory($normalized, $publicDirectory);
        }

        $this->path = $path;
    }

    public function open(
        string $path,
        string $name,
    ): bool {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0700, true);
        }

        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(
        string $id,
    ): string|false {
        $path = $this->getPath($id);

        if (!file_exists($path)) {
            return '';
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return false;
        }

        flock($handle, LOCK_SH);
        $size = filesize($path);
        $data = $size > 0 ? fread($handle, $size) : '';
        flock($handle, LOCK_UN);
        fclose($handle);

        return $data !== false ? $data : '';
    }

    /**
     * @throws SessionWriteException
     */
    public function write(
        string $id,
        string $data,
    ): bool {
        $path = $this->getPath($id);

        $handle = fopen($path, 'c');

        if ($handle === false) {
            return false;
        }

        try {
            flock($handle, LOCK_EX);
            chmod($path, 0600);

            if (!ftruncate($handle, 0)) {
                throw SessionWriteException::truncateFailed($path);
            }

            $expected = strlen($data);
            $written = fwrite($handle, $data);

            if ($written === false || $written !== $expected) {
                throw SessionWriteException::partialWrite($path, $written === false ? 0 : $written, $expected);
            }

            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        // Stamp the mtime with the injected clock so validateId(), gc() and
        // updateTimestamp() all measure age against one time source.
        return touch($path, $this->clock->now()->getTimestamp());
    }

    public function destroy(
        string $id,
    ): bool {
        $path = $this->getPath($id);

        if (file_exists($path)) {
            return unlink($path);
        }

        return true;
    }

    public function gc(
        int $max_lifetime,
    ): int|false {
        $count = 0;
        $expireTime = $this->clock->now()->getTimestamp() - $max_lifetime;

        $files = glob($this->path . '/sess_*');

        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            $mtime = filemtime($file);

            if ($mtime !== false && $mtime < $expireTime) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * A session is known when its file exists and was last touched within
     * the configured lifetime, measured with the injected clock.
     */
    public function validateId(
        string $id,
    ): bool {
        $path = $this->getPath($id);

        // The stat cache survives across requests in a long-running worker;
        // a stale entry could resurrect a file another process removed.
        clearstatcache(true, $path);

        if (!is_file($path)) {
            return false;
        }

        $mtime = filemtime($path);
        $expiresBefore = $this->clock->now()->getTimestamp() - $this->config->lifetime() * self::SECONDS_PER_MINUTE;

        return $mtime !== false && $mtime >= $expiresBefore;
    }

    /**
     * Slide the expiry of an existing session forward without rewriting its
     * payload. A missing file is left missing: this never creates a session.
     */
    public function updateTimestamp(
        string $id,
        string $data,
    ): bool {
        $path = $this->getPath($id);

        clearstatcache(true, $path);

        if (!is_file($path)) {
            return true;
        }

        return touch($path, $this->clock->now()->getTimestamp());
    }

    private function getPath(
        string $id,
    ): string {
        return $this->path . '/sess_' . $id;
    }

    /**
     * Collapse "." and ".." segments and duplicate slashes lexically, so the
     * public-directory check works before the session directory exists.
     */
    private function normalize(
        string $path,
    ): string {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }
}
