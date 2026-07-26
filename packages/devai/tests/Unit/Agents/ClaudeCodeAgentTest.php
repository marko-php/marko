<?php

declare(strict_types=1);

use Marko\DevAi\Agents\ClaudeCodeAgent;
use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\Exceptions\DevAiInstallException;
use Marko\DevAi\Installation\EnsureResult;
use Marko\DevAi\Installation\IntelephenseEnsurerInterface;

// ---------------------------------------------------------------------------
// Basic identity
// ---------------------------------------------------------------------------

it('reports name as claude-code', function (): void {
    expect((new ClaudeCodeAgent(devaiRunner()))->name())->toBe('claude-code');
});

it('detects installation when claude binary is on PATH', function (): void {
    expect((new ClaudeCodeAgent(devaiRunner(onPath: true)))->isInstalled())->toBeTrue()
        ->and((new ClaudeCodeAgent(devaiRunner(onPath: false)))->isInstalled())->toBeFalse();
});

it('implements AgentInterface', function (): void {
    expect(new ClaudeCodeAgent(devaiRunner()))->toBeInstanceOf(AgentInterface::class);
});

// ---------------------------------------------------------------------------
// install() — guidelines
// ---------------------------------------------------------------------------

describe('guidelines', function (): void {
    beforeEach(function (): void {
        $this->root = devaiTempDir();
        $this->agent = new ClaudeCodeAgent(devaiRunner());
    });

    afterEach(function (): void {
        devaiRemoveDir($this->root);
    });

    it('writes AGENTS.md with the aggregated package guidelines content', function (): void {
        $this->agent->install(devaiContext('# Project Guidelines'), $this->root);

        expect(file_exists($this->root . '/AGENTS.md'))->toBeTrue()
            ->and(file_get_contents($this->root . '/AGENTS.md'))->toContain('# Project Guidelines');
    });

    it('creates AGENTS.md via the writer when it does not exist', function (): void {
        $this->agent->install(devaiContext('# Project Guidelines'), $this->root);

        $agentsMd = (string) file_get_contents($this->root . '/AGENTS.md');
        expect($agentsMd)->toContain('# Project Guidelines')
            ->and($agentsMd)->toContain('<!-- BEGIN marko:devai -->');
    });

    it('preserves user content outside the markers in an existing AGENTS.md', function (): void {
        $beginMarker = '<!-- BEGIN marko:devai -->';
        $endMarker = '<!-- END marko:devai -->';
        $existing = "# My Custom Header\n\n$beginMarker\nOld generated content\n$endMarker\n\n## My Footer\n";
        file_put_contents($this->root . '/AGENTS.md', $existing);

        $this->agent->install(devaiContext('# New Guidelines'), $this->root);

        $agentsMd = (string) file_get_contents($this->root . '/AGENTS.md');
        expect($agentsMd)->toContain('# My Custom Header')
            ->and($agentsMd)->toContain('## My Footer')
            ->and($agentsMd)->toContain('# New Guidelines');
    });

    it('creates CLAUDE.md with the AGENTS.md import inside the marker region', function (): void {
        $this->agent->install(devaiContext('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        $beginPos = strpos($claudeMd, '<!-- BEGIN marko:devai -->');
        $endPos = strpos($claudeMd, '<!-- END marko:devai -->');
        $importPos = strpos($claudeMd, '@AGENTS.md');

        expect($beginPos)->not->toBeFalse()
            ->and($endPos)->not->toBeFalse()
            ->and($importPos)->not->toBeFalse()
            ->and($importPos > $beginPos)->toBeTrue()
            ->and($importPos < $endPos)->toBeTrue();
    });

    it('wraps the Claude tooling block inside the marker region', function (): void {
        $this->agent->install(devaiContext('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        $beginPos = strpos($claudeMd, '<!-- BEGIN marko:devai -->');
        $endPos = strpos($claudeMd, '<!-- END marko:devai -->');
        $toolingPos = strpos($claudeMd, 'Marko AI tooling');

        expect($beginPos)->not->toBeFalse()
            ->and($endPos)->not->toBeFalse()
            ->and($toolingPos)->not->toBeFalse()
            ->and($toolingPos > $beginPos)->toBeTrue()
            ->and($toolingPos < $endPos)->toBeTrue();
    });

    it('leaves a marker-stripped CLAUDE.md untouched', function (): void {
        $noMarkerContent = "# My CLAUDE.md\n\nSome user content without any markers.\n";
        file_put_contents($this->root . '/CLAUDE.md', $noMarkerContent);

        $this->agent->install(devaiContext('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        expect($claudeMd)->toBe($noMarkerContent);
    });

    it('does not modify MCP or settings behavior', function (): void {
        $this->agent->install(devaiContext('body'), $this->root);

        $settingsPath = $this->root . '/.claude/settings.json';
        expect(file_exists($settingsPath))->toBeTrue();

        $data = json_decode((string) file_get_contents($settingsPath), true);
        expect($data)->toHaveKey('extraKnownMarketplaces')
            ->and($data['extraKnownMarketplaces'])->toHaveKey('marko')
            ->and($data['enabledPlugins']['marko-skills@marko'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-lsp@marko'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-mcp@marko'])->toBeTrue();
    });

    it('writes CLAUDE.md including the @AGENTS.md import directive', function (): void {
        $this->agent->install(devaiContext('body'), $this->root);

        expect((string) file_get_contents($this->root . '/CLAUDE.md'))->toContain('@AGENTS.md');
    });

    it(
        'writes CLAUDE.md including the verbatim authority directive about skills as canonical spec',
        function (): void {
            $this->agent->install(devaiContext('body'), $this->root);

            $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
            expect($claudeMd)->toContain('skill is the canonical specification')
                ->and($claudeMd)->toContain('marko-skills:create-module');
        },
    );

    it('writes CLAUDE.md including the LSP verification gate directive', function (): void {
        $this->agent->install(devaiContext('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        expect($claudeMd)->toContain('LSP diagnostics')
            ->and($claudeMd)->toContain('verification gate');
    });

    it('writes CLAUDE.md stating no reindex/build step is needed', function (): void {
        $this->agent->install(devaiContext('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        expect($claudeMd)->toContain('There is no build, compile, or reindex step')
            ->and($claudeMd)->toContain('discovered live from your source files')
            ->and($claudeMd)->toContain('Magento reflex Marko does not have');
    });

    it('writes CLAUDE.md noting the plugin-namespaced skill invocation', function (): void {
        $this->agent->install(devaiContext('body'), $this->root);

        $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
        expect($claudeMd)->toContain('marko-skills@marko')
            ->and($claudeMd)->toContain('marko-lsp@marko')
            ->and($claudeMd)->toContain('marko-mcp@marko');
    });

    it(
        'writes CLAUDE.md instructing the agent to call search_docs first for documentation lookups',
        function (): void {
            $this->agent->install(devaiContext('body'), $this->root);

            $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
            expect($claudeMd)->toContain('search_docs')
                ->and($claudeMd)->toContain('Do NOT infer answers from `vendor/marko/*`')
                ->and($claudeMd)->toContain('list_modules')
                ->and($claudeMd)->toContain('validate_module')
                ->and($claudeMd)->toContain('find_event_observers')
                ->and($claudeMd)->toContain('find_plugins_targeting')
                ->and($claudeMd)->toContain('resolve_preference')
                ->and($claudeMd)->toContain('check_config_key');
        },
    );

    it(
        'writes CLAUDE.md instructing the agent to trust its own writes rather than using introspection tools to confirm scaffolding',
        function (): void {
            $this->agent->install(devaiContext('body'), $this->root);

            $claudeMd = (string) file_get_contents($this->root . '/CLAUDE.md');
            expect($claudeMd)->toContain('introspection tools are for discovering pre-existing code');
        },
    );
});

// ---------------------------------------------------------------------------
// install() — settings
// ---------------------------------------------------------------------------

describe('settings', function (): void {
    beforeEach(function (): void {
        $this->root = devaiTempDir();
        $this->agent = new ClaudeCodeAgent(devaiRunner());
    });

    afterEach(function (): void {
        devaiRemoveDir($this->root);
    });

    it('writes .claude/settings.json with extraKnownMarketplaces.marko entry', function (): void {
        $this->agent->install(devaiContext(), $this->root);

        $path = $this->root . '/.claude/settings.json';
        expect(file_exists($path))->toBeTrue();

        $data = json_decode((string) file_get_contents($path), true);
        expect($data)->toHaveKey('extraKnownMarketplaces')
            ->and($data['extraKnownMarketplaces'])->toHaveKey('marko');
    });

    it('writes enabledPlugins listing marko-skills, marko-lsp, marko-mcp all true', function (): void {
        $this->agent->install(devaiContext(), $this->root);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        expect($data['enabledPlugins']['marko-skills@marko'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-lsp@marko'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-mcp@marko'])->toBeTrue();
    });

    it('merges into existing settings without clobbering unrelated user keys', function (): void {
        mkdir($this->root . '/.claude', 0755, true);
        file_put_contents(
            $this->root . '/.claude/settings.json',
            json_encode(['theme' => 'dark', 'someUserKey' => 42]),
        );

        $this->agent->install(devaiContext(), $this->root);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        expect($data['theme'])->toBe('dark')
            ->and($data['someUserKey'])->toBe(42)
            ->and($data['extraKnownMarketplaces'])->toHaveKey('marko');
    });

    it('throws a loud exception when marko is already registered and force is false', function (): void {
        mkdir($this->root . '/.claude', 0755, true);
        file_put_contents(
            $this->root . '/.claude/settings.json',
            json_encode(
                ['extraKnownMarketplaces' => ['marko' => ['source' => ['source' => 'github', 'repo' => 'marko-php/marko']]]],
            ),
        );

        expect(fn () => $this->agent->install(devaiContext(force: false), $this->root))
            ->toThrow(DevAiInstallException::class);
    });

    it('overwrites marko keys without throwing when force is true', function (): void {
        mkdir($this->root . '/.claude', 0755, true);
        file_put_contents(
            $this->root . '/.claude/settings.json',
            json_encode(['extraKnownMarketplaces' => ['marko' => ['source' => ['source' => 'old']]]]),
        );

        $this->agent->install(devaiContext(force: true), $this->root);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        expect($data['extraKnownMarketplaces']['marko']['source']['source'])->toBe('github');
    });

    it('preserves unrelated user keys under force (only marko-prefixed keys touched)', function (): void {
        mkdir($this->root . '/.claude', 0755, true);
        file_put_contents(
            $this->root . '/.claude/settings.json',
            json_encode([
                'theme' => 'light',
                'extraKnownMarketplaces' => ['marko' => ['old' => true]],
                'enabledPlugins' => ['marko-skills@marko' => false, 'other-plugin@other' => true],
            ]),
        );

        $this->agent->install(devaiContext(force: true), $this->root);

        $data = json_decode((string) file_get_contents($this->root . '/.claude/settings.json'), true);
        expect($data['theme'])->toBe('light')
            ->and($data['enabledPlugins']['other-plugin@other'])->toBeTrue()
            ->and($data['enabledPlugins']['marko-skills@marko'])->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Monorepo vs external-project detection
// ---------------------------------------------------------------------------

describe('monorepo detection', function (): void {
    it('chooses the local marketplace source when inside the monorepo', function (): void {
        $root = devaiTempDir();
        mkdir($root . '/packages/claude-plugins', 0755, true);

        try {
            (new ClaudeCodeAgent(devaiRunner()))->install(devaiContext(), $root);

            $data = json_decode((string) file_get_contents($root . '/.claude/settings.json'), true);
            expect($data['extraKnownMarketplaces']['marko']['source']['source'])->toBe('local');
        } finally {
            devaiRemoveDir($root);
        }
    });

    it('chooses the github source shape for an external project', function (): void {
        $root = devaiTempDir();

        try {
            (new ClaudeCodeAgent(devaiRunner()))->install(devaiContext(), $root);

            $source = json_decode(
                (string) file_get_contents($root . '/.claude/settings.json'),
                true,
            )['extraKnownMarketplaces']['marko']['source'];
            expect($source['source'])->toBe('github')
                ->and($source['repo'])->toBe('marko-php/marko');
        } finally {
            devaiRemoveDir($root);
        }
    });
});

// ---------------------------------------------------------------------------
// Legacy artifact cleanup
// ---------------------------------------------------------------------------

describe('legacy artifact cleanup', function (): void {
    it('removes any pre-existing .claude/plugins/marko/.lsp.json (idempotent)', function (): void {
        $root = devaiTempDir();

        try {
            $legacyDir = $root . '/.claude/plugins/marko';
            mkdir($legacyDir, 0755, true);
            file_put_contents($legacyDir . '/.lsp.json', '{"old":"stuff"}');

            (new ClaudeCodeAgent(devaiRunner()))->install(devaiContext(), $root);

            expect(file_exists($legacyDir . '/.lsp.json'))->toBeFalse();
        } finally {
            devaiRemoveDir($root);
        }
    });

    it('removes a previously-registered marko-mcp server via claude mcp remove', function (): void {
        $root = devaiTempDir();

        try {
            $runner = devaiRunner(listOutput: "marko-mcp: stdio - php marko mcp:serve\n");
            (new ClaudeCodeAgent($runner))->install(devaiContext(), $root);

            $listCall = null;
            $removeCall = null;
            foreach ($runner->calls as $call) {
                if ($call['command'] === 'claude' && ($call['args'][0] ?? '') === 'mcp' && ($call['args'][1] ?? '') === 'list') {
                    $listCall = $call;
                }
                if ($call['command'] === 'claude' && ($call['args'][0] ?? '') === 'mcp' && ($call['args'][1] ?? '') === 'remove') {
                    $removeCall = $call;
                }
            }
            expect($listCall)->not->toBeNull()
                ->and($removeCall)->not->toBeNull()
                ->and($removeCall['args'])->toContain('marko-mcp');
        } finally {
            devaiRemoveDir($root);
        }
    });

    it('does not call claude mcp remove when marko-mcp is absent (idempotent)', function (): void {
        $root = devaiTempDir();

        try {
            $runner = devaiRunner(listOutput: '');
            (new ClaudeCodeAgent($runner))->install(devaiContext(), $root);

            $removeCall = null;
            foreach ($runner->calls as $call) {
                if ($call['command'] === 'claude' && ($call['args'][0] ?? '') === 'mcp' && ($call['args'][1] ?? '') === 'remove') {
                    $removeCall = $call;
                }
            }
            expect($removeCall)->toBeNull();
        } finally {
            devaiRemoveDir($root);
        }
    });
});

// ---------------------------------------------------------------------------
// IntelephenseEnsurer wiring — --skip-lsp-deps
// ---------------------------------------------------------------------------

describe('lsp deps', function (): void {
    beforeEach(function (): void {
        $this->root = devaiTempDir();
    });

    afterEach(function (): void {
        devaiRemoveDir($this->root);
    });

    it('invokes IntelephenseEnsurer with skip=false during install', function (): void {
        $log = [];
        $ensurer = new class ($log) implements IntelephenseEnsurerInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$log,
            ) {}

            public function ensure(bool $skip = false): EnsureResult
            {
                $this->log[] = $skip;

                return EnsureResult::alreadyInstalled();
            }
        };

        (new ClaudeCodeAgent(devaiRunner(), $ensurer))->install(devaiContext(skipLspDeps: false), $this->root);

        expect($log)->toBe([false]);
    });

    it('passes --skip-lsp-deps through to IntelephenseEnsurer when set', function (): void {
        $log = [];
        $ensurer = new class ($log) implements IntelephenseEnsurerInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$log,
            ) {}

            public function ensure(bool $skip = false): EnsureResult
            {
                $this->log[] = $skip;

                return EnsureResult::skipped();
            }
        };

        (new ClaudeCodeAgent(devaiRunner(), $ensurer))->install(devaiContext(skipLspDeps: true), $this->root);

        expect($log)->toBe([true]);
    });

    it('completes install when no IntelephenseEnsurer is wired', function (): void {
        (new ClaudeCodeAgent(devaiRunner()))->install(devaiContext(), $this->root);

        expect(file_exists($this->root . '/.claude/settings.json'))->toBeTrue();
    });
});
