<?php

declare(strict_types=1);

use Marko\CodeIndexer\Contract\ModuleWalkerInterface;
use Marko\DevAi\Guidelines\GuidelinesAggregator;
use Marko\DevAi\Installation\AgentRegistry;
use Marko\DevAi\Installation\InstallationContext;
use Marko\DevAi\Installation\InstallationOrchestrator;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\Rendering\AgentsMdRenderer;
use Marko\DevAi\Rendering\ClaudeMdRenderer;
use Marko\DevAi\Skills\SkillsDistributor;

// ---------------------------------------------------------------------------
// Extended runner with LSP/npm awareness for skip-lsp-deps integration tests
// ---------------------------------------------------------------------------

function integMonorepoRunnerWithLsp(
    string $listOutput = '',
    bool $intelephenseOnPath = false,
    bool $npmOnPath = true,
    int $npmExitCode = 0,
): CommandRunnerInterface {
    return new class ($listOutput, $intelephenseOnPath, $npmOnPath, $npmExitCode) implements CommandRunnerInterface
    {
        public array $calls = [];

        public function __construct(
            public string $listOutput,
            private bool $intelephenseOnPath,
            private bool $npmOnPath,
            private int $npmExitCode,
        ) {}

        public function run(string $cmd, array $args = []): array
        {
            $this->calls[] = [$cmd, $args];
            if ($cmd === 'claude' && ($args[0] ?? '') === 'mcp' && ($args[1] ?? '') === 'list') {
                return ['exitCode' => 0, 'stdout' => $this->listOutput, 'stderr' => ''];
            }
            if ($cmd === 'npm') {
                return ['exitCode' => $this->npmExitCode, 'stdout' => '', 'stderr' => ''];
            }

            return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
        }

        public function isOnPath(string $binary): bool
        {
            return match ($binary) {
                'claude' => true,
                'intelephense' => $this->intelephenseOnPath,
                'npm' => $this->npmOnPath,
                default => false,
            };
        }
    };
}

// ---------------------------------------------------------------------------
// Helpers shared within this file
// ---------------------------------------------------------------------------

function integMonorepoTempDir(): string
{
    $dir = sys_get_temp_dir() . '/devai-integ-monorepo-' . uniqid();
    mkdir($dir, 0755, true);

    return $dir;
}

function integMonorepoRemoveTempDir(string $dir): void
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

/** Build a fake CommandRunnerInterface for monorepo integration tests. */
function integMonorepoRunner(string $listOutput = ''): CommandRunnerInterface
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
            // Report claude and intelephense as present so existing tests don't
            // trigger the npm-required exception path (they don't test LSP deps).
            return in_array($binary, ['claude', 'intelephense'], true);
        }
    };
}

/** Build a no-op ModuleWalkerInterface that returns an empty list. */
function integMonorepoWalker(): ModuleWalkerInterface
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
function integMonorepoOrchestrator(CommandRunnerInterface $runner): InstallationOrchestrator
{
    $walker = integMonorepoWalker();

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
function integMonorepoRunInstall(
    string $projectRoot,
    bool $force = false,
    ?CommandRunnerInterface $runner = null,
): array {
    $runner ??= integMonorepoRunner();
    $orchestrator = integMonorepoOrchestrator($runner);
    $ctx = new InstallationContext(selectedAgents: ['claude-code']);

    return $orchestrator->install($ctx, $projectRoot, $force);
}

// ---------------------------------------------------------------------------
// Monorepo fixture — WITH stub packages/claude-plugins/ directory
// ---------------------------------------------------------------------------

describe('monorepo fixture (with stub packages/claude-plugins)', function (): void {
    beforeEach(function (): void {
        $this->root = integMonorepoTempDir();
        // Simulate monorepo: create the packages/claude-plugins/ directory
        mkdir($this->root . '/packages/claude-plugins', 0755, true);
    });

    afterEach(function (): void {
        integMonorepoRemoveTempDir($this->root);
    });

    it('monorepo fixture (with stub packages/claude-plugins) produces settings.json with the path/local source shape per Task 001', function (): void {
        integMonorepoRunInstall($this->root);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        $source = $data['extraKnownMarketplaces']['marko']['source'];
        expect($source['source'])->toBe('local')
            ->and($source['path'])->toBe('.');
    });

    it('monorepo install still creates AGENTS.md at the project root', function (): void {
        integMonorepoRunInstall($this->root);

        expect(file_exists($this->root . '/AGENTS.md'))->toBeTrue();
    });

    it('monorepo install still creates CLAUDE.md at the project root', function (): void {
        integMonorepoRunInstall($this->root);

        expect(file_exists($this->root . '/CLAUDE.md'))->toBeTrue();
    });

    it('monorepo install still creates .claude/settings.json with enabledPlugins set to true', function (): void {
        integMonorepoRunInstall($this->root);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        expect($data['enabledPlugins']['marko-skills@marko'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-lsp@marko'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-mcp@marko'])->toBeTrue();
    });

    it('monorepo install does not create .claude/plugins/marko/.lsp.json (legacy broken path)', function (): void {
        integMonorepoRunInstall($this->root);

        expect(file_exists($this->root . '/.claude/plugins/marko/.lsp.json'))->toBeFalse();
    });

    it('monorepo install does not invoke "claude mcp add" subprocess (legacy approach)', function (): void {
        $runner = integMonorepoRunner();
        integMonorepoRunInstall($this->root, runner: $runner);

        $mcpAddCalls = array_filter(
            $runner->calls,
            fn ($call) => $call[0] === 'claude'
                && ($call[1][0] ?? '') === 'mcp'
                && ($call[1][1] ?? '') === 'add',
        );

        expect($mcpAddCalls)->toBeEmpty();
    });

    it('monorepo install creates the install marker .marko/devai.json with the new shape', function (): void {
        integMonorepoRunInstall($this->root);

        $markerPath = $this->root . '/.marko/devai.json';
        expect(file_exists($markerPath))->toBeTrue();

        $data = json_decode((string) file_get_contents($markerPath), true);
        expect($data)->toHaveKey('agents')
            ->and($data)->toHaveKey('shippedSkills')
            ->and($data)->toHaveKey('installedAt')
            ->and($data['agents'])->toContain('claude-code');
    });
});

// ---------------------------------------------------------------------------
// Mode contrast: both fixtures together prove the detection branch
// ---------------------------------------------------------------------------

it('external-project fixture (no sibling packages/claude-plugins) produces settings.json with the github source shape', function (): void {
    $externalRoot = integMonorepoTempDir(); // reuse helper — no claude-plugins dir created

    try {
        integMonorepoRunInstall($externalRoot);

        $data = json_decode((string) file_get_contents($externalRoot . '/.claude/settings.json'), true);
        $source = $data['extraKnownMarketplaces']['marko']['source'];
        expect($source['source'])->toBe('github')
            ->and($source['repo'])->toBe('marko-php/marko');
    } finally {
        integMonorepoRemoveTempDir($externalRoot);
    }
});

// ---------------------------------------------------------------------------
// --skip-lsp-deps integration tests (monorepo fixture)
// ---------------------------------------------------------------------------

describe('--skip-lsp-deps integration (monorepo)', function (): void {
    beforeEach(function (): void {
        $this->root = integMonorepoTempDir();
        mkdir($this->root . '/packages/claude-plugins', 0755, true);
    });

    afterEach(function (): void {
        integMonorepoRemoveTempDir($this->root);
    });

    it('attempts npm install -g intelephense when not skipping LSP deps and intelephense is missing', function (): void {
        $runner = integMonorepoRunnerWithLsp(intelephenseOnPath: false, npmOnPath: true);
        $orchestrator = integMonorepoOrchestrator($runner);
        $ctx = new InstallationContext(selectedAgents: ['claude-code'], skipLspDeps: false);

        $orchestrator->install($ctx, $this->root, false);

        $npmInstallCalls = array_filter(
            $runner->calls,
            fn ($call) => $call[0] === 'npm'
                && ($call[1][0] ?? '') === 'install'
                && in_array('intelephense', $call[1], true),
        );
        expect(array_values($npmInstallCalls))->not->toBeEmpty();
    });

    it('skips intelephense installation when --skip-lsp-deps is passed', function (): void {
        $runner = integMonorepoRunnerWithLsp(intelephenseOnPath: false, npmOnPath: true);
        $orchestrator = integMonorepoOrchestrator($runner);
        $ctx = new InstallationContext(selectedAgents: ['claude-code'], skipLspDeps: true);

        $orchestrator->install($ctx, $this->root, false);

        $npmInstallCalls = array_filter(
            $runner->calls,
            fn ($call) => $call[0] === 'npm'
                && in_array('intelephense', $call[1], true),
        );
        expect(array_values($npmInstallCalls))->toBeEmpty();
    });

    it('still writes settings.json correctly when --skip-lsp-deps is passed (only the LSP deps step is skipped)', function (): void {
        $runner = integMonorepoRunnerWithLsp(intelephenseOnPath: false, npmOnPath: true);
        $orchestrator = integMonorepoOrchestrator($runner);
        $ctx = new InstallationContext(selectedAgents: ['claude-code'], skipLspDeps: true);

        $orchestrator->install($ctx, $this->root, false);

        $settingsPath = $this->root . '/.claude/settings.json';
        expect(file_exists($settingsPath))->toBeTrue();

        $data = json_decode((string) file_get_contents($settingsPath), true);
        expect($data['extraKnownMarketplaces'])->toHaveKey('marko')
            ->and($data['enabledPlugins']['marko-skills@marko'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-lsp@marko'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-mcp@marko'])->toBeTrue();
    });
});
