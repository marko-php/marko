<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\Module;

use FilesystemIterator;
use Marko\CodeIndexer\Contract\ModuleWalkerInterface;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ModuleWalker implements ModuleWalkerInterface
{
    public function __construct(
        private readonly string $rootPath,
    ) {}

    /** @return list<ModuleInfo> */
    public function walk(): array
    {
        /** @var array<string, ModuleInfo> $byName key = composer name */
        $byName = [];

        foreach ($this->collectVendor() as $info) {
            $byName[$info->name] = $info;
        }

        foreach ($this->collectModules() as $info) {
            $byName[$info->name] = $info;
        }

        foreach ($this->collectApp() as $info) {
            $byName[$info->name] = $info;
        }

        return array_values($byName);
    }

    /** @return list<ModuleInfo> */
    private function collectVendor(): array
    {
        $results = [];
        $pattern = $this->rootPath . '/vendor/*/*/composer.json';

        foreach (glob($pattern) ?: [] as $composerFile) {
            $info = $this->buildModuleInfo($composerFile);
            if ($info !== null) {
                $results[] = $info;
            }
        }

        return $results;
    }

    /** @return list<ModuleInfo> */
    private function collectModules(): array
    {
        $results = [];
        $modulesDir = $this->rootPath . '/modules';

        if (!is_dir($modulesDir)) {
            return $results;
        }

        foreach ($this->findRecursive($modulesDir) as $composerFile) {
            $info = $this->buildModuleInfo($composerFile);
            if ($info !== null) {
                $results[] = $info;
            }
        }

        return $results;
    }

    /** @return list<ModuleInfo> */
    private function collectApp(): array
    {
        $results = [];
        $pattern = $this->rootPath . '/app/*/composer.json';

        foreach (glob($pattern) ?: [] as $composerFile) {
            $info = $this->buildModuleInfo($composerFile);
            if ($info !== null) {
                $results[] = $info;
            }
        }

        return $results;
    }

    /** @return list<string> */
    private function findRecursive(string $dir): array
    {
        $found = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() === 'composer.json') {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }

    private function buildModuleInfo(string $composerFile): ?ModuleInfo
    {
        $contents = file_get_contents($composerFile);
        if ($contents === false) {
            return null;
        }

        $data = json_decode($contents, true);
        if (!is_array($data) || !isset($data['name']) || !is_string($data['name'])) {
            return null;
        }

        $name = $data['name'];
        $path = dirname($composerFile);
        $namespace = $this->extractNamespace($data);
        $manifest = $this->loadManifest($path);

        return new ModuleInfo($name, $path, $namespace, $manifest);
    }

    /** @param array<string, mixed> $composerData */
    private function extractNamespace(array $composerData): string
    {
        $psr4 = $composerData['autoload']['psr-4'] ?? [];
        if (is_array($psr4) && count($psr4) > 0) {
            return (string) array_key_first($psr4);
        }

        return '';
    }

    /** @return array<string, mixed> */
    private function loadManifest(string $path): array
    {
        $manifestFile = $path . '/module.php';
        if (!file_exists($manifestFile)) {
            return [];
        }

        $manifest = require $manifestFile;

        return is_array($manifest) ? $manifest : [];
    }
}
