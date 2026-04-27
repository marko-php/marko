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
    public function discoverInModule(ModuleManifest $manifest): array
    {
        $srcDir = $manifest->path . '/src';
    
        if (!is_dir($srcDir)) {
            return [];
        }
    
        $preferenceFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        $phpFiles = new RegexIterator($iterator, '/\.php$/i');
    
        foreach ($phpFiles as $file) {
            $filepath = $file->getPathname();
    
            // Extract potential class name from filename
            $className = $this->getClassNameFromFile($filepath);
            if (!$className) {
                continue;
            }
    
            // Try to autoload or manually include the class
            if (!class_exists($className, false)) {
                require_once $filepath;
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

    private function getClassNameFromFile(string $filepath): ?string
    {
        $contents = file_get_contents($filepath);
        if ($contents === false) {
            return null;
        }
    
        // Basic heuristic to extract namespace + class name
        $nsMatches = [];
        $classMatches = [];
    
        preg_match('/namespace\s+([^;]+);/', $contents, $nsMatches);
        preg_match('/\bclass\s+([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)/', $contents, $classMatches);
    
        if (!isset($classMatches[1])) {
            return null;
        }
    
        $namespace = $nsMatches[1] ?? '';
        return ltrim($namespace . '\\' . $classMatches[1], '\\');
    }
}
