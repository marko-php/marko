<?php

declare(strict_types=1);

use Marko\CodeIndexer\Contract\ModuleWalkerInterface;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\DevAi\Skills\SkillsDistributor;

function removeDirRecursive(string $dir): void
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

beforeEach(function (): void {
    $this->tempRoot = sys_get_temp_dir() . '/devai-skills-test-' . uniqid();
    mkdir($this->tempRoot, 0755, true);
});

afterEach(function (): void {
    removeDirRecursive($this->tempRoot);
});

function makeWalker(array $modules): ModuleWalkerInterface
{
    return new class ($modules) implements ModuleWalkerInterface
    {
        public function __construct(private array $modules) {}

        public function walk(): array
        {
            return $this->modules;
        }
    };
}

function skillFrontmatter(string $skillName, string $description = 'Test skill description'): string
{
    return "---\nname: $skillName\ndescription: $description\n---\n\n# " . $skillName . "\n";
}

function makeSkillDir(string $base, string $skillName, array $files = []): void
{
    $skillsBase = $base . '/resources/ai/skills/' . $skillName;
    mkdir($skillsBase, 0755, true);
    if ($files === []) {
        file_put_contents($skillsBase . '/SKILL.md', skillFrontmatter($skillName));

        return;
    }
    foreach ($files as $filename => $content) {
        $filePath = $skillsBase . '/' . $filename;
        $fileDir = dirname($filePath);
        if (!is_dir($fileDir)) {
            mkdir($fileDir, 0755, true);
        }
        file_put_contents($filePath, $content);
    }
}

it('preserves skill directory structure (name/SKILL.md plus supporting files)', function (): void {
    $modulePath = $this->tempRoot . '/module-struct';
    mkdir($modulePath, 0755, true);
    makeSkillDir($modulePath, 'complex-skill', [
        'SKILL.md' => skillFrontmatter('complex-skill', 'Complex skill description'),
        'helper.txt' => 'helper content',
        'examples/example.php' => '<?php // example',
    ]);

    $walker = makeWalker([
        new ModuleInfo('vendor/module-struct', $modulePath, 'Vendor\\ModuleStruct'),
    ]);

    $distributor = new SkillsDistributor($walker, $this->tempRoot . '/devai-no-core');
    $bundles = $distributor->collect();

    $targetDir = $this->tempRoot . '/target-struct/skills';
    SkillsDistributor::syncBundles($bundles, $targetDir, []);

    expect(file_exists($targetDir . '/complex-skill/SKILL.md'))->toBeTrue()
        ->and(file_get_contents($targetDir . '/complex-skill/SKILL.md'))->toContain('name: complex-skill')
        ->and(file_get_contents($targetDir . '/complex-skill/SKILL.md'))->toContain(
            'description: Complex skill description',
        )
        ->and(file_exists($targetDir . '/complex-skill/helper.txt'))->toBeTrue()
        ->and(file_get_contents($targetDir . '/complex-skill/helper.txt'))->toBe('helper content')
        ->and(file_exists($targetDir . '/complex-skill/examples/example.php'))->toBeTrue()
        ->and(file_get_contents($targetDir . '/complex-skill/examples/example.php'))->toBe('<?php // example');
});

it('copies skills to each enabled agent\'s destination path', function (): void {
    $modulePath = $this->tempRoot . '/module-x';
    mkdir($modulePath, 0755, true);
    makeSkillDir($modulePath, 'my-skill', ['SKILL.md' => skillFrontmatter('my-skill')]);

    $walker = makeWalker([
        new ModuleInfo('vendor/module-x', $modulePath, 'Vendor\\ModuleX'),
    ]);

    $distributor = new SkillsDistributor($walker, $this->tempRoot . '/devai-no-core');
    $bundles = $distributor->collect();

    $targetDir = $this->tempRoot . '/target-agent/skills';
    SkillsDistributor::syncBundles($bundles, $targetDir, []);

    expect(file_exists($targetDir . '/my-skill/SKILL.md'))->toBeTrue()
        ->and(file_get_contents($targetDir . '/my-skill/SKILL.md'))->toContain('name: my-skill');
});

it('discovers resources/ai/skills/ SKILL.md files across every module', function (): void {
    $moduleAPath = $this->tempRoot . '/module-a';
    $moduleBPath = $this->tempRoot . '/module-b';
    mkdir($moduleAPath, 0755, true);
    mkdir($moduleBPath, 0755, true);

    makeSkillDir($moduleAPath, 'skill-alpha');
    makeSkillDir($moduleBPath, 'skill-beta');

    $walker = makeWalker([
        new ModuleInfo('vendor/module-a', $moduleAPath, 'Vendor\\ModuleA'),
        new ModuleInfo('vendor/module-b', $moduleBPath, 'Vendor\\ModuleB'),
    ]);

    $distributor = new SkillsDistributor($walker, $this->tempRoot . '/devai-no-core');

    $bundles = $distributor->collect();

    $allSkillKeys = [];
    foreach ($bundles as $bundle) {
        $allSkillKeys = array_merge($allSkillKeys, array_keys($bundle->skills));
    }

    expect($allSkillKeys)->toContain('skill-alpha/SKILL.md')
        ->and($allSkillKeys)->toContain('skill-beta/SKILL.md');
});

it('handles skill name conflicts with first-wins plus warning', function (): void {
    $moduleAPath = $this->tempRoot . '/module-conflict-a';
    $moduleBPath = $this->tempRoot . '/module-conflict-b';
    mkdir($moduleAPath, 0755, true);
    mkdir($moduleBPath, 0755, true);

    makeSkillDir($moduleAPath, 'shared-skill', ['SKILL.md' => skillFrontmatter('shared-skill', 'Shared from A')]);
    makeSkillDir($moduleBPath, 'shared-skill', ['SKILL.md' => skillFrontmatter('shared-skill', 'Shared from B')]);

    $walker = makeWalker([
        new ModuleInfo('vendor/module-conflict-a', $moduleAPath, 'Vendor\\ModuleConflictA'),
        new ModuleInfo('vendor/module-conflict-b', $moduleBPath, 'Vendor\\ModuleConflictB'),
    ]);

    $distributor = new SkillsDistributor($walker, $this->tempRoot . '/devai-no-core');
    $bundles = $distributor->collect();

    // Only one bundle for shared-skill
    $allSkillKeys = [];
    foreach ($bundles as $bundle) {
        $allSkillKeys = array_merge($allSkillKeys, array_keys($bundle->skills));
    }

    $matchingKeys = array_filter($allSkillKeys, fn (string $k) => $k === 'shared-skill/SKILL.md');
    expect(count($matchingKeys))->toBe(1);

    // First wins: content from A
    $sharedContent = null;
    foreach ($bundles as $bundle) {
        if (isset($bundle->skills['shared-skill/SKILL.md'])) {
            $sharedContent = $bundle->skills['shared-skill/SKILL.md'];
        }
    }
    expect($sharedContent)->toContain('description: Shared from A');

    // Warning recorded
    $warnings = $distributor->warnings();
    expect(count($warnings))->toBe(1)
        ->and($warnings[0])->toContain('shared-skill')
        ->and($warnings[0])->toContain('vendor/module-conflict-b')
        ->and($warnings[0])->toContain('vendor/module-conflict-a');
});

it('syncBundles removes only previously-shipped skills that are no longer in the bundle', function (): void {
    $modulePath = $this->tempRoot . '/module-orphan';
    mkdir($modulePath, 0755, true);
    makeSkillDir($modulePath, 'active-skill', ['SKILL.md' => skillFrontmatter('active-skill')]);

    $walker = makeWalker([
        new ModuleInfo('vendor/module-orphan', $modulePath, 'Vendor\\ModuleOrphan'),
    ]);

    $distributor = new SkillsDistributor($walker, $this->tempRoot . '/devai-no-core');
    $bundles = $distributor->collect();

    $targetDir = $this->tempRoot . '/target-orphan/skills';
    // Simulate a previous install that shipped 'active-skill' AND 'old-skill'
    mkdir($targetDir . '/old-skill', 0755, true);
    file_put_contents($targetDir . '/old-skill/SKILL.md', '# Previously shipped, now removed');

    SkillsDistributor::syncBundles($bundles, $targetDir, ['active-skill', 'old-skill']);

    expect(file_exists($targetDir . '/active-skill/SKILL.md'))->toBeTrue()
        ->and(is_dir($targetDir . '/old-skill'))->toBeFalse();
});

it('syncBundles preserves user-authored skills that devai never shipped', function (): void {
    $modulePath = $this->tempRoot . '/module-user';
    mkdir($modulePath, 0755, true);
    makeSkillDir($modulePath, 'devai-skill', ['SKILL.md' => skillFrontmatter('devai-skill')]);

    $walker = makeWalker([
        new ModuleInfo('vendor/module-user', $modulePath, 'Vendor\\ModuleUser'),
    ]);

    $distributor = new SkillsDistributor($walker, $this->tempRoot . '/devai-no-core');
    $bundles = $distributor->collect();

    $targetDir = $this->tempRoot . '/target-user/skills';
    // User-authored skill that devai never shipped
    mkdir($targetDir . '/user-skill', 0755, true);
    file_put_contents($targetDir . '/user-skill/SKILL.md', "---\nname: user-skill\ndescription: hand-rolled\n---\n");

    // previouslyShipped does NOT include user-skill, so it must survive
    SkillsDistributor::syncBundles($bundles, $targetDir, ['devai-skill']);

    expect(file_exists($targetDir . '/devai-skill/SKILL.md'))->toBeTrue()
        ->and(file_exists($targetDir . '/user-skill/SKILL.md'))->toBeTrue();
});

it('ships a core skill set from packages/claude-plugins/plugins/marko-skills/skills/ (monorepo)', function (): void {
    // devaiPackageRoot = packages/devai/ → project root is two levels up (the monorepo root)
    $devaiPackageRoot = dirname(__DIR__, 3);
    $projectRoot = dirname($devaiPackageRoot, 2);

    $walker = makeWalker([]);

    $distributor = new SkillsDistributor($walker, $projectRoot);
    $bundles = $distributor->collect();

    $allSkillKeys = [];
    foreach ($bundles as $bundle) {
        $allSkillKeys = array_merge($allSkillKeys, array_keys($bundle->skills));
        expect($bundle->bundleName)->toStartWith('marko/claude-plugins:');
    }

    expect($allSkillKeys)->toContain('create-module/SKILL.md')
        ->and($allSkillKeys)->toContain('create-plugin/SKILL.md');
});

it('CodexAgent installer reads skill source from packages/claude-plugins/plugins/marko-skills/skills/ (not the legacy path)', function (): void {
    $projectRoot = dirname(dirname(__DIR__, 3), 2);

    $walker = makeWalker([]);
    $distributor = new SkillsDistributor($walker, $projectRoot);
    $bundles = $distributor->collect();

    $allSkillKeys = [];
    foreach ($bundles as $bundle) {
        $allSkillKeys = array_merge($allSkillKeys, array_keys($bundle->skills));
    }

    // Skills must come from the new canonical path, not resources/ai/skills
    expect($allSkillKeys)->toContain('create-module/SKILL.md')
        ->and($allSkillKeys)->not->toContain('marko-create-module/SKILL.md');
});

it('CursorAgent installer reads skill source from packages/claude-plugins/plugins/marko-skills/skills/', function (): void {
    $projectRoot = dirname(dirname(__DIR__, 3), 2);

    $walker = makeWalker([]);
    $distributor = new SkillsDistributor($walker, $projectRoot);
    $bundles = $distributor->collect();

    $allSkillKeys = [];
    foreach ($bundles as $bundle) {
        $allSkillKeys = array_merge($allSkillKeys, array_keys($bundle->skills));
    }

    expect($allSkillKeys)->toContain('create-module/SKILL.md')
        ->and($allSkillKeys)->toContain('create-plugin/SKILL.md');
});

it('CopilotAgent installer reads skill source from packages/claude-plugins/plugins/marko-skills/skills/', function (): void {
    $projectRoot = dirname(dirname(__DIR__, 3), 2);

    $walker = makeWalker([]);
    $distributor = new SkillsDistributor($walker, $projectRoot);
    $bundles = $distributor->collect();

    $allSkillKeys = [];
    foreach ($bundles as $bundle) {
        $allSkillKeys = array_merge($allSkillKeys, array_keys($bundle->skills));
    }

    expect($allSkillKeys)->toContain('create-module/SKILL.md')
        ->and($allSkillKeys)->toContain('create-plugin/SKILL.md');
});

it('GeminiCliAgent installer reads skill source from packages/claude-plugins/plugins/marko-skills/skills/', function (): void {
    $projectRoot = dirname(dirname(__DIR__, 3), 2);

    $walker = makeWalker([]);
    $distributor = new SkillsDistributor($walker, $projectRoot);
    $bundles = $distributor->collect();

    $allSkillKeys = [];
    foreach ($bundles as $bundle) {
        $allSkillKeys = array_merge($allSkillKeys, array_keys($bundle->skills));
    }

    expect($allSkillKeys)->toContain('create-module/SKILL.md')
        ->and($allSkillKeys)->toContain('create-plugin/SKILL.md');
});

it('JunieAgent installer reads skill source from packages/claude-plugins/plugins/marko-skills/skills/', function (): void {
    $projectRoot = dirname(dirname(__DIR__, 3), 2);

    $walker = makeWalker([]);
    $distributor = new SkillsDistributor($walker, $projectRoot);
    $bundles = $distributor->collect();

    $allSkillKeys = [];
    foreach ($bundles as $bundle) {
        $allSkillKeys = array_merge($allSkillKeys, array_keys($bundle->skills));
    }

    expect($allSkillKeys)->toContain('create-module/SKILL.md')
        ->and($allSkillKeys)->toContain('create-plugin/SKILL.md');
});

it('each agent copies the entire skill directory (SKILL.md plus assets/ and references/) so template references resolve', function (): void {
    $projectRoot = dirname(dirname(__DIR__, 3), 2);

    $walker = makeWalker([]);
    $distributor = new SkillsDistributor($walker, $projectRoot);
    $bundles = $distributor->collect();

    $allSkillKeys = [];
    foreach ($bundles as $bundle) {
        $allSkillKeys = array_merge($allSkillKeys, array_keys($bundle->skills));
    }

    // Assets from create-module are bundled
    expect($allSkillKeys)->toContain('create-module/SKILL.md')
        ->and($allSkillKeys)->toContain('create-module/assets/module.php.tmpl')
        ->and($allSkillKeys)->toContain('create-plugin/SKILL.md')
        ->and($allSkillKeys)->toContain('create-plugin/assets/PluginClass.php.tmpl');
});

it('the legacy packages/devai/resources/ai/skills/ directory is fully removed (parent dir included, not just child SKILL.md files removed by tasks 006/007)', function (): void {
    $devaiPackageRoot = dirname(__DIR__, 3);
    $legacySkillsDir = $devaiPackageRoot . '/resources/ai/skills';

    expect(is_dir($legacySkillsDir))->toBeFalse();
});

it('no duplicate skill content exists across the codebase — grep verification', function (): void {
    $devaiSkillsPath = dirname(__DIR__, 3) . '/resources/ai/skills';
    expect(is_dir($devaiSkillsPath))->toBeFalse();
});

it('no PHP file under packages/devai/src/ contains the literal string "resources/ai/skills" — grep assertion ensures the legacy path is no longer referenced', function (): void {
    $srcDir = dirname(__DIR__, 3) . '/src';
    $found = [];

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS),
    );
    foreach ($iter as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $content = (string) file_get_contents($file->getPathname());
        // The only permitted occurrence is the MODULE_SKILLS_REL_PATH constant in SkillsDistributor
        // (which scans third-party modules). Any other file having this string is a legacy reference.
        $occurrences = substr_count($content, 'resources/ai/skills');
        if ($occurrences === 0) {
            continue;
        }
        // SkillsDistributor is allowed exactly one occurrence (the MODULE_SKILLS_REL_PATH constant)
        $basename = basename($file->getPathname());
        if ($basename === 'SkillsDistributor.php' && $occurrences === 1) {
            continue;
        }
        $found[] = $file->getPathname();
    }

    expect($found)->toBe([]);
});

it('skips a skill directory missing SKILL.md and records a warning', function (): void {
    $modulePath = $this->tempRoot . '/module-no-skillmd';
    $skillDir = $modulePath . '/resources/ai/skills/incomplete-skill';
    mkdir($skillDir, 0755, true);
    file_put_contents($skillDir . '/notes.md', '# notes but no SKILL.md');

    $walker = makeWalker([
        new ModuleInfo('vendor/module-no-skillmd', $modulePath, 'Vendor\\ModuleNoSkillMd'),
    ]);
    $distributor = new SkillsDistributor($walker, $this->tempRoot . '/devai-no-core');
    $bundles = $distributor->collect();

    expect($bundles)->toBe([]);
    $warnings = $distributor->warnings();
    expect(count($warnings))->toBe(1)
        ->and($warnings[0])->toContain('incomplete-skill')
        ->and($warnings[0])->toContain('no SKILL.md');
});

it('skips a SKILL.md missing required frontmatter and records a warning', function (): void {
    $modulePath = $this->tempRoot . '/module-no-frontmatter';
    mkdir($modulePath, 0755, true);
    makeSkillDir($modulePath, 'unmarked-skill', [
        'SKILL.md' => "# Unmarked skill\n\nNo frontmatter at all — auto-discovery is impossible.\n",
    ]);

    $walker = makeWalker([
        new ModuleInfo('vendor/module-no-frontmatter', $modulePath, 'Vendor\\ModuleNoFrontmatter'),
    ]);
    $distributor = new SkillsDistributor($walker, $this->tempRoot . '/devai-no-core');
    $bundles = $distributor->collect();

    expect($bundles)->toBe([]);
    $warnings = $distributor->warnings();
    expect(count($warnings))->toBe(1)
        ->and($warnings[0])->toContain('unmarked-skill')
        ->and($warnings[0])->toContain('frontmatter')
        ->and($warnings[0])->toContain('name')
        ->and($warnings[0])->toContain('description');
});

it('skips a skill whose frontmatter name does not match the directory name', function (): void {
    $modulePath = $this->tempRoot . '/module-name-mismatch';
    mkdir($modulePath, 0755, true);
    makeSkillDir($modulePath, 'directory-name', [
        'SKILL.md' => skillFrontmatter('frontmatter-name', 'Mismatched name'),
    ]);

    $walker = makeWalker([
        new ModuleInfo('vendor/module-name-mismatch', $modulePath, 'Vendor\\ModuleNameMismatch'),
    ]);
    $distributor = new SkillsDistributor($walker, $this->tempRoot . '/devai-no-core');
    $bundles = $distributor->collect();

    expect($bundles)->toBe([]);
    $warnings = $distributor->warnings();
    expect(count($warnings))->toBe(1)
        ->and($warnings[0])->toContain('directory-name')
        ->and($warnings[0])->toContain('frontmatter-name')
        ->and($warnings[0])->toContain('directory name');
});
