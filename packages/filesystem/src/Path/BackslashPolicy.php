<?php

declare(strict_types=1);

namespace Marko\Filesystem\Path;

/**
 * How PathNormalizer treats a backslash in a disk path.
 */
enum BackslashPolicy
{
    /**
     * Treat a backslash as a directory separator (local disks, where Windows-style paths are common).
     */
    case Translate;

    /**
     * Reject the path (object stores, where some S3-compatible services treat a backslash as a separator).
     */
    case Reject;
}
