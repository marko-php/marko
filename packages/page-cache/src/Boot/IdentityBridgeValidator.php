<?php

declare(strict_types=1);

namespace Marko\PageCache\Boot;

use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use Marko\PageCache\Contracts\IdentityInterface;
use Marko\PageCache\Entity\IdentityPurger;
use Marko\PageCache\Exceptions\PageCacheException;
use ReflectionClass;
use ReflectionException;

/**
 * Boot-time validator that detects when application code implements IdentityInterface
 * but the marko/page-cache-entity bridge package is not installed.
 */
readonly class IdentityBridgeValidator
{
    public function __construct(
        private ClassFileParser $classFileParser,
        private string $bridgeClass = IdentityPurger::class,
    ) {}

    /**
     * Validate that any app class implementing IdentityInterface has the bridge package installed.
     *
     * @param array<ModuleManifest> $modules
     * @throws PageCacheException|ReflectionException When an app class implements IdentityInterface and the bridge is missing
     */
    public function validate(array $modules): void
    {
        foreach ($modules as $manifest) {
            if (str_starts_with($manifest->name, 'marko/')) {
                continue;
            }

            $srcDir = $manifest->path . '/src';

            foreach ($this->classFileParser->findPhpFiles($srcDir) as $file) {
                $filePath = $file->getPathname();
                $className = $this->classFileParser->extractClassName($filePath);

                if ($className === null) {
                    continue;
                }

                if (!$this->classFileParser->loadClass($filePath, $className)) {
                    continue;
                }

                $reflection = new ReflectionClass($className);

                if (!$reflection->implementsInterface(IdentityInterface::class)) {
                    continue;
                }

                if (!class_exists($this->bridgeClass)) {
                    throw PageCacheException::missingEntityBridge($className);
                }
            }
        }
    }
}
