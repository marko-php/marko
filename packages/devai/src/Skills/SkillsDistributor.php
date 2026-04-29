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

    private readonly string $devaiPackageRoot;

    public function __construct(
        private ModuleWalkerInterface $walker,
        ?string $devaiPackageRoot = null,
    ) {
        $this->devaiPackageRoot = $devaiPackageRoot ?? dirname(__DIR__, 2);
    }

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
    public function distribute(
        array $bundles,
        string $targetDir,
    ): void
    {
        $expectedSkills = self::writeBundles($bundles, $targetDir);

        foreach (glob($targetDir . '/*', GLOB_ONLYDIR) ?: [] as $existingSkillDir) {
            $name = basename($existingSkillDir);
            if (!isset($expectedSkills[$name])) {
                $this->removeDir($existingSkillDir);
            }
        }
    }

    /**
     * Write bundles into a target dir, creating nested subdirectories as needed.
     * Does NOT remove orphans — safe for agent-managed dirs that may also contain
     * user-installed skills.
     *
     * @param list<SkillBundle> $bundles
     * @return array<string, true> top-level skill names that were written
     */
    public static function writeBundles(
        array $bundles,
        string $targetDir,
    ): array
    {
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $written = [];

        foreach ($bundles as $bundle) {
            foreach ($bundle->skills as $relativePath => $content) {
                $target = $targetDir . '/' . $relativePath;
                $dir = dirname($target);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                file_put_contents($target, $content);
                $written[explode('/', $relativePath, 2)[0]] = true;
            }
        }

        return $written;
    }

    /**
     * @param array<string, string> $seenSkillNames passed by reference
     * @return list<SkillBundle>
     */
    private function collectFromDir(
        string $skillsDir,
        string $packageName,
        array &$seenSkillNames,
    ): array
    {
        $skills = [];
        foreach (glob($skillsDir . '/*', GLOB_ONLYDIR) ?: [] as $skillDir) {
            $skillName = basename($skillDir);
            if (isset($seenSkillNames[$skillName])) {
                $this->warnings[] = "Skill conflict: '$skillName' from $packageName already provided by " . $seenSkillNames[$skillName] . ' (using first)';
                continue;
            }

            $skillMdPath = $skillDir . '/SKILL.md';
            if (!is_file($skillMdPath)) {
                $this->warnings[] = "Skill '$skillName' from $packageName has no SKILL.md (skipped). Every skill directory must contain a SKILL.md.";
                continue;
            }

            $frontmatter = $this->parseFrontmatter((string) file_get_contents($skillMdPath));
            if (!isset($frontmatter['name'], $frontmatter['description'])) {
                $missing = array_values(array_diff(['name', 'description'], array_keys($frontmatter)));
                $this->warnings[] = "Skill '$skillName' from $packageName has SKILL.md missing required frontmatter (" . implode(
                    ', ',
                    $missing
                ) . "). Skipped — agents cannot auto-discover skills without name and description.";
                continue;
            }
            if ($frontmatter['name'] !== $skillName) {
                $this->warnings[] = "Skill '$skillName' from $packageName has frontmatter name '" . $frontmatter['name'] . "' which does not match its directory name. Skipped — directory and frontmatter name must match.";
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
     * Extract a flat key/value YAML frontmatter block from the head of a SKILL.md.
     * Only handles the simple `key: value` shape we require — `name` and `description`.
     *
     * @return array<string, string>
     */
    private function parseFrontmatter(string $content): array
    {
        if (!preg_match('/\A---\R(.*?)\R---\R/s', $content, $matches)) {
            return [];
        }

        $result = [];
        foreach (preg_split('/\R/', $matches[1]) ?: [] as $line) {
            if (!preg_match('/^([A-Za-z0-9_-]+):\s*(.+?)\s*$/', $line, $kv)) {
                continue;
            }
            $value = $kv[2];
            if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            $result[$kv[1]] = $value;
        }

        return $result;
    }

    /**
     * @return array<string, string> relativePath => content
     */
    private function collectFiles(
        string $skillDir,
        string $skillName,
    ): array
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
