<?php

declare(strict_types=1);

use Marko\CodeIndexer\Contract\ModuleWalkerInterface;
use Marko\DevAi\Exceptions\DevAiInstallException;
use Marko\DevAi\Guidelines\GuidelinesAggregator;
use Marko\DevAi\Installation\AgentRegistry;
use Marko\DevAi\Installation\InstallationContext;
use Marko\DevAi\Installation\InstallationOrchestrator;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\Rendering\AgentsMdRenderer;
use Marko\DevAi\Rendering\ClaudeMdRenderer;
use Marko\DevAi\Skills\SkillsDistributor;

// ---------------------------------------------------------------------------
// Helpers shared within this file
// ---------------------------------------------------------------------------

function integExternalTempDir(): string
{
    $dir = sys_get_temp_dir() . '/devai-integ-external-' . uniqid();
    mkdir($dir, 0755, true);

    return $dir;
}

function integRemoveTempDir(string $dir): void
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

/**
 * Build a fake CommandRunnerInterface for integration tests.
 * Captures all calls; returns empty stdout by default, or listOutput for "claude mcp list".
 */
function integExternalRunner(string $listOutput = ''): CommandRunnerInterface
{
    return new class ($listOutput) implements CommandRunnerInterface
    {
        public array $calls = [];

        public function __construct(public string $listOutput) {}

        public function run(string $cmd, array $args = []): array
        {
            $this->calls[] = [$cmd, $args];
            if ($cmd === 'claude' && ($args[0] ?? '') === 'mcp' && ($args[1] ?? '') === 'list') {
                return ['exitCode' => 0, 'stdout' => $this->listOutput, 'stderr' => ''];
            }

            return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
        }

        public function isOnPath(string $binary): bool
        {
            return $binary === 'claude';
        }
    };
}

/** Build a no-op ModuleWalkerInterface that returns an empty list. */
function integExternalWalker(): ModuleWalkerInterface
{
    return new class () implements ModuleWalkerInterface
    {
        public function walk(): array
        {
            return [];
        }
    };
}

/**
 * Build a fully-wired InstallationOrchestrator with real classes, fake I/O deps.
 */
function integExternalOrchestrator(CommandRunnerInterface $runner): InstallationOrchestrator
{
    $walker = integExternalWalker();

    return new InstallationOrchestrator(
        registry: new AgentRegistry($runner),
        agentsRenderer: new AgentsMdRenderer(),
        claudeRenderer: new ClaudeMdRenderer(),
        guidelinesAggregator: new GuidelinesAggregator($walker),
        skillsDistributor: new SkillsDistributor($walker),
        runner: $runner,
    );
}

/**
 * Run the install for claude-code against $projectRoot and return the result.
 *
 * @return array{status: string, message?: string, log?: list<string>}
 */
function integExternalRunInstall(
    string $projectRoot,
    bool $force = false,
    ?CommandRunnerInterface $runner = null,
): array {
    $runner ??= integExternalRunner();
    $orchestrator = integExternalOrchestrator($runner);
    $ctx = new InstallationContext(selectedAgents: ['claude-code']);

    return $orchestrator->install($ctx, $projectRoot, $force);
}

// ---------------------------------------------------------------------------
// External-project fixture — NO sibling packages/claude-plugins/ directory
// ---------------------------------------------------------------------------

describe('external-project fixture (no sibling packages/claude-plugins)', function (): void {
    beforeEach(function (): void {
        $this->root = integExternalTempDir();
        // Explicitly: no packages/claude-plugins/ here — external project
    });

    afterEach(function (): void {
        integRemoveTempDir($this->root);
    });

    it('running devai:install --agents=claude-code in a tempdir creates AGENTS.md at the project root', function (): void {
        integExternalRunInstall($this->root);

        expect(file_exists($this->root . '/AGENTS.md'))->toBeTrue();
    });

    it('running devai:install --agents=claude-code creates CLAUDE.md at the project root', function (): void {
        integExternalRunInstall($this->root);

        expect(file_exists($this->root . '/CLAUDE.md'))->toBeTrue();
    });

    it('CLAUDE.md content includes the verbatim authority directive about skills as canonical spec', function (): void {
        integExternalRunInstall($this->root);

        $content = (string) file_get_contents($this->root . '/CLAUDE.md');
        expect($content)
            ->toContain('skill is the canonical specification')
            ->and($content)->toContain('marko-skills:create-module');
    });

    it('CLAUDE.md content includes the LSP verification gate directive', function (): void {
        integExternalRunInstall($this->root);

        $content = (string) file_get_contents($this->root . '/CLAUDE.md');
        expect($content)
            ->toContain('LSP diagnostics')
            ->and($content)->toContain('verification gate');
    });

    it('running devai:install --agents=claude-code creates .claude/settings.json', function (): void {
        integExternalRunInstall($this->root);

        expect(file_exists($this->root . '/.claude/settings.json'))->toBeTrue();
    });

    it('.claude/settings.json contains extraKnownMarketplaces.marko entry with the expected source shape', function (): void {
        integExternalRunInstall($this->root);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        expect($data)->toHaveKey('extraKnownMarketplaces')
            ->and($data['extraKnownMarketplaces'])->toHaveKey('marko');
    });

    it('.claude/settings.json contains enabledPlugins with all three plugin entries set to true', function (): void {
        integExternalRunInstall($this->root);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        expect($data['enabledPlugins']['marko-skills@marko'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-lsp@marko'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-mcp@marko'])->toBeTrue();
    });

    it('running devai:install --agents=claude-code does not create .claude/plugins/marko/.lsp.json (legacy broken path)', function (): void {
        integExternalRunInstall($this->root);

        expect(file_exists($this->root . '/.claude/plugins/marko/.lsp.json'))->toBeFalse();
    });

    it('running devai:install --agents=claude-code does not invoke "claude mcp add" subprocess (legacy approach)', function (): void {
        $runner = integExternalRunner();
        integExternalRunInstall($this->root, runner: $runner);

        $mcpAddCalls = array_filter(
            $runner->calls,
            fn ($call) => $call[0] === 'claude'
                && ($call[1][0] ?? '') === 'mcp'
                && ($call[1][1] ?? '') === 'add',
        );

        expect($mcpAddCalls)->toBeEmpty();
    });

    it('running devai:install --agents=claude-code creates the install marker .marko/devai.json with the new shape', function (): void {
        integExternalRunInstall($this->root);

        $markerPath = $this->root . '/.marko/devai.json';
        expect(file_exists($markerPath))->toBeTrue();

        $data = json_decode((string) file_get_contents($markerPath), true);
        expect($data)->toHaveKey('agents')
            ->and($data)->toHaveKey('shippedSkills')
            ->and($data)->toHaveKey('installedAt')
            ->and($data['agents'])->toContain('claude-code');
    });

    it('external-project fixture (no sibling packages/claude-plugins) produces settings.json with the github source shape', function (): void {
        integExternalRunInstall($this->root);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        $source = $data['extraKnownMarketplaces']['marko']['source'];
        expect($source['source'])->toBe('github')
            ->and($source['repo'])->toBe('marko-php/marko');
    });
});

// ---------------------------------------------------------------------------
// Re-run / idempotency tests (external-project fixture)
// ---------------------------------------------------------------------------

describe('re-running devai:install on an external project', function (): void {
    beforeEach(function (): void {
        $this->root = integExternalTempDir();
    });

    afterEach(function (): void {
        integRemoveTempDir($this->root);
    });

    it('re-running devai:install on a project that already has the legacy .lsp.json or a registered marko-mcp server cleans them up only when --force is passed (legacy artifact cleanup is gated by --force)', function (): void {
        // Seed legacy artifacts: .lsp.json file and a runner that reports marko-mcp in list
        $legacyDir = $this->root . '/.claude/plugins/marko';
        mkdir($legacyDir, 0755, true);
        file_put_contents($legacyDir . '/.lsp.json', '{"old":"stuff"}');

        $runner = integExternalRunner(listOutput: "marko-mcp: stdio - php marko mcp:serve\n");

        // First install (force=false) — no prior extraKnownMarketplaces, so install runs
        integExternalRunInstall($this->root, force: false, runner: $runner);

        // Legacy .lsp.json must be cleaned up even on first install
        expect(file_exists($legacyDir . '/.lsp.json'))->toBeFalse();

        // marko-mcp remove call must have been made (legacy cleanup)
        $removeCalls = array_filter(
            $runner->calls,
            fn ($call) => $call[0] === 'claude'
                && ($call[1][0] ?? '') === 'mcp'
                && ($call[1][1] ?? '') === 'remove',
        );
        expect(array_values($removeCalls))->not->toBeEmpty();

        // Re-seed legacy artifacts to test that --force triggers cleanup again
        mkdir($legacyDir, 0755, true);
        file_put_contents($legacyDir . '/.lsp.json', '{"old":"stuff2"}');

        $runner2 = integExternalRunner(listOutput: "marko-mcp: stdio - php marko mcp:serve\n");

        // Re-run with --force — should clean up and overwrite
        integExternalRunInstall($this->root, force: true, runner: $runner2);

        expect(file_exists($legacyDir . '/.lsp.json'))->toBeFalse();

        $removeCalls2 = array_filter(
            $runner2->calls,
            fn ($call) => $call[0] === 'claude'
                && ($call[1][0] ?? '') === 'mcp'
                && ($call[1][1] ?? '') === 'remove',
        );
        expect(array_values($removeCalls2))->not->toBeEmpty();
    });

    it('re-running devai:install on a project that already has extraKnownMarketplaces.marko in settings.json (without --force) exits with a loud Marko exception and a non-zero exit code', function (): void {
        // Pre-populate settings.json with the marketplace entry as a prior install would have done,
        // but do NOT write the .marko/devai.json marker — that would cause the orchestrator's
        // marker-file guard to short-circuit before reaching the settings-level idempotency check.
        $claudeDir = $this->root . '/.claude';
        mkdir($claudeDir, 0755, true);
        $settings = [
            'extraKnownMarketplaces' => [
                'marko' => ['source' => ['source' => 'github', 'repo' => 'marko-php/marko']],
            ],
            'enabledPlugins' => [
                'marko-skills@marko' => true,
                'marko-lsp@marko' => true,
                'marko-mcp@marko' => true,
            ],
        ];
        file_put_contents($claudeDir . '/settings.json', json_encode($settings));

        // Install without --force — must throw DevAiInstallException because
        // extraKnownMarketplaces.marko is already present in settings.json
        $runner = integExternalRunner();
        $orchestrator = integExternalOrchestrator($runner);
        $ctx = new InstallationContext(selectedAgents: ['claude-code']);

        expect(fn () => $orchestrator->install($ctx, $this->root, false))
            ->toThrow(DevAiInstallException::class);
    });

    it('re-running devai:install --force on a project with prior extraKnownMarketplaces.marko succeeds and overwrites marko-related keys', function (): void {
        // First install sets github source
        integExternalRunInstall($this->root, force: false);

        // Manually corrupt the marketplace entry to detect overwrite
        $settingsPath = $this->root . '/.claude/settings.json';
        $data = json_decode((string) file_get_contents($settingsPath), true);
        $data['extraKnownMarketplaces']['marko']['source'] = ['source' => 'old-corrupted'];
        file_put_contents($settingsPath, json_encode($data));

        // Re-run with --force — must succeed and restore proper shape
        integExternalRunInstall($this->root, force: true);

        $after = json_decode((string) file_get_contents($settingsPath), true);
        expect($after['extraKnownMarketplaces']['marko']['source']['source'])->toBe('github');
    });

    it('re-running devai:install --force preserves unrelated user keys in the existing .claude/settings.json', function (): void {
        // First install
        integExternalRunInstall($this->root, force: false);

        // Add unrelated user keys to settings
        $settingsPath = $this->root . '/.claude/settings.json';
        $data = json_decode((string) file_get_contents($settingsPath), true);
        $data['theme'] = 'dark';
        $data['myCustomKey'] = 'preserved-value';
        $data['enabledPlugins']['unrelated-plugin@other'] = true;
        file_put_contents($settingsPath, json_encode($data));

        // Re-run with --force
        integExternalRunInstall($this->root, force: true);

        $after = json_decode((string) file_get_contents($settingsPath), true);
        expect($after['theme'])->toBe('dark')
            ->and($after['myCustomKey'])->toBe('preserved-value')
            ->and($after['enabledPlugins']['unrelated-plugin@other'])->toBeTrue()
            ->and($after['enabledPlugins']['marko-skills@marko'])->toBeTrue();
    });
});
