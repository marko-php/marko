<?php

declare(strict_types=1);

namespace Marko\Core\Container;

use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use ReflectionClass;

class PreferenceDiscovery
{
    private ClassFileParser $classFileParser;

    public function __construct(ClassFileParser $classFileParser)
    {
        $this->classFileParser = $classFileParser;
    }

    /**
     * Discover preference files in a module's src directory.
     *
     * @return array<string> List of absolute paths to PHP files containing preferences
     */
    public function discoverInModule(ModuleManifest $manifest): array
    {
        $srcDir = $manifest->path . '/src';

        if (!is_dir($srcDir)) {
            return [];
        }

        $preferenceFiles = [];

        foreach ($this->classFileParser->findPhpFiles($srcDir) as $file) {
            $filepath = $file->getPathname();

            // Extract the fully qualified class name from the file
            $className = $this->classFileParser->extractClassName($filepath);
            if (!$className) {
                continue;
            }

            // Try to load the class
            if (!$this->classFileParser->loadClass($filepath, $className)) {
                continue;
            }

            // Check if class exists and has the #[Preference] attribute
            if (class_exists($className) || interface_exists($className) || trait_exists($className)) {
                $reflector = new ReflectionClass($className);
                $attributes = $reflector->getAttributes('Preference');

                if (!empty($attributes)) {
                    $preferenceFiles[] = $filepath;
                }
            }
        }

        return array_unique($preferenceFiles);
    }
}
