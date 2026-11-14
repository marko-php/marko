<?php

declare(strict_types=1);

namespace Marko\Routing;

use Marko\Routing\Exceptions\UnsafePathException;

/**
 * Joins a request-supplied path onto a base directory without letting it
 * escape. Use it whenever a route parameter becomes part of a filesystem path.
 *
 * The check is lexical: `.` and empty segments are dropped and `..` segments
 * are resolved against the segments before them, so `a/../b` is fine and
 * `../b` is refused. It does not touch the filesystem, so a symlink inside
 * the base directory can still point outside it.
 */
class SafePath
{
    /**
     * @throws UnsafePathException When the path is absolute, contains a NUL byte or escapes the base
     */
    public static function join(
        string $base,
        string $relative,
    ): string {
        if ($base === '') {
            throw UnsafePathException::emptyBase();
        }

        if (
            str_contains($base, "\0")
            || str_contains($relative, "\0")
        ) {
            throw UnsafePathException::nulByte($base);
        }

        $separators = DIRECTORY_SEPARATOR === '/' ? '/' : '/' . DIRECTORY_SEPARATOR;

        if (
            $relative !== ''
            && str_contains($separators, $relative[0])
        ) {
            throw UnsafePathException::absolutePath($base, $relative);
        }

        $segments = [];

        foreach (preg_split('#[' . preg_quote($separators, '#') . ']#', $relative) ?: [] as $segment) {
            if (
                $segment === ''
                || $segment === '.'
            ) {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    throw UnsafePathException::escapesBase($base, $relative);
                }

                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        $base = rtrim($base, $separators);

        if ($segments === []) {
            return $base === '' ? '/' : $base;
        }

        return $base . '/' . implode('/', $segments);
    }
}
