<?php

declare(strict_types=1);

namespace Marko\DevAi\Skills;

use Marko\CodeIndexer\Contract\ModuleWalkerInterface;
use Marko\DevAi\ValueObject\SkillBundle;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SkillsDistributor
{
    private const string SKILLS_REL_PATH = '/resources/ai/skills';

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(
        private ModuleWalkerInterface $walker,
        private string $devaiPackageRoot,
    ) {}

    /** @return list<SkillBundle> */
    public function collect(): array
    {
        $bundles = [];
        $seenSkillNames = [];

        $coreSkillsDir = $this->devaiPackageRoot . self::SKILLS_REL_PATH;
        if (is_dir($coreSkillsDir)) {
            foreach ($this->collectFromDir($coreSkillsDir, 'marko/devai', $seenSkillNames) as $b) {
                $bundles[] = $b;
            }
        }

        foreach ($this->walker->walk() as $module) {
            $skillsDir = $module->path . self::SKILLS_REL_PATH;
            if (is_dir($skillsDir)) {
                foreach ($this->collectFromDir($skillsDir, $module->name, $seenSkillNames) as $b) {
                    $bundles[] = $b;
                }
            }
        }

        return $bundles;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Distribute collected skills into a target dir, preserving subdirectory structure.
     * Removes any skill dirs in target that no longer exist in source (orphan removal).
     *
     * @param list<SkillBundle> $bundles
     */
    public function distribute(array $bundles, string $targetDir): void
    {
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $expectedSkills = [];
        foreach ($bundles as $bundle) {
            foreach ($bundle->skills as $relativePath => $content) {
                $target = $targetDir . '/' . $relativePath;
                $dir = dirname($target);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                file_put_contents($target, $content);
                $expectedSkills[explode('/', $relativePath, 2)[0]] = true;
            }
        }

        foreach (glob($targetDir . '/*', GLOB_ONLYDIR) ?: [] as $existingSkillDir) {
            $name = basename($existingSkillDir);
            if (!isset($expectedSkills[$name])) {
                $this->removeDir($existingSkillDir);
            }
        }
    }

    /**
     * @param array<string, string> $seenSkillNames passed by reference
     * @return list<SkillBundle>
     */
    private function collectFromDir(string $skillsDir, string $packageName, array &$seenSkillNames): array
    {
        $skills = [];
        foreach (glob($skillsDir . '/*', GLOB_ONLYDIR) ?: [] as $skillDir) {
            $skillName = basename($skillDir);
            if (isset($seenSkillNames[$skillName])) {
                $this->warnings[] = "Skill conflict: '$skillName' from $packageName already provided by " . $seenSkillNames[$skillName] . ' (using first)';
                continue;
            }
            $seenSkillNames[$skillName] = $packageName;

            $files = $this->collectFiles($skillDir, $skillName);
            if ($files !== []) {
                $skills[] = new SkillBundle(bundleName: $packageName . ':' . $skillName, skills: $files);
            }
        }

        return $skills;
    }

    /**
     * @return array<string, string> relativePath => content
     */
    private function collectFiles(string $skillDir, string $skillName): array
    {
        $files = [];
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($skillDir, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iter as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = $skillName . '/' . substr($file->getPathname(), strlen($skillDir) + 1);
            $files[$relative] = (string) file_get_contents($file->getPathname());
        }

        return $files;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iter as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}
