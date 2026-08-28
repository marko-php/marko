<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Worker;

use Composer\Autoload\ClassLoader;
use ReflectionClass;

/**
 * Finds the Composer {@see ClassLoader} instance already registered with
 * `spl_autoload_functions()` — true for any process that reached this code
 * at all, since that code was itself loaded through that autoloader.
 */
readonly class SplComposerAutoloaderLocator implements ComposerAutoloaderLocatorInterface
{
    public function locate(): ?string
    {
        foreach (spl_autoload_functions() ?: [] as $function) {
            if (!is_array($function) || !($function[0] instanceof ClassLoader)) {
                continue;
            }

            $file = (new ReflectionClass($function[0]))->getFileName();

            return $file === false ? null : $file;
        }

        return null;
    }
}
