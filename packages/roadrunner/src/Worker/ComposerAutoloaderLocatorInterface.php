<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Worker;

/**
 * Locates the source file of the Composer autoloader actually registered
 * for the running process. Unlike `__FILE__`/`__DIR__` on a script reached
 * through a Composer path-repository symlink (PHP resolves those through
 * the symlink target), the registered `ClassLoader`'s own file lives at
 * `vendor/composer/ClassLoader.php`, which is never itself the symlinked
 * path — making it a symlink-independent anchor for the project root.
 */
interface ComposerAutoloaderLocatorInterface
{
    /**
     * @return string|null Absolute path to the registered ClassLoader's own
     *         source file, or null when no Composer autoloader is registered.
     */
    public function locate(): ?string;
}
