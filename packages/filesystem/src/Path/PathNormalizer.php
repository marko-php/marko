<?php

declare(strict_types=1);

namespace Marko\Filesystem\Path;

use Marko\Filesystem\Exceptions\PathException;

/**
 * The path rules every filesystem driver shares.
 *
 * A disk path is relative to the disk root: empty and '.' segments are dropped, so '/docs//a.txt'
 * and './docs/a.txt' both become 'docs/a.txt', and the root itself becomes ''. NUL bytes and '..'
 * segments are rejected with a PathException. Backslashes follow the BackslashPolicy the driver
 * passes in.
 */
readonly class PathNormalizer
{
    public function __construct(
        private BackslashPolicy $backslashes,
    ) {}

    /**
     * Normalize a disk path. Returns '' when the path refers to the disk root.
     *
     * @throws PathException
     */
    public function normalize(
        string $path,
    ): string {
        if (str_contains($path, "\0")) {
            throw PathException::invalidPath($path, 'Path contains a NUL byte');
        }

        if (str_contains($path, '\\')) {
            if ($this->backslashes === BackslashPolicy::Reject) {
                throw PathException::invalidPath(
                    $path,
                    'Path contains a backslash, which some storage services treat as a directory separator',
                );
            }

            $path = str_replace('\\', '/', $path);
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw PathException::traversalAttempt($path);
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * Normalize the path of a directory about to be deleted, refusing the disk root.
     *
     * @throws PathException
     */
    public function normalizeForDeletion(
        string $path,
    ): string {
        $normalized = $this->normalize($path);

        if ($normalized === '') {
            throw PathException::rootDeletion($path);
        }

        return $normalized;
    }
}
