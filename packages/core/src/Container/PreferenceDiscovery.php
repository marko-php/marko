<?php

declare(strict_types=1);

namespace Marko\Core\Container;

use Marko\Core\Attributes\Preference;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use ReflectionClass;

class PreferenceDiscovery
{
    /**
     * Discover preferences in a module's src directory.
     *
     * Scans PHP files for classes with the #[Preference] attribute and returns
     * structured records ready for registration in PreferenceRegistry.
     *
     * @return array<PreferenceRecord>
     */
    public function discoverInModule(ModuleManifest $manifest): array
    {
        $srcDir = $manifest->path . '/src';

        if (!is_dir($srcDir)) {
            return [];
        }

        $records = [];
        $parser = new ClassFileParser();

        foreach ($parser->findPhpFiles($srcDir) as $file) {
            $filepath = $file->getPathname();

            // Cheap pre-filter: skip files that obviously don't declare a preference
            // before paying for class extraction, autoload, and reflection.
            $contents = file_get_contents($filepath);
            if ($contents === false || !str_contains($contents, '#[Preference')) {
                continue;
            }

            $className = $parser->extractClassName($filepath);
            if ($className === null) {
                continue;
            }

            if (!$parser->loadClass($filepath, $className)) {
                continue;
            }

            $reflector = new ReflectionClass($className);
            $attributes = $reflector->getAttributes(Preference::class);

            if (empty($attributes)) {
                continue;
            }

            $preference = $attributes[0]->newInstance();

            $records[] = new PreferenceRecord(
                replacement: $className,
                replaces: $preference->replaces,
            );
        }

        return $records;
    }
}
