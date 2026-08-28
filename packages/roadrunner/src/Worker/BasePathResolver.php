<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Worker;

use Marko\Roadrunner\Exceptions\BasePathNotResolvableException;

/**
 * Resolves the project's base path for a worker that ships inside a vendor
 * package (`vendor/marko/roadrunner/worker.php`), where `__DIR__` cannot be
 * trusted: under a Composer path repository, `vendor/marko/roadrunner` is a
 * symlink, and PHP resolves `__FILE__`/`__DIR__` through symlink targets —
 * landing in the package's own source tree instead of the consuming
 * project.
 *
 * Resolution order:
 *   1. The MARKO_BASE_PATH environment variable (the shipped `.rr.yaml`
 *      always sets this).
 *   2. The directory containing the registered Composer autoloader's own
 *      file — never itself a symlinked path, so symlink-independent.
 *   3. The current working directory, validated like every other source
 *      rather than trusted blindly.
 */
readonly class BasePathResolver
{
    private const string ENV_VAR = 'MARKO_BASE_PATH';

    /** @var list<string> */
    private const array REQUIRED_DIRECTORIES = ['vendor', 'app', 'modules'];

    public function __construct(
        private ComposerAutoloaderLocatorInterface $autoloaderLocator,
    ) {}

    /**
     * @throws BasePathNotResolvableException
     */
    public function resolve(): string
    {
        $fromEnvironment = $this->fromEnvironment();
        if ($fromEnvironment !== null) {
            return $this->validated($fromEnvironment, sprintf('the %s environment variable', self::ENV_VAR));
        }

        $fromAutoloader = $this->fromAutoloader();
        if ($fromAutoloader !== null) {
            return $this->validated($fromAutoloader, "the loaded Composer autoloader's own path");
        }

        return $this->validated((string) getcwd(), 'the current working directory');
    }

    private function fromEnvironment(): ?string
    {
        $value = getenv(self::ENV_VAR);

        return $value === false || $value === '' ? null : $value;
    }

    private function fromAutoloader(): ?string
    {
        $classLoaderFile = $this->autoloaderLocator->locate();

        // vendor/composer/ClassLoader.php -> vendor/composer -> vendor -> project root
        return $classLoaderFile === null ? null : dirname($classLoaderFile, 3);
    }

    /**
     * @throws BasePathNotResolvableException
     */
    private function validated(
        string $path,
        string $source,
    ): string {
        $path = rtrim($path, '/');

        foreach (self::REQUIRED_DIRECTORIES as $directory) {
            if ($path === '' || !is_dir("$path/$directory")) {
                throw BasePathNotResolvableException::whenDirectoryMissing($path, $directory, $source);
            }
        }

        return $path;
    }
}
