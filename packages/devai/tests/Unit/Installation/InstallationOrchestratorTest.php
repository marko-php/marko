<?php

declare(strict_types=1);

use Marko\CodeIndexer\Contract\ModuleWalkerInterface;
use Marko\DevAi\Agents\AbstractAgent;
use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsLsp;
use Marko\DevAi\Contract\SupportsMcp;
use Marko\DevAi\Contract\SupportsSkills;
use Marko\DevAi\Guidelines\GuidelinesAggregator;
use Marko\DevAi\Installation\AgentRegistry;
use Marko\DevAi\Installation\InstallationContext;
use Marko\DevAi\Installation\InstallationOrchestrator;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\Rendering\AgentsMdRenderer;
use Marko\DevAi\Rendering\ClaudeMdRenderer;
use Marko\DevAi\Skills\SkillsDistributor;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\LspRegistration;
use Marko\DevAi\ValueObject\McpRegistration;

function makeInstallRunner(bool $installed = false): CommandRunnerInterface
{
    return new class ($installed) implements CommandRunnerInterface
    {
        public function __construct(private bool $installed) {}

        public function run(
            string $command,
            array $args = [],
        ): array
        {
            return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
        }

        public function isOnPath(string $binary): bool
        {
            return $this->installed;
        }
    };
}

function makeInstallFullAgent(bool $installed = false): AgentInterface&SupportsGuidelines&SupportsMcp&SupportsLsp&SupportsSkills
{
    return new class ($installed) extends AbstractAgent implements SupportsGuidelines, SupportsMcp, SupportsLsp, SupportsSkills
    {
        public array $guidelinesCalls = [];

        public array $mcpCalls = [];

        public array $lspCalls = [];

        public array $skillsCalls = [];

        public function __construct(private bool $installed) {}

        public function name(): string
        {
            return 'test-agent';
        }

        public function displayName(): string
        {
            return 'Test Agent';
        }

        public function isInstalled(): bool
        {
            return $this->installed;
        }

        public function writeGuidelines(
            GuidelinesContent $content,
            string $projectRoot,
        ): void
        {
            $this->guidelinesCalls[] = [$content, $projectRoot];
        }

        public function registerMcpServer(
            McpRegistration $registration,
            string $projectRoot,
        ): void
        {
            $this->mcpCalls[] = [$registration, $projectRoot];
        }

        public function registerLspServer(
            LspRegistration $registration,
            string $projectRoot,
        ): void
        {
            $this->lspCalls[] = [$registration, $projectRoot];
        }

        public function distributeSkills(
            array $bundles,
            string $projectRoot,
            array $previouslyShipped = [],
        ): void
        {
            $this->skillsCalls[] = [$bundles, $projectRoot, $previouslyShipped];
        }
    };
}

function makeInstallRegistry(array $agents): AgentRegistry
{
    $runner = makeInstallRunner();

    return new class ($runner, $agents) extends AgentRegistry
    {
        public function __construct(
            CommandRunnerInterface $runner,
            private array $agentMap,
        ) {
            parent::__construct($runner);
        }

        public function all(string $projectRoot): array
        {
            return $this->agentMap;
        }
    };
}

function makeNullWalker(): ModuleWalkerInterface
{
    return new class () implements ModuleWalkerInterface
    {
        public function walk(): array
        {
            return [];
        }
    };
}

function makeRecordingRunner(): CommandRunnerInterface
{
    return new class () implements CommandRunnerInterface
    {
        /** @var list<array{string, list<string>}> */
        public array $calls = [];

        public function run(
            string $command,
            array $args = [],
        ): array
        {
            $this->calls[] = [$command, $args];

            return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
        }

        public function isOnPath(string $binary): bool
        {
            return false;
        }
    };
}

function makeInstallOrchestrator(
    AgentRegistry $registry,
    string $devaiRoot = '/dev/null',
    ?CommandRunnerInterface $runner = null,
): InstallationOrchestrator {
    $walker = makeNullWalker();

    return new InstallationOrchestrator(
        registry: $registry,
        agentsRenderer: new AgentsMdRenderer(),
        claudeRenderer: new ClaudeMdRenderer(),
        guidelinesAggregator: new GuidelinesAggregator($walker, $devaiRoot),
        skillsDistributor: new SkillsDistributor($walker, $devaiRoot),
        runner: $runner ?? makeRecordingRunner(),
    );
}

beforeEach(function (): void {
    $this->tempRoot = sys_get_temp_dir() . '/devai-install-test-' . uniqid();
    mkdir($this->tempRoot, 0755, true);
});

afterEach(function (): void {
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tempRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iter as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($this->tempRoot);
});

it('writes a marker on successful install', function (): void {
    $agent = makeInstallFullAgent(installed: true);
    $registry = makeInstallRegistry(['test-agent' => $agent]);
    $orchestrator = makeInstallOrchestrator($registry);

    $ctx = new InstallationContext(selectedAgents: ['test-agent']);

    $result = $orchestrator->install($ctx, $this->tempRoot, false);

    expect($result['status'])->toBe('installed');

    $marker = json_decode((string) file_get_contents($this->tempRoot . '/.marko/devai.json'), true);
    expect($marker['agents'])->toBe(['test-agent'])
        ->and($marker)->toHaveKey('installedAt')
        ->and($marker)->not->toHaveKey('docsDriver');
});

it('writes or updates .gitignore entries for generated files if user opts in', function (): void {
    $registry = makeInstallRegistry([]);
    $orchestrator = makeInstallOrchestrator($registry);

    $ctx = new InstallationContext(
        selectedAgents: [],
        updateGitignore: true,
    );

    $orchestrator->install($ctx, $this->tempRoot, false);

    $gitignorePath = $this->tempRoot . '/.gitignore';
    expect(file_exists($gitignorePath))->toBeTrue();

    $contents = (string) file_get_contents($gitignorePath);
    expect($contents)->toContain('# marko/devai generated files')
        ->and($contents)->toContain('.marko/');
});

it('does not write .gitignore when user does not opt in', function (): void {
    $registry = makeInstallRegistry([]);
    $orchestrator = makeInstallOrchestrator($registry);

    $ctx = new InstallationContext(
        selectedAgents: [],
        updateGitignore: false,
    );

    $orchestrator->install($ctx, $this->tempRoot, false);

    expect(file_exists($this->tempRoot . '/.gitignore'))->toBeFalse();
});

it('does not duplicate .gitignore entries on repeated installs', function (): void {
    $registry = makeInstallRegistry([]);
    $orchestrator = makeInstallOrchestrator($registry);

    $ctx = new InstallationContext(
        selectedAgents: [],
        updateGitignore: true,
    );

    $orchestrator->install($ctx, $this->tempRoot, false);
    $orchestrator->install($ctx, $this->tempRoot, true);

    $contents = (string) file_get_contents($this->tempRoot . '/.gitignore');
    expect(substr_count($contents, '.marko/'))->toBe(1);
});

it(
    'writes .marko/devai.json on successful install capturing selected agents',
    function (): void {
        $registry = makeInstallRegistry([]);
        $orchestrator = makeInstallOrchestrator($registry);

        $ctx = new InstallationContext(selectedAgents: ['claude-code', 'codex']);

        $orchestrator->install($ctx, $this->tempRoot, false);

        $markerPath = $this->tempRoot . '/.marko/devai.json';
        expect(file_exists($markerPath))->toBeTrue();

        $marker = json_decode((string) file_get_contents($markerPath), true);
        expect($marker['agents'])->toBe(['claude-code', 'codex'])
            ->and($marker)->toHaveKey('installedAt')
            ->and($marker)->toHaveKey('shippedSkills')
            ->and($marker['shippedSkills'])->toBeArray()
            ->and($marker)->not->toHaveKey('docsDriver');
    }
);

it('passes previously-shipped skills from the prior marker to each agent on update', function (): void {
    // Simulate a prior install where devai shipped 'old-skill' and 'still-here'
    mkdir($this->tempRoot . '/.marko', 0755, true);
    file_put_contents(
        $this->tempRoot . '/.marko/devai.json',
        json_encode([
            'agents' => ['test-agent'],
            'shippedSkills' => ['old-skill', 'still-here'],
            'installedAt' => '2026-01-01T00:00:00+00:00',
        ]),
    );

    $agent = makeInstallFullAgent(installed: true);
    $registry = makeInstallRegistry(['test-agent' => $agent]);
    $orchestrator = makeInstallOrchestrator($registry);

    $orchestrator->install(
        new InstallationContext(selectedAgents: ['test-agent']),
        $this->tempRoot,
        true, // force, since marker exists
    );

    [$bundles, , $previouslyShipped] = $agent->skillsCalls[0];

    expect($previouslyShipped)->toBe(['old-skill', 'still-here']);

    // The new marker reflects what devai actually shipped this run, not the prior list
    $newMarker = json_decode((string) file_get_contents($this->tempRoot . '/.marko/devai.json'), true);
    expect($newMarker)->toHaveKey('shippedSkills')
        ->and($newMarker['shippedSkills'])->toBeArray();
});

it('treats first install as having no previously-shipped skills', function (): void {
    $agent = makeInstallFullAgent(installed: true);
    $registry = makeInstallRegistry(['test-agent' => $agent]);
    $orchestrator = makeInstallOrchestrator($registry);

    $orchestrator->install(
        new InstallationContext(selectedAgents: ['test-agent']),
        $this->tempRoot,
        false,
    );

    [, , $previouslyShipped] = $agent->skillsCalls[0];

    expect($previouslyShipped)->toBe([]);
});

it('supports a --force flag to re-run from scratch (overwrites all generated files)', function (): void {
    // Pre-create the marker file
    mkdir($this->tempRoot . '/.marko', 0755, true);
    file_put_contents($this->tempRoot . '/.marko/devai.json', json_encode(['agents' => []]));

    $registry = makeInstallRegistry([]);
    $orchestrator = makeInstallOrchestrator($registry);

    $ctx = new InstallationContext(selectedAgents: ['claude-code']);

    // Without force: skipped
    $result = $orchestrator->install($ctx, $this->tempRoot, false);
    expect($result['status'])->toBe('skipped');

    // With force: proceeds
    $result = $orchestrator->install($ctx, $this->tempRoot, true);
    expect($result['status'])->toBe('installed');

    // Verify marker was overwritten with the new selected agents
    $marker = json_decode((string) file_get_contents($this->tempRoot . '/.marko/devai.json'), true);
    expect($marker['agents'])->toBe(['claude-code']);
});

it(
    'detects a prior install by reading .marko/devai.json and early-exits with a helpful message pointing the user to devai:update',
    function (): void {
        mkdir($this->tempRoot . '/.marko', 0755, true);
        file_put_contents(
            $this->tempRoot . '/.marko/devai.json',
            json_encode(['agents' => []]),
        );

        $registry = makeInstallRegistry([]);
        $orchestrator = makeInstallOrchestrator($registry);

        $ctx = new InstallationContext(selectedAgents: []);

        $result = $orchestrator->install($ctx, $this->tempRoot, false);

        expect($result['status'])->toBe('skipped')
            ->and($result['message'])->toContain('devai:update');
    }
);

it('prints a summary of changes made', function (): void {
    $agent = makeInstallFullAgent(installed: true);
    $registry = makeInstallRegistry(['test-agent' => $agent]);
    $orchestrator = makeInstallOrchestrator($registry);

    $ctx = new InstallationContext(selectedAgents: ['test-agent']);

    $result = $orchestrator->install($ctx, $this->tempRoot, false);

    expect($result['status'])->toBe('installed')
        ->and($result['log'])->toBeArray()
        ->and($result['log'])->not->toBeEmpty();

    $log = implode("\n", $result['log']);
    expect($log)->toContain('[test-agent] wrote guidelines')
        ->and($log)->toContain('[test-agent] registered MCP server')
        ->and($log)->toContain('[test-agent] registered LSP server')
        ->and($log)->toContain('[test-agent] distributed');
});

it('invokes each selected adapter writeGuidelines registerMcp registerLsp distributeSkills', function (): void {
    $agent = makeInstallFullAgent(installed: true);
    $registry = makeInstallRegistry(['test-agent' => $agent]);
    $orchestrator = makeInstallOrchestrator($registry);

    $ctx = new InstallationContext(selectedAgents: ['test-agent']);

    $orchestrator->install($ctx, $this->tempRoot, false);

    expect($agent->guidelinesCalls)->toHaveCount(1)
        ->and($agent->mcpCalls)->toHaveCount(1)
        ->and($agent->lspCalls)->toHaveCount(1)
        ->and($agent->skillsCalls)->toHaveCount(1);
});

it('registers MCP and LSP servers using the absolute path to vendor/bin/marko', function (): void {
    // Regression: registering `php marko mcp:serve` blew up at spawn time because
    // there is no `marko` file at the project root — the binary lives in
    // vendor/bin/marko. Agents (Claude Code, Cursor, etc.) spawn the MCP/LSP
    // server with no PATH guarantee and no guarantee about cwd, so the
    // registration must use an absolute path.
    $agent = makeInstallFullAgent(installed: true);
    $registry = makeInstallRegistry(['test-agent' => $agent]);
    $orchestrator = makeInstallOrchestrator($registry);

    $ctx = new InstallationContext(selectedAgents: ['test-agent']);

    $orchestrator->install($ctx, $this->tempRoot, false);

    [$mcpReg] = $agent->mcpCalls[0];
    [$lspReg] = $agent->lspCalls[0];

    $expectedBin = $this->tempRoot . '/vendor/bin/marko';

    expect($mcpReg->command)->toBe($expectedBin)
        ->and($mcpReg->args)->toBe(['mcp:serve'])
        ->and($lspReg->command)->toBe($expectedBin)
        ->and($lspReg->args)->toBe(['lsp:serve']);
});

it('runs docs-fts:build during install when marko/docs-fts is in vendor', function (): void {
    // marko/devai now hard-requires marko/docs-fts, so it lives in vendor/
    // by the time devai:install runs. The orchestrator must build the index
    // so search_docs is queryable as soon as the install command returns.
    mkdir($this->tempRoot . '/vendor/marko/docs-fts', 0755, true);

    $runner = makeRecordingRunner();
    $registry = makeInstallRegistry([]);
    $orchestrator = makeInstallOrchestrator($registry, runner: $runner);

    $orchestrator->install(
        new InstallationContext(selectedAgents: []),
        $this->tempRoot,
        false,
    );

    $buildCall = null;
    foreach ($runner->calls as $call) {
        if (in_array('docs-fts:build', $call[1], true)) {
            $buildCall = $call;
            break;
        }
    }

    expect($buildCall)->not->toBeNull()
        ->and($buildCall[0])->toBe($this->tempRoot . '/vendor/bin/marko');
});

it('runs docs-vec:build instead when marko/docs-vec replaced docs-fts', function (): void {
    // When the user upgrades to marko/docs-vec, Composer's `replace` removes
    // docs-fts. The orchestrator must build the vec index, not fts.
    mkdir($this->tempRoot . '/vendor/marko/docs-vec', 0755, true);

    $runner = makeRecordingRunner();
    $registry = makeInstallRegistry([]);
    $orchestrator = makeInstallOrchestrator($registry, runner: $runner);

    $orchestrator->install(
        new InstallationContext(selectedAgents: []),
        $this->tempRoot,
        false,
    );

    $buildCommands = array_map(fn ($c) => $c[1][0] ?? null, $runner->calls);

    expect($buildCommands)->toContain('docs-vec:build')
        ->and($buildCommands)->not->toContain('docs-fts:build');
});

it('skips the docs index build when no driver is installed', function (): void {
    $runner = makeRecordingRunner();
    $registry = makeInstallRegistry([]);
    $orchestrator = makeInstallOrchestrator($registry, runner: $runner);

    $orchestrator->install(
        new InstallationContext(selectedAgents: []),
        $this->tempRoot,
        false,
    );

    $buildCommands = array_map(fn ($c) => $c[1][0] ?? null, $runner->calls);

    expect($buildCommands)->not->toContain('docs-fts:build')
        ->and($buildCommands)->not->toContain('docs-vec:build');
});

it('records a helpful log line when the docs index build fails', function (): void {
    mkdir($this->tempRoot . '/vendor/marko/docs-fts', 0755, true);

    $runner = new class () implements CommandRunnerInterface
    {
        public function run(
            string $command,
            array $args = [],
        ): array {
            return ['exitCode' => 1, 'stdout' => '', 'stderr' => 'permission denied writing index'];
        }

        public function isOnPath(string $binary): bool
        {
            return false;
        }
    };

    $registry = makeInstallRegistry([]);
    $orchestrator = makeInstallOrchestrator($registry, runner: $runner);

    $result = $orchestrator->install(
        new InstallationContext(selectedAgents: []),
        $this->tempRoot,
        false,
    );

    $log = implode("\n", $result['log'] ?? []);
    expect($log)->toContain('docs-fts')
        ->and($log)->toContain('build failed')
        ->and($log)->toContain('permission denied');
});

it('detects installed agents and presents them as a checkbox picker', function (): void {
    $installedAgent = makeInstallFullAgent(installed: true);
    $notInstalledAgent = makeInstallFullAgent(installed: false);

    $registry = makeInstallRegistry(['installed-agent' => $installedAgent, 'missing-agent' => $notInstalledAgent]);

    $allAgents = $registry->all($this->tempRoot);
    $detected = array_keys(array_filter($allAgents, fn ($a) => $a->isInstalled()));

    expect($detected)->toBe(['installed-agent'])
        ->and(in_array('missing-agent', $detected))->toBeFalse();
});
