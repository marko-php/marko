<?php

declare(strict_types=1);

namespace Marko\DevAi\Skills;

use Marko\CodeIndexer\Contract\ModuleWalkerInterface;
use Marko\DevAi\ValueObject\SkillBundle;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SkillsDistributor
{
    private const string MODULE_SKILLS_REL_PATH = '/resources/ai/skills';

    private const string CANONICAL_SKILLS_REL_PATH = '/packages/claude-plugins/plugins/marko-skills/skills';

    private const string VENDOR_SKILLS_REL_PATH = '/vendor/marko/claude-plugins/plugins/marko-skills/skills';

    /** @var list<string> */
    private array $warnings = [];

    private readonly string $projectRoot;

    public function __construct(
        private ModuleWalkerInterface $walker,
        ?string $projectRoot = null,
    ) {
        // Default: devai lives at <projectRoot>/packages/devai/ (monorepo)
        // or <projectRoot>/vendor/marko/devai/ (external) — walk up to find project root
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 4);
    }

    /** @return list<SkillBundle> */
    public function collect(): array
    {
        $bundles = [];
        $seenSkillNames = [];

        $coreSkillsDir = $this->resolveCoreSkillsDir();
        if ($coreSkillsDir !== null && is_dir($coreSkillsDir)) {
            foreach ($this->collectFromDir($coreSkillsDir, 'marko/claude-plugins', $seenSkillNames) as $b) {
                $bundles[] = $b;
            }
        }

        foreach ($this->walker->walk() as $module) {
            $skillsDir = $module->path . self::MODULE_SKILLS_REL_PATH;
            if (is_dir($skillsDir)) {
                foreach ($this->collectFromDir($skillsDir, $module->name, $seenSkillNames) as $b) {
                    $bundles[] = $b;
                }
            }
        }

        return $bundles;
    }

    private function resolveCoreSkillsDir(): ?string
    {
        // Monorepo: packages/claude-plugins/ exists at project root
        $monorepoPath = $this->projectRoot . self::CANONICAL_SKILLS_REL_PATH;
        if (is_dir($monorepoPath)) {
            return $monorepoPath;
        }

        // External project: installed via Composer
        $vendorPath = $this->projectRoot . self::VENDOR_SKILLS_REL_PATH;
        if (is_dir($vendorPath)) {
            return $vendorPath;
        }

        return null;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
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
    ): array {
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
     * Write current bundles AND remove orphans from prior installs.
     *
     * "Orphan" = a skill directory at the target whose name appears in
     * $previouslyShipped (i.e. devai shipped it before) but is NOT in the
     * current $bundles. User-authored skills in the same directory are left
     * untouched because they were never in $previouslyShipped.
     *
     * @param list<SkillBundle> $bundles
     * @param list<string> $previouslyShipped top-level skill names devai shipped on the prior install
     */
    public static function syncBundles(
        array $bundles,
        string $targetDir,
        array $previouslyShipped,
    ): void {
        $written = self::writeBundles($bundles, $targetDir);

        foreach ($previouslyShipped as $priorName) {
            if (isset($written[$priorName])) {
                continue;
            }
            $orphanDir = $targetDir . '/' . $priorName;
            if (is_dir($orphanDir)) {
                self::removeDir($orphanDir);
            }
        }
    }

    /**
     * @param array<string, string> $seenSkillNames passed by reference
     * @return list<SkillBundle>
     */
    private function collectFromDir(
        string $skillsDir,
        string $packageName,
        array &$seenSkillNames,
    ): array {
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
                    $missing,
                ) . '). Skipped — agents cannot auto-discover skills without name and description.';
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
    ): array {
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

    private static function removeDir(string $dir): void
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
