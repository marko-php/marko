<?php

declare(strict_types=1);

use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\Guidelines\GuidelinesAggregator;
use Marko\DevAi\Installation\AgentRegistry;
use Marko\DevAi\Installation\InstallationContext;
use Marko\DevAi\Installation\InstallationOrchestrator;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\Rendering\AgentsMdRenderer;
use Marko\DevAi\Skills\SkillsDistributor;
use Marko\DevAi\Writing\GuidelinesWriter;

/**
 * An AgentInterface spy: records the context and root it was installed with.
 */
function makeInstallSpyAgent(bool $installed = false): AgentInterface
{
    return new class ($installed) implements AgentInterface
    {
        public ?InstallationContext $installedCtx = null;

        public ?string $installedRoot = null;

        public int $installCount = 0;

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

        public function install(
            InstallationContext $ctx,
            string $projectRoot,
        ): void {
            $this->installedCtx = $ctx;
            $this->installedRoot = $projectRoot;
            $this->installCount++;
        }
    };
}

/** @param array<string, AgentInterface> $agents */
function makeInstallRegistry(array $agents): AgentRegistry
{
    return new class (devaiRunner(), $agents) extends AgentRegistry
    {
        /** @param array<string, AgentInterface> $agentMap */
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

/** A CommandRunner that records every call as [$command, $args]. */
function makeRecordingRunner(): CommandRunnerInterface
{
    return new class () implements CommandRunnerInterface
    {
        /** @var list<array{string, list<string>}> */
        public array $calls = [];

        public function run(
            string $command,
            array $args = [],
        ): array {
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
    $walker = devaiWalker();

    return new InstallationOrchestrator(
        registry: $registry,
        agentsRenderer: new AgentsMdRenderer(),
        guidelinesAggregator: new GuidelinesAggregator($walker, $devaiRoot),
        skillsDistributor: new SkillsDistributor($walker, $devaiRoot),
        runner: $runner ?? makeRecordingRunner(),
    );
}

beforeEach(function (): void {
    $this->tempRoot = devaiTempDir();
});

afterEach(function (): void {
    devaiRemoveDir($this->tempRoot);
});

it('writes a marker on successful install', function (): void {
    $agent = makeInstallSpyAgent(installed: true);
    $orchestrator = makeInstallOrchestrator(makeInstallRegistry(['test-agent' => $agent]));

    $result = $orchestrator->install(new InstallationContext(selectedAgents: ['test-agent']), $this->tempRoot);

    expect($result['status'])->toBe('installed');

    $marker = json_decode((string) file_get_contents($this->tempRoot . '/.marko/devai.json'), true);
    expect($marker['agents'])->toBe(['test-agent'])
        ->and($marker)->toHaveKey('installedAt')
        ->and($marker)->not->toHaveKey('docsDriver');
});

it('writes or updates .gitignore entries for generated files if user opts in', function (): void {
    $orchestrator = makeInstallOrchestrator(makeInstallRegistry([]));

    $orchestrator->install(new InstallationContext(selectedAgents: [], updateGitignore: true), $this->tempRoot);

    $contents = (string) file_get_contents($this->tempRoot . '/.gitignore');
    expect($contents)->toContain('# marko/devai generated files')
        ->and($contents)->toContain('.marko/');
});

it('does not write .gitignore when user does not opt in', function (): void {
    $orchestrator = makeInstallOrchestrator(makeInstallRegistry([]));

    $orchestrator->install(new InstallationContext(selectedAgents: [], updateGitignore: false), $this->tempRoot);

    expect(file_exists($this->tempRoot . '/.gitignore'))->toBeFalse();
});

it('does not duplicate .gitignore entries on repeated installs', function (): void {
    $orchestrator = makeInstallOrchestrator(makeInstallRegistry([]));

    $orchestrator->install(new InstallationContext(selectedAgents: [], updateGitignore: true), $this->tempRoot);
    $orchestrator->install(
        new InstallationContext(selectedAgents: [], force: true, updateGitignore: true),
        $this->tempRoot,
    );

    $contents = (string) file_get_contents($this->tempRoot . '/.gitignore');
    expect(substr_count($contents, '.marko/'))->toBe(1);
});

it('writes .marko/devai.json on successful install capturing selected agents', function (): void {
    $orchestrator = makeInstallOrchestrator(makeInstallRegistry([]));

    $orchestrator->install(new InstallationContext(selectedAgents: ['claude-code', 'codex']), $this->tempRoot);

    $marker = json_decode((string) file_get_contents($this->tempRoot . '/.marko/devai.json'), true);
    expect($marker['agents'])->toBe(['claude-code', 'codex'])
        ->and($marker)->toHaveKey('installedAt')
        ->and($marker)->toHaveKey('shippedSkills')
        ->and($marker['shippedSkills'])->toBeArray()
        ->and($marker)->not->toHaveKey('docsDriver');
});

it('passes previously-shipped skills from the prior marker to each agent on update', function (): void {
    mkdir($this->tempRoot . '/.marko', 0755, true);
    file_put_contents(
        $this->tempRoot . '/.marko/devai.json',
        json_encode([
            'agents' => ['test-agent'],
            'shippedSkills' => ['old-skill', 'still-here'],
            'installedAt' => '2026-01-01T00:00:00+00:00',
        ]),
    );

    $agent = makeInstallSpyAgent(installed: true);
    $orchestrator = makeInstallOrchestrator(makeInstallRegistry(['test-agent' => $agent]));

    $orchestrator->install(
        new InstallationContext(selectedAgents: ['test-agent'], force: true),
        $this->tempRoot,
    );

    expect($agent->installedCtx->previouslyShipped)->toBe(['old-skill', 'still-here']);

    $newMarker = json_decode((string) file_get_contents($this->tempRoot . '/.marko/devai.json'), true);
    expect($newMarker)->toHaveKey('shippedSkills')
        ->and($newMarker['shippedSkills'])->toBeArray();
});

it('treats first install as having no previously-shipped skills', function (): void {
    $agent = makeInstallSpyAgent(installed: true);
    $orchestrator = makeInstallOrchestrator(makeInstallRegistry(['test-agent' => $agent]));

    $orchestrator->install(new InstallationContext(selectedAgents: ['test-agent']), $this->tempRoot);

    expect($agent->installedCtx->previouslyShipped)->toBeEmpty();
});

it('supports a --force flag to re-run from scratch (overwrites all generated files)', function (): void {
    mkdir($this->tempRoot . '/.marko', 0755, true);
    file_put_contents($this->tempRoot . '/.marko/devai.json', json_encode(['agents' => []]));

    $orchestrator = makeInstallOrchestrator(makeInstallRegistry([]));

    $result = $orchestrator->install(new InstallationContext(selectedAgents: ['claude-code']), $this->tempRoot);
    expect($result['status'])->toBe('skipped');

    $result = $orchestrator->install(
        new InstallationContext(selectedAgents: ['claude-code'], force: true),
        $this->tempRoot,
    );
    expect($result['status'])->toBe('installed');

    $marker = json_decode((string) file_get_contents($this->tempRoot . '/.marko/devai.json'), true);
    expect($marker['agents'])->toBe(['claude-code']);
});

it('detects a prior install and early-exits pointing the user to devai:update', function (): void {
    mkdir($this->tempRoot . '/.marko', 0755, true);
    file_put_contents($this->tempRoot . '/.marko/devai.json', json_encode(['agents' => []]));

    $orchestrator = makeInstallOrchestrator(makeInstallRegistry([]));

    $result = $orchestrator->install(new InstallationContext(selectedAgents: []), $this->tempRoot);

    expect($result['status'])->toBe('skipped')
        ->and($result['message'])->toContain('devai:update');
});

it('prints a per-agent install summary', function (): void {
    $agent = makeInstallSpyAgent(installed: true);
    $orchestrator = makeInstallOrchestrator(makeInstallRegistry(['test-agent' => $agent]));

    $result = $orchestrator->install(new InstallationContext(selectedAgents: ['test-agent']), $this->tempRoot);

    expect($result['status'])->toBe('installed')
        ->and($result['log'])->toBeArray()
        ->and($result['log'])->not->toBeEmpty()
        ->and(implode("\n", $result['log']))->toContain('[test-agent] installed');
});

it('invokes install() once per selected agent', function (): void {
    $agent = makeInstallSpyAgent(installed: true);
    $orchestrator = makeInstallOrchestrator(makeInstallRegistry(['test-agent' => $agent]));

    $orchestrator->install(new InstallationContext(selectedAgents: ['test-agent']), $this->tempRoot);

    expect($agent->installCount)->toBe(1)
        ->and($agent->installedRoot)->toBe($this->tempRoot);
});

it('builds the MCP registration using the absolute path to vendor/bin/marko', function (): void {
    // Regression: registering `php marko mcp:serve` blew up at spawn time because
    // there is no `marko` file at the project root — the binary lives in
    // vendor/bin/marko. Agents spawn the MCP server with no PATH guarantee and
    // no guarantee about cwd, so the registration must use an absolute path.
    $agent = makeInstallSpyAgent(installed: true);
    $orchestrator = makeInstallOrchestrator(makeInstallRegistry(['test-agent' => $agent]));

    $orchestrator->install(new InstallationContext(selectedAgents: ['test-agent']), $this->tempRoot);

    $mcpReg = $agent->installedCtx->mcpRegistration;
    expect($mcpReg->command)->toBe($this->tempRoot . '/vendor/bin/marko')
        ->and($mcpReg->args)->toBe(['mcp:serve']);
});

it('runs docs-fts:build during install when marko/docs-fts is in vendor', function (): void {
    mkdir($this->tempRoot . '/vendor/marko/docs-fts', 0755, true);

    $runner = makeRecordingRunner();
    $orchestrator = makeInstallOrchestrator(makeInstallRegistry([]), runner: $runner);

    $orchestrator->install(new InstallationContext(selectedAgents: []), $this->tempRoot);

    $buildCall = array_find(
        $runner->calls,
        fn ($call) => in_array('docs-fts:build', $call[1], true),
    );

    expect($buildCall)->not->toBeNull()
        ->and($buildCall[0])->toBe($this->tempRoot . '/vendor/bin/marko');
});

it('runs docs-vec:build instead when the docs-vec driver is installed', function (): void {
    // docs-fts and docs-vec are independent sibling drivers (the replace
    // mechanism was removed). When docs-vec is the installed driver, the
    // orchestrator must build the vec index, not fts.
    mkdir($this->tempRoot . '/vendor/marko/docs-vec', 0755, true);

    $runner = makeRecordingRunner();
    $orchestrator = makeInstallOrchestrator(makeInstallRegistry([]), runner: $runner);

    $orchestrator->install(new InstallationContext(selectedAgents: []), $this->tempRoot);

    $buildCommands = array_map(fn ($c) => $c[1][0] ?? null, $runner->calls);

    expect($buildCommands)->toContain('docs-vec:build')
        ->and($buildCommands)->not->toContain('docs-fts:build');
});

it('skips the docs index build when no driver is installed', function (): void {
    $runner = makeRecordingRunner();
    $orchestrator = makeInstallOrchestrator(makeInstallRegistry([]), runner: $runner);

    $result = $orchestrator->install(new InstallationContext(selectedAgents: []), $this->tempRoot);

    $buildCommands = array_map(fn ($c) => $c[1][0] ?? null, $runner->calls);

    expect($buildCommands)->not->toContain('docs-fts:build')
        ->and($buildCommands)->not->toContain('docs-vec:build');

    // A bare install is valid — but it should tell the user how to enable search.
    expect(implode("\n", $result['log'] ?? []))->toContain('no search driver installed');
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

    $orchestrator = makeInstallOrchestrator(makeInstallRegistry([]), runner: $runner);

    $result = $orchestrator->install(new InstallationContext(selectedAgents: []), $this->tempRoot);

    $log = implode("\n", $result['log'] ?? []);
    expect($log)->toContain('docs-fts')
        ->and($log)->toContain('build failed')
        ->and($log)->toContain('permission denied');
});

it('surfaces a loud notice in the install log when a guideline file has its markers stripped', function (): void {
    // Create a guideline file with markers stripped (no markers)
    file_put_contents($this->tempRoot . '/AGENTS.md', "# My custom AGENTS\n\nNo markers here.\n");

    $agent = makeInstallSpyAgent(installed: true);

    // Use a spy agent that writes to AGENTS.md via the real GuidelinesWriter path
    // We simulate the stripped-markers scenario by having a pre-existing file without markers
    // and an agent that calls GuidelinesWriter::write() on it.
    $writingAgent = new class ($this->tempRoot) implements AgentInterface
    {
        public function __construct(private string $root) {}

        public function name(): string
        {
            return 'writing-agent';
        }

        public function displayName(): string
        {
            return 'Writing Agent';
        }

        public function isInstalled(): bool
        {
            return true;
        }

        public function install(InstallationContext $ctx, string $projectRoot): void
        {
            GuidelinesWriter::write($projectRoot . '/AGENTS.md', 'new content');
        }
    };

    $orchestrator = makeInstallOrchestrator(makeInstallRegistry(['writing-agent' => $writingAgent]));

    $result = $orchestrator->install(new InstallationContext(selectedAgents: ['writing-agent']), $this->tempRoot);

    $log = implode("\n", $result['log'] ?? []);
    expect($log)->toContain('does not contain marko:devai markers');
});

it('detects installed agents and filters out missing ones', function (): void {
    $registry = makeInstallRegistry([
        'installed-agent' => makeInstallSpyAgent(installed: true),
        'missing-agent' => makeInstallSpyAgent(installed: false),
    ]);

    $detected = array_keys(array_filter($registry->all($this->tempRoot), fn ($a) => $a->isInstalled()));

    expect($detected)->toBe(['installed-agent']);
});
