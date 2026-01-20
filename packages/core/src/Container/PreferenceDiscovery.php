<?php

declare(strict_types=1);

namespace Marko\Core\Container;

use Marko\Core\Module\ModuleManifest;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class PreferenceDiscovery
{
    /**
     * Discover preference files in a module's src directory.
     *
     * @return array<string> List of absolute paths to PHP files containing preferences
     */
    public function discoverInModule(
        ModuleManifest $manifest,
    ): array {
        $srcDir = $manifest->path . '/src';

        if (!is_dir($srcDir)) {
            return [];
        }

        $preferenceFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcDir),
        );
        $phpFiles = new RegexIterator($iterator, '/\.php$/');

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file->getPathname());
            if ($content !== false && str_contains($content, '#[Preference')) {
                $preferenceFiles[] = $file->getPathname();
            }
        }

        return $preferenceFiles;
    }
}
