<?php

declare(strict_types=1);

namespace Marko\View;

use Marko\Core\Path\ProjectPaths;
use Marko\Core\Support\ErrorCapture;
use Marko\View\Exceptions\InsecureCacheDirectoryException;

/**
 * Resolves and secures the compiled-template cache directory.
 *
 * Template engines write compiled templates as PHP into this directory and
 * include them, so no other local user may own it or be able to write to it.
 */
readonly class CacheDirectoryGuard
{
    public function __construct(
        private ProjectPaths $paths,
    ) {}

    /**
     * Resolve the configured directory against the project root, create it
     * with mode 0700 when missing, and refuse it when it is unsafe.
     *
     * The world-writable and ownership checks are skipped on Windows: NTFS
     * ACLs don't map to Unix mode bits, so fileperms() reports nearly every
     * directory as 0777 there and the check would refuse every cache directory.
     *
     * @throws InsecureCacheDirectoryException
     */
    public function prepare(
        string $directory,
    ): string {
        // Resolve against the project root, never getcwd(): FPM, CGI and
        // mod_php chdir into public/ before running the front controller.
        $directory = rtrim($this->paths->resolve($directory), '/');

        if (file_exists($directory) && !is_dir($directory)) {
            throw InsecureCacheDirectoryException::notADirectory($directory);
        }

        if (
            !is_dir($directory)
            && !ErrorCapture::run($reason, fn (): bool => mkdir($directory, 0o700, recursive: true))
            && !is_dir($directory)
        ) {
            throw InsecureCacheDirectoryException::cannotCreate($directory, $reason);
        }

        if ($this->isWindows()) {
            return $directory;
        }

        clearstatcache(true, $directory);

        $permissions = fileperms($directory);

        if ($permissions !== false && ($permissions & 0o002) !== 0) {
            throw InsecureCacheDirectoryException::worldWritable($directory, $permissions);
        }

        if (function_exists('posix_geteuid')) {
            $owner = fileowner($directory);
            $currentUid = posix_geteuid();

            if ($owner !== false && $owner !== $currentUid) {
                throw InsecureCacheDirectoryException::notOwnedByCurrentUser($directory, $owner, $currentUid);
            }
        }

        return $directory;
    }

    /**
     * Whether PHP runs on Windows. Overridable so the Windows branch is testable on any host.
     */
    protected function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}
