<?php

declare(strict_types=1);

namespace Marko\Cli;

class ProjectFinder
{
    public function find(
        ?string $startPath = null,
    ): ?string {
        $path = $startPath ?? getcwd();

        if ($path === false) {
            return null;
        }

        $path = realpath($path);

        if ($path === false) {
            return null;
        }

        while (true) {
            if (is_dir($path . '/vendor/marko/core')) {
                return $path;
            }

            $parent = dirname($path);

            if ($parent === $path) {
                return null;
            }

            $path = $parent;
        }
    }
}
