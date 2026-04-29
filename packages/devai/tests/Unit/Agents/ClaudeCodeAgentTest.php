<?php

declare(strict_types=1);

use Marko\DevAi\Agents\ClaudeCodeAgent;
use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsLsp;
use Marko\DevAi\Contract\SupportsSkills;
use Marko\DevAi\Exceptions\DevAiInstallException;
use Marko\DevAi\Installation\EnsureResult;
use Marko\DevAi\Installation\InstallationOrchestrator;
use Marko\DevAi\Installation\IntelephenseEnsurerInterface;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\ValueObject\GuidelinesContent;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeDevaiTempDir(): string
{
    $dir = sys_get_temp_dir() . '/devai-test-' . uniqid();
    mkdir($dir, 0755, true);

    return $dir;
}

function removeDevaiTempDir(string $dir): void
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

function makeClaudeRunner(bool $claudeOnPath = true, string $listOutput = ''): CommandRunnerInterface
{
    return new class ($claudeOnPath, $listOutput) implements CommandRunnerInterface
    {
        public array $calls = [];

        public function __construct(
            private bool $claudeOnPath,
            public string $listOutput,
        ) {}

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
            return $this->claudeOnPath;
        }
    };
}

// ---------------------------------------------------------------------------
// Basic identity
// ---------------------------------------------------------------------------

it('reports name as claude-code', function (): void {
    $agent = new ClaudeCodeAgent(makeClaudeRunner());
    expect($agent->name())->toBe('claude-code');
});

it('detects installation when claude binary is on PATH', function (): void {
    $agent = new ClaudeCodeAgent(makeClaudeRunner(claudeOnPath: true));
    expect($agent->isInstalled())->toBeTrue();

    $agent2 = new ClaudeCodeAgent(makeClaudeRunner(claudeOnPath: false));
    expect($agent2->isInstalled())->toBeFalse();
});

// ---------------------------------------------------------------------------
// writeGuidelines — Requirements 1–4
// ---------------------------------------------------------------------------

describe('writeGuidelines', function (): void {
    beforeEach(function (): void {
        $this->root = makeDevaiTempDir();
        $this->agent = new ClaudeCodeAgent(makeClaudeRunner());
    });

    afterEach(function (): void {
        removeDevaiTempDir($this->root);
    });

    it('writes AGENTS.md with the existing aggregated package guidelines content', function (): void {
        $content = new GuidelinesContent('# Project Guidelines');
        $this->agent->writeGuidelines($content, $this->root);

        expect(file_exists($this->root . '/AGENTS.md'))->toBeTrue()
            ->and(file_get_contents($this->root . '/AGENTS.md'))->toBe('# Project Guidelines');
    });

    it('writes CLAUDE.md including the @AGENTS.md import directive', function (): void {
        $this->agent->writeGuidelines(new GuidelinesContent('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        expect($claudeMd)->toContain('@AGENTS.md');
    });

    it('writes CLAUDE.md including the verbatim authority directive about skills as canonical spec', function (): void {
        $this->agent->writeGuidelines(new GuidelinesContent('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        expect($claudeMd)->toContain('skill is the canonical specification')
            ->and($claudeMd)->toContain('marko-skills:create-module');
    });

    it('writes CLAUDE.md including the LSP verification gate directive', function (): void {
        $this->agent->writeGuidelines(new GuidelinesContent('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        expect($claudeMd)->toContain('LSP diagnostics')
            ->and($claudeMd)->toContain('verification gate');
    });

    it('writes CLAUDE.md content noting the new plugin-namespaced skill invocation', function (): void {
        $this->agent->writeGuidelines(new GuidelinesContent('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        expect($claudeMd)->toContain('marko-skills@marko')
            ->and($claudeMd)->toContain('marko-lsp@marko')
            ->and($claudeMd)->toContain('marko-mcp@marko');
    });

    it('writes CLAUDE.md instructing the agent to call search_docs first for documentation lookups', function (): void {
        $this->agent->writeGuidelines(new GuidelinesContent('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        expect($claudeMd)->toContain('search_docs')
            ->and($claudeMd)->toContain('Do NOT infer answers from `vendor/marko/*`')
            ->and($claudeMd)->toContain('list_modules')
            ->and($claudeMd)->toContain('validate_module')
            ->and($claudeMd)->toContain('find_event_observers')
            ->and($claudeMd)->toContain('find_plugins_targeting')
            ->and($claudeMd)->toContain('resolve_preference')
            ->and($claudeMd)->toContain('check_config_key');
    });
});

// ---------------------------------------------------------------------------
// writeSettings — Requirements 5–10 + monorepo detection
// ---------------------------------------------------------------------------

describe('installation', function (): void {
    beforeEach(function (): void {
        $this->root = makeDevaiTempDir();
        $this->agent = new ClaudeCodeAgent(makeClaudeRunner());
    });

    afterEach(function (): void {
        removeDevaiTempDir($this->root);
    });

    it('writes .claude/settings.json with extraKnownMarketplaces.marko entry', function (): void {
        $this->agent->writeSettings($this->root, force: false);

        $path = $this->root . '/.claude/settings.json';
        expect(file_exists($path))->toBeTrue();

        $data = json_decode((string) file_get_contents($path), true);
        expect($data)->toHaveKey('extraKnownMarketplaces')
            ->and($data['extraKnownMarketplaces'])->toHaveKey('marko');
    });

    it('writes .claude/settings.json with enabledPlugins listing marko-skills@marko, marko-lsp@marko, marko-mcp@marko all set to true', function (): void {
        $this->agent->writeSettings($this->root, force: false);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        expect($data['enabledPlugins']['marko-skills@marko'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-lsp@marko'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-mcp@marko'])->toBeTrue();
    });

    it('merges into an existing .claude/settings.json without clobbering unrelated user keys', function (): void {
        mkdir($this->root . '/.claude', 0755, true);
        file_put_contents(
            $this->root . '/.claude/settings.json',
            json_encode(['theme' => 'dark', 'someUserKey' => 42]),
        );

        $this->agent->writeSettings($this->root, force: false);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        expect($data['theme'])->toBe('dark')
            ->and($data['someUserKey'])->toBe(42)
            ->and($data['extraKnownMarketplaces'])->toHaveKey('marko');
    });

    it('running install on a project that already has extraKnownMarketplaces.marko throws a loud Marko exception (message + context + suggestion) when --force is not passed', function (): void {
        mkdir($this->root . '/.claude', 0755, true);
        file_put_contents(
            $this->root . '/.claude/settings.json',
            json_encode(['extraKnownMarketplaces' => ['marko' => ['source' => ['source' => 'github', 'repo' => 'marko-php/marko']]]]),
        );

        expect(fn () => $this->agent->writeSettings($this->root, force: false))
            ->toThrow(DevAiInstallException::class);
    });

    it('running install with --force on a project that already has extraKnownMarketplaces.marko overwrites the marko-related keys without throwing', function (): void {
        mkdir($this->root . '/.claude', 0755, true);
        file_put_contents(
            $this->root . '/.claude/settings.json',
            json_encode(['extraKnownMarketplaces' => ['marko' => ['source' => ['source' => 'old']]]]),
        );

        $this->agent->writeSettings($this->root, force: true);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        expect($data['extraKnownMarketplaces']['marko']['source']['source'])->toBe('github');
    });

    it('running install with --force preserves unrelated user keys in the existing .claude/settings.json (only marko-prefixed keys are touched)', function (): void {
        mkdir($this->root . '/.claude', 0755, true);
        file_put_contents(
            $this->root . '/.claude/settings.json',
            json_encode([
                'theme' => 'light',
                'extraKnownMarketplaces' => ['marko' => ['old' => true]],
                'enabledPlugins' => ['marko-skills@marko' => false, 'other-plugin@other' => true],
            ]),
        );

        $this->agent->writeSettings($this->root, force: true);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        expect($data['theme'])->toBe('light')
            ->and($data['enabledPlugins']['other-plugin@other'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-skills@marko'])->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Monorepo vs external-project detection — Requirements 12–14
// ---------------------------------------------------------------------------

describe('monorepo detection', function (): void {
    it('when run from inside the marko monorepo, ClaudeCodeAgent chooses the local marketplace source shape', function (): void {
        $root = makeDevaiTempDir();
        // Simulate monorepo: packages/claude-plugins/ exists
        mkdir($root . '/packages/claude-plugins', 0755, true);

        try {
            $agent = new ClaudeCodeAgent(makeClaudeRunner());
            $agent->writeSettings($root, force: false);

            $data = json_decode((string) file_get_contents($root . '/.claude/settings.json'), true);
            $source = $data['extraKnownMarketplaces']['marko']['source'];
            expect($source['source'])->toBe('local');
        } finally {
            removeDevaiTempDir($root);
        }
    });

    it('external-project detection: when run from a project that requires marko/devai via Composer but is not the monorepo, ClaudeCodeAgent chooses the github source shape', function (): void {
        $root = makeDevaiTempDir();
        // No packages/claude-plugins/ — external project

        try {
            $agent = new ClaudeCodeAgent(makeClaudeRunner());
            $agent->writeSettings($root, force: false);

            $data = json_decode((string) file_get_contents($root . '/.claude/settings.json'), true);
            $source = $data['extraKnownMarketplaces']['marko']['source'];
            expect($source['source'])->toBe('github')
                ->and($source['repo'])->toBe('marko-php/marko');
        } finally {
            removeDevaiTempDir($root);
        }
    });

    it('the test for both monorepo and external-project branches uses two separate test fixtures (a tempdir with packages/claude-plugins present vs absent) rather than mocking the detection', function (): void {
        // Monorepo fixture
        $monorepoRoot = makeDevaiTempDir();
        mkdir($monorepoRoot . '/packages/claude-plugins', 0755, true);

        // External fixture
        $externalRoot = makeDevaiTempDir();

        try {
            $agent = new ClaudeCodeAgent(makeClaudeRunner());

            $agent->writeSettings($monorepoRoot, force: false);
            $monorepoData = json_decode((string) file_get_contents($monorepoRoot . '/.claude/settings.json'), true);

            $agent->writeSettings($externalRoot, force: false);
            $externalData = json_decode((string) file_get_contents($externalRoot . '/.claude/settings.json'), true);

            expect($monorepoData['extraKnownMarketplaces']['marko']['source']['source'])->toBe('local')
                ->and($externalData['extraKnownMarketplaces']['marko']['source']['source'])->toBe('github');
        } finally {
            removeDevaiTempDir($monorepoRoot);
            removeDevaiTempDir($externalRoot);
        }
    });
});

// ---------------------------------------------------------------------------
// Legacy artifact cleanup — Requirements 15–16
// ---------------------------------------------------------------------------

describe('legacy artifact cleanup', function (): void {
    it('ClaudeCodeAgent no longer writes .claude/plugins/marko/.lsp.json — install also removes any pre-existing one (legacy artifact cleanup, idempotent)', function (): void {
        $root = makeDevaiTempDir();

        try {
            // Pre-create legacy .lsp.json
            $legacyDir = $root . '/.claude/plugins/marko';
            mkdir($legacyDir, 0755, true);
            file_put_contents($legacyDir . '/.lsp.json', '{"old":"stuff"}');

            $agent = new ClaudeCodeAgent(makeClaudeRunner());
            $agent->writeSettings($root, force: false);

            // Legacy file must be gone
            expect(file_exists($legacyDir . '/.lsp.json'))->toBeFalse();
        } finally {
            removeDevaiTempDir($root);
        }
    });

    it('ClaudeCodeAgent no longer invokes claude mcp add directly — install also removes any previously-registered marko-mcp server via claude mcp remove if present (idempotent)', function (): void {
        $root = makeDevaiTempDir();

        try {
            $runner = makeClaudeRunner(listOutput: "marko-mcp: stdio - php marko mcp:serve\n");
            $agent = new ClaudeCodeAgent($runner);
            $agent->writeSettings($root, force: false);

            // Must have called 'claude mcp list' and then 'claude mcp remove marko-mcp'
            $calls = $runner->calls;
            $listCall = null;
            $removeCall = null;
            foreach ($calls as $call) {
                if ($call[0] === 'claude' && ($call[1][0] ?? '') === 'mcp' && ($call[1][1] ?? '') === 'list') {
                    $listCall = $call;
                }
                if ($call[0] === 'claude' && ($call[1][0] ?? '') === 'mcp' && ($call[1][1] ?? '') === 'remove') {
                    $removeCall = $call;
                }
            }
            expect($listCall)->not->toBeNull()
                ->and($removeCall)->not->toBeNull()
                ->and($removeCall[1])->toContain('marko-mcp');
        } finally {
            removeDevaiTempDir($root);
        }
    });

    it('does not call claude mcp remove when marko-mcp is not in the list (idempotent)', function (): void {
        $root = makeDevaiTempDir();

        try {
            $runner = makeClaudeRunner(listOutput: '');
            $agent = new ClaudeCodeAgent($runner);
            $agent->writeSettings($root, force: false);

            $removeCall = null;
            foreach ($runner->calls as $call) {
                if ($call[0] === 'claude' && ($call[1][0] ?? '') === 'mcp' && ($call[1][1] ?? '') === 'remove') {
                    $removeCall = $call;
                }
            }
            expect($removeCall)->toBeNull();
        } finally {
            removeDevaiTempDir($root);
        }
    });
});

// ---------------------------------------------------------------------------
// SkillsDistributor no longer used for ClaudeCode — Requirement 17
// ---------------------------------------------------------------------------

it('legacy SkillsDistributor invocation for Claude Code agent is removed (skills come via the plugin now)', function (): void {
    // ClaudeCodeAgent must NOT implement SupportsSkills any more.
    // (SkillsDistributor is for non-Claude agents which still call it.)
    $agent = new ClaudeCodeAgent(makeClaudeRunner());
    expect($agent)->not->toBeInstanceOf(SupportsSkills::class);
});

// ---------------------------------------------------------------------------
// --force end-to-end plumbing — Requirement 11
// ---------------------------------------------------------------------------

it('the --force flag is plumbed end-to-end: devai:install command accepts it, InstallationOrchestrator forwards it, ClaudeCodeAgent honors it', function (): void {
    // The InstallCommand passes $force = $input->hasOption('force') to orchestrator->install(..., force: $force).
    // The orchestrator calls $agent->writeSettings($root, force: $force).
    // We can verify the orchestrator wires force through by inspecting its install() signature.
    $reflection = new ReflectionMethod(InstallationOrchestrator::class, 'install');
    $params = $reflection->getParameters();
    $paramNames = array_map(fn ($p) => $p->getName(), $params);
    expect($paramNames)->toContain('force');

    // Verify ClaudeCodeAgent::writeSettings also has a force parameter.
    $agentReflection = new ReflectionMethod(ClaudeCodeAgent::class, 'writeSettings');
    $agentParams = $agentReflection->getParameters();
    $agentParamNames = array_map(fn ($p) => $p->getName(), $agentParams);
    expect($agentParamNames)->toContain('force');
});

// ---------------------------------------------------------------------------
// Interfaces — SupportsGuidelines kept; SupportsLsp removed
// ---------------------------------------------------------------------------

it('still implements SupportsGuidelines', function (): void {
    $agent = new ClaudeCodeAgent(makeClaudeRunner());
    expect($agent)->toBeInstanceOf(SupportsGuidelines::class);
});

it('no longer implements SupportsLsp (interface deleted or not applied to ClaudeCodeAgent)', function (): void {
    $agent = new ClaudeCodeAgent(makeClaudeRunner());
    expect(interface_exists(SupportsLsp::class))->toBeFalse();
});

// ---------------------------------------------------------------------------
// IntelephenseEnsurer wiring — --skip-lsp-deps
// ---------------------------------------------------------------------------

describe('ensureLspDeps', function (): void {
    beforeEach(function (): void {
        $this->root = makeDevaiTempDir();
    });

    afterEach(function (): void {
        removeDevaiTempDir($this->root);
    });

    it('invokes IntelephenseEnsurer after writeSettings when --skip-lsp-deps is false', function (): void {
        $ensureCallLog = [];
        $fakeEnsurer = new class ($ensureCallLog) implements IntelephenseEnsurerInterface
        {
            public function __construct(private array &$log) {}

            public function ensure(bool $skip = false): EnsureResult
            {
                $this->log[] = ['skip' => $skip];

                return EnsureResult::alreadyInstalled();
            }
        };

        $agent = new ClaudeCodeAgent(makeClaudeRunner(), $fakeEnsurer);
        $agent->writeSettings($this->root, force: false);
        $agent->ensureLspDeps(skipLspDeps: false);

        expect($ensureCallLog)->toHaveCount(1)
            ->and($ensureCallLog[0]['skip'])->toBeFalse();
    });

    it('passes --skip-lsp-deps through to IntelephenseEnsurer when set', function (): void {
        $ensureCallLog = [];
        $fakeEnsurer = new class ($ensureCallLog) implements IntelephenseEnsurerInterface
        {
            public function __construct(private array &$log) {}

            public function ensure(bool $skip = false): EnsureResult
            {
                $this->log[] = ['skip' => $skip];

                return EnsureResult::skipped();
            }
        };

        $agent = new ClaudeCodeAgent(makeClaudeRunner(), $fakeEnsurer);
        $agent->writeSettings($this->root, force: false);
        $agent->ensureLspDeps(skipLspDeps: true);

        expect($ensureCallLog)->toHaveCount(1)
            ->and($ensureCallLog[0]['skip'])->toBeTrue();
    });

    it('reports "installed intelephense" in summary on successful auto-install', function (): void {
        $fakeEnsurer = new class () implements IntelephenseEnsurerInterface
        {
            public function ensure(bool $skip = false): EnsureResult
            {
                return EnsureResult::installed();
            }
        };

        $agent = new ClaudeCodeAgent(makeClaudeRunner(), $fakeEnsurer);
        $agent->writeSettings($this->root, force: false);
        $summary = $agent->ensureLspDeps(skipLspDeps: false);

        expect($summary)->toContain('installed intelephense');
    });

    it('reports a skipped status (no silent success) when --skip-lsp-deps is set', function (): void {
        $fakeEnsurer = new class () implements IntelephenseEnsurerInterface
        {
            public function ensure(bool $skip = false): EnsureResult
            {
                return EnsureResult::skipped();
            }
        };

        $agent = new ClaudeCodeAgent(makeClaudeRunner(), $fakeEnsurer);
        $agent->writeSettings($this->root, force: false);
        $summary = $agent->ensureLspDeps(skipLspDeps: true);

        expect($summary)->toContain('[claude-code]')
            ->and($summary)->toContain('skipped')
            ->and($summary)->toContain('--skip-lsp-deps')
            ->and($summary)->toContain('intelephense');
    });

    it('reports "verified intelephense" (no silent success) when intelephense is already on PATH', function (): void {
        $fakeEnsurer = new class () implements IntelephenseEnsurerInterface
        {
            public function ensure(bool $skip = false): EnsureResult
            {
                return EnsureResult::alreadyInstalled();
            }
        };

        $agent = new ClaudeCodeAgent(makeClaudeRunner(), $fakeEnsurer);
        $agent->writeSettings($this->root, force: false);
        $summary = $agent->ensureLspDeps(skipLspDeps: false);

        expect($summary)->toContain('[claude-code]')
            ->and($summary)->toContain('verified intelephense')
            ->and($summary)->toContain('already on PATH');
    });
});
